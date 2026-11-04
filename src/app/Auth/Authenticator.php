<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Csrf\CsrfTokenManager;
use App\Auth\Session\SessionInterface;

final class Authenticator
{
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

    public function attempt(string $email, #[\SensitiveParameter] string $password): bool
    {
        $user = $this->users->findByEmail(mb_strtolower(trim($email)));

        if ($user === null) {
            // On hache quand même pour répondre aussi lentement qu'avec un mauvais mot de passe.
            $this->hasher->hash($password);

            return false;
        }

        if (!$this->hasher->verify($password, $user->getAuthPasswordHash())) {
            return false;
        }

        if ($this->hasher->needsRehash($user->getAuthPasswordHash())) {
            $this->users->updatePasswordHash($user->getAuthId(), $this->hasher->hash($password));
        }

        $this->login($user);

        return true;
    }

    public function login(Authenticatable $user): void
    {
        // Nouvel ID : un ID de session connu avant la connexion ne sert plus à rien.
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

    public function user(): ?Authenticatable
    {
        // Relu en base à chaque requête : un changement de rôle s'applique tout de suite.
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
}
