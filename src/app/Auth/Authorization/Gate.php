<?php

declare(strict_types=1);

namespace App\Auth\Authorization;

use App\Auth\Authenticatable;
use App\Auth\Exception\AccessDeniedException;
use App\Auth\Exception\AuthenticationException;

/**
 * Vérification des autorisations, côté serveur, dans chaque contrôleur qui en a besoin.
 * Cacher un bouton dans la vue ne protège rien : la vérification doit être ici.
 */
final class Gate
{
    public function __construct(private readonly PermissionMap $permissions)
    {
    }

    public function can(?Authenticatable $user, string $permission): bool
    {
        return $user !== null && $this->permissions->roleHas($user->getRole(), $permission);
    }

    /**
     * @throws AuthenticationException personne n'est connecté (401)
     * @throws AccessDeniedException   permission manquante (403)
     */
    public function authorize(?Authenticatable $user, string $permission): void
    {
        if ($user === null) {
            throw new AuthenticationException();
        }
        if (!$this->can($user, $permission)) {
            throw AccessDeniedException::forbidden($permission);
        }
    }

    /**
     * Contrôle de propriété : un client ne voit que SES ressources.
     *
     * Le propriétaire passe. Sinon il faut la permission $anyPermission (ex. "order.view_any"
     * pour l'admin). Sinon on répond 404, pas 403, pour ne pas confirmer que la ressource existe.
     *
     * @throws AuthenticationException personne n'est connecté (401)
     * @throws AccessDeniedException   ressource d'un autre utilisateur (404)
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

        throw AccessDeniedException::hidden(sprintf(
            'utilisateur %d sur une ressource de l\'utilisateur %d',
            $user->getAuthId(),
            $resource->getOwnerId(),
        ));
    }
}
