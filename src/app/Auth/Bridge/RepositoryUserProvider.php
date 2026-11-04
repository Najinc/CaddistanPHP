<?php

declare(strict_types=1);

namespace App\Auth\Bridge;

use App\Auth\Authenticatable;
use App\Auth\UserProviderInterface;
use App\Repositories\UserRepository;

/** Utilise le UserRepository existant : aucune requête SQL ici. */
final class RepositoryUserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    public function findById(int $id): ?Authenticatable
    {
        $row = $this->users->find($id);

        return $row !== null ? new UserRow($row) : null;
    }

    public function findByEmail(string $email): ?Authenticatable
    {
        $row = $this->users->findByEmail($email);

        return $row !== null ? new UserRow($row) : null;
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->users->updatePassword($userId, $passwordHash);
    }
}
