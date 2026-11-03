<?php

declare(strict_types=1);

namespace App\Auth\Session;

/**
 * Session PHP native, durcie dans le code : elle reste sûre même si le php.ini change.
 */
final class NativeSession implements SessionInterface
{
    public const DEFAULT_NAME = 'caddistan_session';
    private const LAST_ACTIVITY = '_session.last_activity';

    private bool $expired = false;

    /**
     * @param int                  $idleTimeout secondes d'inactivité avant destruction de la session
     * @param (\Closure(): int)|null $clock     horloge injectable (tests) ; time() par défaut
     */
    public function __construct(
        private readonly CookiePolicy $cookies = new CookiePolicy(),
        private readonly int $idleTimeout = 1800,
        private readonly string $name = self::DEFAULT_NAME,
        private readonly ?\Closure $clock = null,
    ) {
        if ($idleTimeout < 60) {
            throw new \InvalidArgumentException('Le timeout d\'inactivité doit être d\'au moins 60 secondes.');
        }
        if (preg_match('/\A[A-Za-z0-9_]+\z/', $name) !== 1) {
            throw new \InvalidArgumentException('Nom de session invalide.');
        }
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $this->boot();

        $lastActivity = $_SESSION[self::LAST_ACTIVITY] ?? null;
        if (is_int($lastActivity) && $this->now() - $lastActivity > $this->idleTimeout) {
            // Inactif trop longtemps : on jette tout et on repart sur une session vierge (nouvel ID).
            $this->destroy();
            $this->boot();
            $this->expired = true;
        }

        $_SESSION[self::LAST_ACTIVITY] = $this->now();
    }

    public function id(): string
    {
        $this->start();

        return (string) session_id();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        // true : l'ancienne session est supprimée côté serveur. Un ID volé ou fixé avant le login ne sert plus à rien.
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Impossible de régénérer l\'identifiant de session.');
        }
    }

    public function destroy(): void
    {
        $this->start();

        $name = session_name();
        $_SESSION = [];
        session_destroy();

        // Le cookie est supprimé avec exactement les mêmes attributs, sinon le navigateur l'ignore (surtout avec __Host-).
        setcookie((string) $name, '', $this->cookies->options(1));
        unset($_COOKIE[$name]);
    }

    public function expiredDueToInactivity(): bool
    {
        return $this->expired;
    }

    private function boot(): void
    {
        if (headers_sent($file, $line)) {
            throw new \LogicException(sprintf('Session impossible à démarrer : sortie déjà envoyée (%s:%d).', $file, $line));
        }

        // Refuse les identifiants que le serveur n'a pas générés : bloque la fixation de session.
        ini_set('session.use_strict_mode', '1');
        // L'identifiant ne passe que par le cookie, jamais par l'URL.
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        // Le ramasse-miettes ne doit pas supprimer une session avant la fin du timeout.
        ini_set('session.gc_maxlifetime', (string) $this->idleTimeout);

        $options = $this->cookies->options();
        session_name($this->cookies->name($this->name));
        session_set_cookie_params([
            'lifetime' => 0, // cookie de session : supprimé à la fermeture du navigateur
            'path' => $options['path'],
            'domain' => $options['domain'],
            'secure' => $options['secure'],
            'httponly' => true,
            'samesite' => $options['samesite'],
        ]);

        if (!session_start()) {
            throw new \RuntimeException('Impossible de démarrer la session.');
        }
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
