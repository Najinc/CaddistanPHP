<?php

declare(strict_types=1);

namespace App\Auth\Authorization;

/** Ressource qui appartient à un utilisateur, par exemple une commande (implémenté par la Partie 2). */
interface OwnedResource
{
    public function getOwnerId(): int;
}
