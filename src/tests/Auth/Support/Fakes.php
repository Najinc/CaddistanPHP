<?php

declare(strict_types=1);

namespace Tests\Auth\Support;

use App\Auth\Authenticatable;
use App\Auth\Authorization\OwnedResource;
use App\Auth\UserProviderInterface;

/*
 * Doublures de test : elles remplacent le modèle User et le dépôt de la Partie 2,
 * pour tester la Partie 3 sans base de données.
 */

final class FakeUser implements Authenticatable
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public string $passwordHash,
        public readonly string $role = 'client',
    ) {
    }

    public function getAuthId(): int
    {
        return $this->id;
    }

    public function getAuthPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): string
    {
        return $this->role;
    }
}

final class InMemoryUserProvider implements UserProviderInterface
{
    /** @var array<int, FakeUser> */
    private array $users = [];

    public function add(FakeUser $user): void
    {
        $this->users[$user->id] = $user;
    }

    public function delete(int $id): void
    {
        unset($this->users[$id]);
    }

    public function findById(int $id): ?Authenticatable
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?Authenticatable
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->users[$userId]->passwordHash = $passwordHash;
    }
}

final class FakeOrder implements OwnedResource
{
    public function __construct(private readonly int $ownerId)
    {
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }
}
