<?php

declare(strict_types=1);

namespace App\Auth\Bridge;

use App\Auth\Authenticatable;

/** Ligne de la table users (tableau renvoyé par UserRepository), vue comme un Authenticatable. */
final class UserRow implements Authenticatable
{
    /** @param array<string, mixed> $row */
    public function __construct(public readonly array $row)
    {
    }

    public function getAuthId(): int
    {
        return (int) $this->row['id'];
    }

    public function getAuthPasswordHash(): string
    {
        return (string) ($this->row['password'] ?? '');
    }

    public function getRole(): string
    {
        return (string) ($this->row['role'] ?? '');
    }
}
