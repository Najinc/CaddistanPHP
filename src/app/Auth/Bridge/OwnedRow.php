<?php

declare(strict_types=1);

namespace App\Auth\Bridge;

use App\Auth\Authorization\OwnedResource;

/** Ligne SQL avec une colonne user_id (ex : une commande). */
final class OwnedRow implements OwnedResource
{
    public function __construct(private readonly array $row)
    {
    }

    public function getOwnerId(): int
    {
        return (int) $this->row['user_id'];
    }
}
