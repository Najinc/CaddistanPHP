<?php

declare(strict_types=1);

namespace Tests\Robustness;

use App\Core\Cache;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Throttle;
use App\Core\TooManyRequestsException;

final class RateLimiterTest extends TestCase
{
    private int $now = 1_000_000_200;   // 300 s après le début d'une tranche de 15 min (qui commence à 999 999 900)
    private RateLimiter $limiter;
    private Cache $cache;

    public function setUp(): void
    {
        parent::setUp();
        $clock = fn () => $this->now;
        $this->cache = new Cache($this->tmp . '/cache', $clock);
        $this->limiter = new RateLimiter($this->cache, $clock);
    }

    public function test_autorise_jusqu_au_maximum_puis_refuse(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue($this->limiter->hit('k', 5, 900)->allowed, "essai $i refusé");
        }

        $result = $this->limiter->hit('k', 5, 900);
        $this->assertFalse($result->allowed);
        $this->assertSame(6, $result->hits);
        $this->assertSame(0, $result->remaining());
    }

    public function test_retry_after_vaut_le_temps_restant_avant_la_fin_de_la_tranche(): void
    {
        // tranche [999 999 900 ; 1 000 000 800[ : il reste 600 s
        $this->assertSame(600, $this->limiter->hit('k', 1, 900)->retryAfter);
    }

    public function test_fenetre_fixe_le_compteur_repart_a_zero_a_la_tranche_suivante(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->limiter->hit('k', 5, 900);
        }
        $this->assertFalse($this->limiter->hit('k', 5, 900)->allowed);

        $this->now += 600;   // début exact de la tranche suivante
        $result = $this->limiter->hit('k', 5, 900);
        $this->assertTrue($result->allowed);
        $this->assertSame(1, $result->hits);
    }

    public function test_le_depassement_n_est_signale_qu_une_fois_par_tranche(): void
    {
        $flags = [];
        for ($i = 0; $i < 10; $i++) {
            $flags[] = $this->limiter->hit('k', 3, 60)->justExceeded();
        }

        $this->assertSame(1, count(array_filter($flags)));
        $this->assertTrue($flags[3], 'le 4e appel (premier refus) doit être signalé');
    }

    public function test_les_cles_sont_independantes_et_clear_remet_a_zero(): void
    {
        $this->limiter->hit('login:account:a@b.fr', 1, 900);
        $this->assertFalse($this->limiter->hit('login:account:a@b.fr', 1, 900)->allowed);
        $this->assertTrue($this->limiter->hit('login:account:c@d.fr', 1, 900)->allowed);

        $this->limiter->clear('login:account:a@b.fr', 900);
        $this->assertTrue($this->limiter->hit('login:account:a@b.fr', 1, 900)->allowed);
    }

    public function test_cache_json_uniquement_et_nom_de_fichier_hache(): void
    {
        $this->cache->set('../../evil', ['a' => 1], 60);

        $files = glob($this->tmp . '/cache/*');
        $this->assertSame(1, count($files), 'le fichier doit rester dans le dossier du cache');
        $this->assertTrue((bool) preg_match('#/[0-9a-f]{64}\.json$#', $files[0]), 'nom = sha256');
        $this->assertSame(['a' => 1], json_decode(file_get_contents($files[0]), true)['value']);
    }

    public function test_cache_expire_apres_le_ttl(): void
    {
        $this->assertSame('v', $this->cache->remember('k', 60, fn () => 'v'));
        $this->assertSame('v', $this->cache->remember('k', 60, fn () => 'autre'));

        $this->now += 61;
        $this->assertSame(null, $this->cache->get('k'));
        $this->assertSame(1, $this->cache->prune());
    }

    public function test_throttle_login_par_compte_avec_429_et_log(): void
    {
        $throttle = new Throttle($this->limiter);
        $login = fn (string $email) => $throttle->before($this->post('/connexion', ['email' => $email]));

        for ($i = 0; $i < 5; $i++) {
            $login('Client@Caddistan.local');
        }

        // Même compte, casse différente : même compteur
        $e = $this->expectThrows(TooManyRequestsException::class, fn () => $login('client@caddistan.local'));
        $this->assertSame(429, $e->status);
        $this->assertSame(600, $e->retryAfter);

        $entries = $this->logEntries();
        $this->assertSame(1, count($entries));
        $this->assertSame('rate_limit.exceeded', $entries[0]['event']);
        $this->assertSame('login:account', $entries[0]['context']['rule']);
    }

    public function test_throttle_login_par_ip_bloque_le_password_spraying(): void
    {
        $throttle = new Throttle($this->limiter);

        // 20 comptes différents depuis la même IP : chaque compte est sous sa limite, mais l'IP non
        for ($i = 0; $i < Throttle::LOGIN_PER_IP[0]; $i++) {
            $throttle->before($this->post('/connexion', ['email' => "user$i@x.fr"]));
        }

        $this->expectThrows(TooManyRequestsException::class,
            fn () => $throttle->before($this->post('/connexion', ['email' => 'encore@x.fr'])));
    }

    private function post(string $path, array $body): Request
    {
        return new Request('POST', $path, [], $body, [], ['REMOTE_ADDR' => '203.0.113.7']);
    }
}
