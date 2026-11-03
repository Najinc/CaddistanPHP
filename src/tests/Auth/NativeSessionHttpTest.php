<?php

declare(strict_types=1);

namespace Tests\Auth;

/**
 * Test « boîte noire » de NativeSession : un vrai serveur PHP (php -S) sert
 * fixtures/session_app.php, et on lit les en-têtes Set-Cookie réellement envoyés.
 */
final class NativeSessionHttpTest extends TestCase
{
    private const COOKIE = '__Host-caddistan_session';

    /** @var resource|null */
    private static $server = null;
    private static int $port = 0;

    public function setUp(): void
    {
        if (self::$server === null) {
            self::startServer();
        }
    }

    public function test_le_cookie_de_session_est_httponly_secure_samesite_lax_et_host(): void
    {
        $cookie = $this->lastCookie($this->request('action=show'));

        $this->assertTrue($cookie['attrs']['secure'] ?? false, 'Secure manquant');
        $this->assertTrue($cookie['attrs']['httponly'] ?? false, 'HttpOnly manquant');
        $this->assertSame('Lax', $cookie['attrs']['samesite'] ?? null);
        $this->assertSame('/', $cookie['attrs']['path'] ?? null);
        $this->assertFalse(isset($cookie['attrs']['domain']), 'pas de Domain avec __Host-');
        $this->assertFalse(isset($cookie['attrs']['expires']), 'cookie de session (supprimé à la fermeture du navigateur)');
    }

    public function test_strict_mode_refuse_un_id_choisi_par_l_attaquant(): void
    {
        $forged = 'idchoisiparlattaquant0123456789';

        $response = $this->request('action=show', $forged);

        $this->assertNotSame($forged, $response['body']['id']);
        $this->assertSame($response['body']['id'], $this->lastCookie($response)['value']);
    }

    public function test_le_login_change_l_id_et_l_ancien_ne_vaut_plus_rien(): void
    {
        $beforeLogin = $this->request('action=show')['body']['id'];

        $login = $this->request('action=login', $beforeLogin);
        $afterLogin = $login['body']['id'];
        $this->assertNotSame($beforeLogin, $afterLogin);
        $this->assertSame(42, $login['body']['user']);
        $this->assertSame($afterLogin, $this->lastCookie($login)['value']);

        // Un attaquant qui connaissait l'ancien ID (fixation) n'obtient rien.
        $replay = $this->request('action=show', $beforeLogin);
        $this->assertNotSame($beforeLogin, $replay['body']['id']);
        $this->assertSame(null, $replay['body']['user']);

        // Le vrai utilisateur reste connecté avec le nouvel ID.
        $this->assertSame(42, $this->request('action=show', $afterLogin)['body']['user']);
    }

    public function test_le_logout_supprime_la_session_et_le_cookie(): void
    {
        $id = $this->request('action=login')['body']['id'];

        $logout = $this->request('action=logout', $id);
        $cookie = $this->lastCookie($logout);
        $this->assertSame('0', $cookie['attrs']['max-age'] ?? null, 'cookie expiré attendu');
        $this->assertTrue($cookie['attrs']['secure'] ?? false, 'mêmes attributs, sinon le navigateur ignore la suppression');

        // Même si le navigateur renvoie l'ancien cookie, la session n'existe plus côté serveur.
        $after = $this->request('action=show', $id);
        $this->assertNotSame($id, $after['body']['id']);
        $this->assertSame(null, $after['body']['user']);
    }

    public function test_timeout_d_inactivite_de_30_minutes(): void
    {
        $t = time();
        $id = $this->request("action=login&now={$t}")['body']['id'];

        $active = $this->request('action=show&now=' . ($t + 1000), $id);
        $this->assertSame($id, $active['body']['id']);
        $this->assertSame(42, $active['body']['user']);

        // 1801 s sans activité depuis la dernière requête (t + 1000).
        $idle = $this->request('action=show&now=' . ($t + 1000 + 1801), $id);
        $this->assertNotSame($id, $idle['body']['id']);
        $this->assertSame(null, $idle['body']['user']);
        $this->assertTrue($idle['body']['expired'], 'expiredDueToInactivity() attendu');
    }

