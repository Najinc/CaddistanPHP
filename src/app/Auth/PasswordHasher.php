<?php

declare(strict_types=1);

namespace App\Auth;

/** Argon2id : lent exprès, pour ralentir le cassage des mots de passe si la base fuite. */
final class PasswordHasher
{
    /** @param array $options paramètres Argon2id (plus légers dans les tests) */
    public function __construct(private readonly array $options = [])
    {
    }

    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, $this->options);
    }

    public function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }
}
