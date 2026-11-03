<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Csrf\CsrfTokenManager;
use App\Auth\Exception\AuthenticationException;
use App\Auth\Session\SessionInterface;

/**
 * Connexion / déconnexion par session.
 */
final class Authenticator
{
    /** Le même message que le compte existe ou non : on ne révèle pas quels emails sont inscrits. */
    public const FAILURE_MESSAGE = 'Email ou mot de passe incorrect.';

    private const SESSION_USER_ID = '_auth.user_id';

    private ?Authenticatable $user = null;
    private bool $resolved = false;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly UserProviderInterface $users,
        private readonly PasswordHasher $hasher,
        private readonly CsrfTokenManager $csrf,
    ) {
    }

    /** À utiliser aussi à l'inscription, pour que « Alice@x.fr » et « alice@x.fr » soient le même compte. */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /**
     * Vérifie les identifiants et connecte l'utilisateur.
     * En cas d'échec, afficher Authenticator::FAILURE_MESSAGE, quelle que soit la cause.
     *
     * Le rate limiting (Partie 5) s'applique autour de cet appel.
     */
    public function attempt(string $email, #[\SensitiveParameter] string $password): bool
    {
        $user = $this->users->findByEmail(self::normalizeEmail($email));

        if ($user === null) {
            // Même coût qu'une vraie vérification : le temps de réponse ne trahit pas l'absence du compte.
            $this->hasher->simulateVerify($password);

            return false;
        }

        if (!$this->hasher->verify($password, $user->getAuthPasswordHash())) {
            return false;
        }

        // Le mot de passe en clair n'est disponible qu'ici : on en profite pour migrer les vieux hash.
        if ($this->hasher->needsRehash($user->getAuthPasswordHash())) {
            $this->users->updatePasswordHash($user->getAuthId(), $this->hasher->hash($password));
        }

        $this->login($user);

        return true;
    }

    /** Connecte un utilisateur déjà vérifié (après attempt(), après une inscription, ou via un magic link). */
    public function login(Authenticatable $user): void
    {
        // Nouvel ID de session : un ID fixé ou volé avant la connexion devient inutilisable.
        $this->session->regenerate();
        $this->session->set(self::SESSION_USER_ID, $user->getAuthId());
        $this->csrf->regenerate();

        $this->user = $user;
        $this->resolved = true;
    }

    /** Destruction complète : données, session côté serveur et cookie. */
    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
        $this->resolved = true;
    }

    public function user(): ?Authenticatable
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $id = $this->session->get(self::SESSION_USER_ID);

            if (is_int($id)) {
                // Rechargé depuis la base à chaque requête : un rôle modifié ou un compte supprimé est pris en compte tout de suite.
                $this->user = $this->users->findById($id);
                if ($this->user === null) {
                    $this->session->remove(self::SESSION_USER_ID);
                }
            }
        }

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** @throws AuthenticationException */
    public function requireUser(): Authenticatable
    {
        return $this->user() ?? throw new AuthenticationException();
    }
}
