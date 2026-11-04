<?php

declare(strict_types=1);

namespace App\Auth\Authorization;

use App\Auth\Authenticatable;
use App\Auth\Exception\AccessDeniedException;
use App\Auth\Exception\AuthenticationException;

/** Vérifie les droits côté serveur. Cacher un bouton dans la vue ne protège rien. */
final class Gate
{
    /** @param array<string, list<string>> $permissions rôle => permissions, voir permissions.php */
    public function __construct(private readonly array $permissions)
    {
    }

    /** Refus par défaut : un rôle ou une permission inconnus ne donnent aucun droit. */
    public function can(?Authenticatable $user, string $permission): bool
    {
        return $user !== null
            && in_array($permission, $this->permissions[$user->getRole()] ?? [], true);
    }

    /** 401 si personne n'est connecté, 403 s'il manque la permission. */
    public function authorize(?Authenticatable $user, string $permission): void
    {
        if ($user === null) {
            throw new AuthenticationException();
        }
        if (!$this->can($user, $permission)) {
            throw AccessDeniedException::missingPermission($permission);
        }
    }

    /**
     * Un client ne voit que ses propres ressources (ex. ses commandes).
     * Passent : le propriétaire, ou celui qui a $anyPermission (ex. l'admin avec "order.view_any").
     * Les autres reçoivent un 404.
     */
    public function authorizeOwnership(?Authenticatable $user, OwnedResource $resource, ?string $anyPermission = null): void
    {
        if ($user === null) {
            throw new AuthenticationException();
        }
        if ($resource->getOwnerId() === $user->getAuthId()) {
            return;
        }
        if ($anyPermission !== null && $this->can($user, $anyPermission)) {
            return;
        }

        throw AccessDeniedException::notOwner("utilisateur {$user->getAuthId()} sur une ressource de l'utilisateur {$resource->getOwnerId()}");
    }
}
