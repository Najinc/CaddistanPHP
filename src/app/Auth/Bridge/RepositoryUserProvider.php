<?php

declare(strict_types=1);

namespace App\Auth\Bridge;

use App\Auth\Authenticatable;
use App\Auth\UserProviderInterface;
use App\Repositories\UserRepository;

/**
 * Donne à la Partie 3 accès aux utilisateurs via le UserRepository existant.
 * Aucune requête SQL ici : elles restent dans le repository (Partie 2).
 */
final class RepositoryUserProvider implements UserProviderInterface
{
    private ?UserRepository $users = null;

    public function findById(int $id): ?Authenticatable
    {
        return self::wrap($this->users()->find($id));
    }

    public function findByEmail(string $email): ?Authenticatable
    {
        return self::wrap($this->users()->findByEmail($email));
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->users()->updatePassword($userId, $passwordHash);
    }

    /** Créé à la première utilisation : pas de connexion à la base tant qu'on n'en a pas besoin. */
    private function users(): UserRepository
    {
        return $this->users ??= new UserRepository();
    }

    private static function wrap(?array $row): ?UserRow
    {
        return $row !== null ? new UserRow($row) : null;
    }
}
