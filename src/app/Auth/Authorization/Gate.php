<?php

declare(strict_types=1);

namespace App\Auth\Authorization;

use App\Auth\Authenticatable;
use App\Auth\Exception\AccessDeniedException;
use App\Auth\Exception\AuthenticationException;

final class Gate
{
    /** @param array<string, list<string>> $permissions rôle => permissions (voir permissions.php) */
    public function __construct(private readonly array $permissions)
    {
    }

    // Refus par défaut : un rôle ou une permission inconnus ne donnent aucun droit.
    public function can(?Authenticatable $user, string $permission): bool
    {
        return $user !== null
            && in_array($permission, $this->permissions[$user->getRole()] ?? [], true);
    }

    public function authorize(?Authenticatable $user, string $permission): void
    {
        if ($user === null) {
            throw new AuthenticationException();
        }
        if (!$this->can($user, $permission)) {
            throw AccessDeniedException::missingPermission($permission);
        }
    }

    public function authorizeOwnership(?Authenticatable $user, OwnedResource $resource): void
    {
        if ($user === null) {
            throw new AuthenticationException();
        }
        if ($resource->getOwnerId() !== $user->getAuthId()) {
            throw AccessDeniedException::notOwner();
        }
    }
}
