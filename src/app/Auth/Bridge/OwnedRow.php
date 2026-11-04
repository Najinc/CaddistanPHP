<?php

declare(strict_types=1);

namespace App\Auth\Bridge;

use App\Auth\Authorization\OwnedResource;

/** Ligne SQL qui appartient à un utilisateur via sa colonne user_id (commande, avis…). */
final class OwnedRow implements OwnedResource
{
    /** @param array<string, mixed> $row */
    public function __construct(private readonly array $row)
    {
    }

    public function getOwnerId(): int
    {
        return (int) $this->row['user_id'];
    }
}
