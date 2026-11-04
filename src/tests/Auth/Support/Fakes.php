<?php

declare(strict_types=1);

namespace Tests\Auth\Support;

use App\Auth\Authenticatable;
use App\Auth\Authorization\OwnedResource;
use App\Auth\Session\SessionInterface;
use App\Auth\Signing\UsedTokenStoreInterface;
use App\Auth\UserProviderInterface;

// Faux objets en mémoire : ils remplacent la base de données et la vraie session dans les tests.

final class FakeUser implements Authenticatable
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public string $passwordHash,
        public readonly string $role = 'customer',
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

final class ArraySession implements SessionInterface
{
    private array $data = [];
    private string $id;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function regenerate(): void
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->regenerate();
    }
}

final class InMemoryUsedTokenStore implements UsedTokenStoreInterface
{
    private array $used = [];

    public function markUsed(string $token, int $expiresAt): bool
    {
        if (isset($this->used[$token])) {
            return false;
        }
        $this->used[$token] = $expiresAt;

        return true;
    }
}