    /**
     * @return array{cookies: list<array{name: string, value: string, attrs: array<string, string|true>}>, body: array<string, mixed>}
     */
    private function request(string $query, ?string $sessionId = null): array
    {
        $socket = fsockopen('127.0.0.1', self::$port, $errno, $errstr, 5);
        if ($socket === false) {
            throw new \RuntimeException("Connexion impossible au serveur de test : {$errstr}");
        }

        $request = "GET /?{$query} HTTP/1.0\r\nHost: 127.0.0.1\r\n";
        if ($sessionId !== null) {
            $request .= 'Cookie: ' . self::COOKIE . '=' . $sessionId . "\r\n";
        }
        fwrite($socket, $request . "\r\n");
        $raw = (string) stream_get_contents($socket);
        fclose($socket);

        [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');

        $cookies = [];
        foreach (explode("\r\n", $head) as $line) {
            if (stripos($line, 'Set-Cookie:') !== 0) {
                continue;
            }
            $parts = array_map('trim', explode(';', substr($line, 11)));
            [$name, $value] = array_pad(explode('=', (string) array_shift($parts), 2), 2, '');
            $attrs = [];
            foreach ($parts as $part) {
                [$attr, $attrValue] = array_pad(explode('=', $part, 2), 2, null);
                $attrs[strtolower($attr)] = $attrValue ?? true;
            }
            $cookies[] = ['name' => $name, 'value' => $value, 'attrs' => $attrs];
        }

        try {
            $decoded = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Typiquement un warning ou une dépréciation PHP affiché avant le JSON.
            throw new AssertionFailed('Réponse inattendue du serveur : ' . $body);
        }

        return ['cookies' => $cookies, 'body' => $decoded];
    }

    /**
     * @param array{cookies: list<array{name: string, value: string, attrs: array<string, string|true>}>} $response
     * @return array{name: string, value: string, attrs: array<string, string|true>}
     */
    private function lastCookie(array $response): array
    {
        $found = array_values(array_filter($response['cookies'], fn (array $c): bool => $c['name'] === self::COOKIE));
        if ($found === []) {
            throw new AssertionFailed('Aucun Set-Cookie ' . self::COOKIE);
        }

        return $found[count($found) - 1];
    }

    private static function startServer(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        if ($probe === false) {
            throw new \RuntimeException('Aucun port libre.');
        }
        $address = (string) stream_socket_get_name($probe, false);
        fclose($probe);
        self::$port = (int) substr($address, (int) strrpos($address, ':') + 1);

        $sessionDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'caddistan-sessions-' . bin2hex(random_bytes(4));
        mkdir($sessionDir);
        $log = $sessionDir . DIRECTORY_SEPARATOR . 'server.log';

        $command = [
            PHP_BINARY,
            // Entre guillemets : un chemin Windows court (ex. NAJIBC~1) contient "~", qui est un opérateur INI.
            '-d', 'session.save_path="' . $sessionDir . '"',
            '-d', 'display_errors=1',
            '-d', 'error_reporting=-1',
            '-S', '127.0.0.1:' . self::$port,
            __DIR__ . '/fixtures/session_app.php',
        ];
        $server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
        if ($server === false) {
            throw new \RuntimeException('Impossible de lancer php -S.');
        }
        self::$server = $server;

        register_shutdown_function(static function () use ($server, $sessionDir): void {
            proc_terminate($server);
            proc_close($server);
            foreach (glob($sessionDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($sessionDir);
        });

        for ($i = 0; $i < 50; $i++) {
            $connection = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            usleep(100_000);
        }

        throw new \RuntimeException('Le serveur de test ne répond pas. Log : ' . $log);
    }
}
