<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Authenticator;
use App\Auth\Csrf\CsrfTokenManager;
use App\Auth\Exception\AuthenticationException;
use App\Auth\PasswordHasher;
use Tests\Auth\Support\ArraySession;
use Tests\Auth\Support\FakeUser;
use Tests\Auth\Support\InMemoryUserProvider;

final class AuthenticatorTest extends TestCase
{
    private const PASSWORD = 'correct horse battery staple';

    private ArraySession $session;
    private InMemoryUserProvider $users;
    private PasswordHasher $hasher;
    private CsrfTokenManager $csrf;
    private Authenticator $auth;

    public function setUp(): void
    {
        $this->session = new ArraySession();
        $this->users = new InMemoryUserProvider();
        $this->hasher = new PasswordHasher(['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);
        $this->csrf = new CsrfTokenManager($this->session);
        $this->auth = $this->newAuthenticator();

        $this->users->add(new FakeUser(1, 'alice@caddistan.test', $this->hasher->hash(self::PASSWORD)));
    }

    public function test_connexion_reussie_regenere_l_id_de_session_et_le_token_csrf(): void
    {
        $sessionIdBefore = $this->session->id();
        $csrfBefore = $this->csrf->token();

        $this->assertTrue($this->auth->attempt('alice@caddistan.test', self::PASSWORD));

        $this->assertNotSame($sessionIdBefore, $this->session->id(), 'session_regenerate_id attendu');
        $this->assertNotSame($csrfBefore, $this->csrf->token(), 'nouveau token CSRF attendu');
        $this->assertSame(1, $this->auth->user()?->getAuthId());
    }

    public function test_mauvais_mot_de_passe_refuse(): void
    {
        $this->assertFalse($this->auth->attempt('alice@caddistan.test', 'mauvais'));
        $this->assertFalse($this->auth->check());
    }

    public function test_email_inconnu_refuse_sans_erreur(): void
    {
        $this->assertFalse($this->auth->attempt('inconnu@caddistan.test', self::PASSWORD));
        $this->assertFalse($this->auth->check());
    }

    public function test_email_normalise_casse_et_espaces(): void
    {
        $this->assertTrue($this->auth->attempt('  ALICE@Caddistan.TEST ', self::PASSWORD));
    }

    public function test_email_inconnu_prend_autant_de_temps_qu_un_mauvais_mot_de_passe(): void
    {
        // Paramètres plus lourds pour que le temps d'Argon2id dépasse largement le bruit de mesure.
        $hasher = new PasswordHasher(['memory_cost' => 16384, 'time_cost' => 2, 'threads' => 1]);
        $this->users->add(new FakeUser(2, 'bob@caddistan.test', $hasher->hash(self::PASSWORD)));
        $auth = new Authenticator($this->session, $this->users, $hasher, $this->csrf);

        $wrongPassword = self::medianDuration(fn () => $auth->attempt('bob@caddistan.test', 'mauvais'));
        $unknownEmail = self::medianDuration(fn () => $auth->attempt('personne@caddistan.test', 'mauvais'));

        $ratio = $unknownEmail / $wrongPassword;
        $this->assertTrue(
            $ratio > 0.5 && $ratio < 2.0,
            sprintf('temps trop différents : inconnu %.1f ms, mauvais mot de passe %.1f ms', $unknownEmail * 1e3, $wrongPassword * 1e3),
        );
    }

    public function test_un_ancien_hash_bcrypt_est_migre_en_argon2id_au_login(): void
    {
        $this->users->add(new FakeUser(3, 'carol@caddistan.test', password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4])));

        $this->assertTrue($this->auth->attempt('carol@caddistan.test', self::PASSWORD));

        $newHash = $this->users->findById(3)?->getAuthPasswordHash() ?? '';
        $this->assertTrue(str_starts_with($newHash, '$argon2id$'), 'hash migré attendu');
        $this->assertTrue($this->hasher->verify(self::PASSWORD, $newHash));
    }

    public function test_deconnexion_detruit_toute_la_session(): void
    {
        $this->auth->attempt('alice@caddistan.test', self::PASSWORD);
        $this->session->set('panier', [7 => 2]);
        $sessionIdBefore = $this->session->id();

        $this->auth->logout();

        $this->assertFalse($this->auth->check());
        $this->assertNotSame($sessionIdBefore, $this->session->id());
        $this->assertSame(null, $this->session->get('panier'), 'données de session supprimées');
        $this->assertFalse($this->newAuthenticator()->check(), 'toujours déconnecté à la requête suivante');
    }

    public function test_l_utilisateur_est_retrouve_a_la_requete_suivante(): void
    {
        $this->auth->attempt('alice@caddistan.test', self::PASSWORD);

        $this->assertSame(1, $this->newAuthenticator()->user()?->getAuthId());
    }

    public function test_un_compte_supprime_est_deconnecte(): void
    {
        $this->auth->attempt('alice@caddistan.test', self::PASSWORD);
        $this->users->delete(1);

        $this->assertFalse($this->newAuthenticator()->check());
    }

    public function test_require_user_leve_une_401_si_personne_n_est_connecte(): void
    {
        $e = $this->expectThrows(AuthenticationException::class, fn () => $this->auth->requireUser());

        $this->assertSame(401, $e->status);
    }

    /** Un nouvel Authenticator sur la même session simule la requête suivante. */
    private function newAuthenticator(): Authenticator
    {
        return new Authenticator($this->session, $this->users, $this->hasher, $this->csrf);
    }

    private static function medianDuration(callable $callback): float
    {
        $durations = [];
        for ($i = 0; $i < 7; $i++) {
            $start = hrtime(true);
            $callback();
            $durations[] = (hrtime(true) - $start) / 1e9;
        }
        sort($durations);

        return $durations[3];
    }
}
