<?php

declare(strict_types=1);

namespace Tests\Robustness;

use App\Core\Logger;

final class LoggerTest extends TestCase
{
    public function test_une_entree_egale_une_ligne_json(): void
    {
        Logger::security('auth.login_failed', ['email' => 'a@b.fr']);
        Logger::security('csrf.invalid');

        $entries = $this->logEntries();
        $this->assertSame(2, count($entries));
        $this->assertSame('auth.login_failed', $entries[0]['event']);
        $this->assertSame('warning', $entries[0]['level']);
        $this->assertSame('a@b.fr', $entries[0]['context']['email']);
    }

    public function test_un_retour_a_la_ligne_injecte_ne_cree_pas_de_fausse_ligne(): void
    {
        $evil = "x@x.fr\n{\"level\":\"info\",\"event\":\"auth.login_succeeded\"}\r\n";
        Logger::security('auth.login_failed', ['email' => $evil]);

        $this->assertSame(1, substr_count($this->logs(), "\n"), 'une seule ligne attendue');
        $email = $this->logEntries()[0]['context']['email'];
        $this->assertSame('x@x.fr\x0A{"level":"info","event":"auth.login_succeeded"}\x0D\x0A', $email);
    }

    public function test_caracteres_de_controle_et_separateurs_unicode_neutralises(): void
    {
        // ESC (séquences ANSI qui repeignent le terminal de l'admin), NUL, U+2028 (fin de ligne pour certains outils)
        $clean = Logger::sanitize("a\x1b[31mb\x00c\u{2028}d");

        $this->assertSame('a\x1B[31mb\x00c\x2028d', $clean);
    }

    public function test_les_secrets_sont_masques_a_toute_profondeur(): void
    {
        Logger::security('test', [
            'email'    => 'a@b.fr',
            'password' => 'Hunter2!',
            'new_password_confirmation' => 'Hunter3!',
            '_token'   => 'csrf-abc',
            'nested'   => ['Authorization' => 'Bearer xyz', 'api_key' => 'k', 'ok' => 1],
        ]);

        $logs = $this->logs();
        foreach (['Hunter2!', 'Hunter3!', 'csrf-abc', 'Bearer xyz'] as $secret) {
            $this->assertNotContains($secret, $logs);
        }
        $context = $this->logEntries()[0]['context'];
        $this->assertSame(Logger::REDACTED, $context['password']);
        $this->assertSame(Logger::REDACTED, $context['nested']['Authorization']);
        $this->assertSame(1, $context['nested']['ok']);
        $this->assertSame('a@b.fr', $context['email']);
    }

    public function test_les_parametres_sensibles_de_l_url_sont_masques(): void
    {
        $uri = Logger::sanitizeUri('/connexion/lien?expires=123&signature=deadbeef&page=2');

        $this->assertNotContains('deadbeef', $uri);
        $this->assertContains('signature=' . Logger::REDACTED, $uri);
        $this->assertContains('page=2', $uri);
    }

    public function test_les_cles_injectees_sont_aussi_nettoyees(): void
    {
        $clean = Logger::sanitize(["a\nb" => 1]);

        $this->assertSame(['a\x0Ab' => 1], $clean);
    }

    public function test_les_chaines_trop_longues_sont_tronquees(): void
    {
        $clean = Logger::sanitize(str_repeat('A', 100_000));

        $this->assertTrue(mb_strlen($clean) < 600, 'chaîne non tronquée');
        $this->assertContains('[tronqué]', $clean);
    }

    public function test_un_utf8_invalide_ne_fait_pas_perdre_l_entree(): void
    {
        Logger::security('test', ['email' => "a\xC3\x28@b.fr"]);

        $this->assertSame(1, count($this->logEntries()));
    }
}
