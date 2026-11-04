<?php

declare(strict_types=1);

namespace App\Auth\Authorization;

/** Ressource qui appartient à un utilisateur (ex : une commande). */
interface OwnedResource
{
    public function getOwnerId(): int;
}
