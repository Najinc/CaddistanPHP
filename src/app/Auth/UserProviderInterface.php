<?php

declare(strict_types=1);

namespace App\Auth;

interface UserProviderInterface
{
    public function findById(int $id): ?Authenticatable;

    public function findByEmail(string $email): ?Authenticatable;

    public function updatePasswordHash(int $userId, string $passwordHash): void;
}
