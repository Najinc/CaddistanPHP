<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Hachage Argon2id : lent exprès, pour qu'un attaquant qui vole la base
 * ne puisse tester que peu de mots de passe par seconde. Le sel est inclus dans le hash.
 */
final class PasswordHasher
{
    /** @param array $options paramètres Argon2id : ceux de PHP par défaut, plus légers dans les tests */
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

    /** Vrai si le hash vient d'un autre algorithme ou d'autres paramètres : il faut le refaire. */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }
}
