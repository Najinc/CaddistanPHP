<?php

declare(strict_types=1);

namespace App\Auth;

/** Accès aux utilisateurs en base (implémenté par la Partie 2). */
interface UserProviderInterface
{
    public function findById(int $id): ?Authenticatable;

    public function findByEmail(string $email): ?Authenticatable;

    public function updatePasswordHash(int $userId, string $passwordHash): void;
}
