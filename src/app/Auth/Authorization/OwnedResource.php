<?php

declare(strict_types=1);

namespace App\Auth\Authorization;

/**
 * Ressource qui appartient à un utilisateur (ex. une commande).
 *
 * À implémenter par les modèles de la Partie 2 (ex. Order).
 */
interface OwnedResource
{
    /** Id de l'utilisateur propriétaire. */
    public function getOwnerId(): int;
}
