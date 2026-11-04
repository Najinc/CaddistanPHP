<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Csrf\CsrfTokenManager;
use App\Auth\Exception\AuthenticationException;
use App\Auth\Session\SessionInterface;

final class Authenticator
{
    /** Même message que l'email existe ou non. */
    public const FAILURE_MESSAGE = 'Email ou mot de passe incorrect.';

    private const SESSION_KEY = 'user_id';

    private ?Authenticatable $user = null;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly UserProviderInterface $users,
        private readonly PasswordHasher $hasher,
        private readonly CsrfTokenManager $csrf,
    ) {
    }

    /** "  Alice@X.fr " et "alice@x.fr" sont le même compte (à utiliser aussi à l'inscription). */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function attempt(string $email, #[\SensitiveParameter] string $password): bool
    {
        $user = $this->users->findByEmail(self::normalizeEmail($email));

        if ($user === null) {
            // On hache quand même : la réponse prend autant de temps qu'avec un mauvais mot de passe,
            // donc on ne peut pas deviner quels emails sont inscrits en chronométrant.
            $this->hasher->hash($password);

            return false;
        }

        if (!$this->hasher->verify($password, $user->getAuthPasswordHash())) {
            return false;
        }

        // Seul moment où l'on a le mot de passe en clair : on en profite pour refaire un vieux hash.
        if ($this->hasher->needsRehash($user->getAuthPasswordHash())) {
            $this->users->updatePasswordHash($user->getAuthId(), $this->hasher->hash($password));
        }

        $this->login($user);

        return true;
    }

    public function login(Authenticatable $user): void
    {
        // Nouvel ID de session : un ID volé ou imposé avant la connexion ne sert plus à rien.
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $user->getAuthId());
        $this->csrf->regenerate();

        $this->user = $user;
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
    }

    /** Relu en base à chaque requête : un rôle modifié ou un compte supprimé est pris en compte tout de suite. */
    public function user(): ?Authenticatable
    {
        if ($this->user === null) {
            $id = $this->session->get(self::SESSION_KEY);
            $this->user = is_int($id) ? $this->users->findById($id) : null;
        }

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function requireUser(): Authenticatable
    {
        return $this->user() ?? throw new AuthenticationException();
    }
}
