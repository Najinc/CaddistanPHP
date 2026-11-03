<?php

declare(strict_types=1);

namespace App\Auth\Authorization;

/**
 * Association rôle → permissions.
 *
 * Refus par défaut : un rôle inconnu n'a aucune permission, et une permission
 * qu'aucun rôle ne possède (faute de frappe dans le code) est refusée à tout le monde.
 */
final class PermissionMap
{
    /** @var array<string, array<string, true>> */
    private array $map = [];

    /** @param array<string, list<string>> $rolePermissions */
    public function __construct(array $rolePermissions)
    {
        foreach ($rolePermissions as $role => $permissions) {
            if (!is_string($role) || $role === '' || !is_array($permissions)) {
                throw new \InvalidArgumentException('Format attendu : ["role" => ["permission", ...]].');
            }
            $this->map[$role] = [];
            foreach ($permissions as $permission) {
                if (!is_string($permission) || preg_match('/\A[a-z_]+(\.[a-z_]+)+\z/', $permission) !== 1) {
                    throw new \InvalidArgumentException('Permission invalide : ' . var_export($permission, true));
                }
                $this->map[$role][$permission] = true;
            }
        }
    }

    /** Permissions de Caddistan, définies dans permissions.php. */
    public static function caddistan(): self
    {
        return new self(require __DIR__ . '/../permissions.php');
    }

    public function roleHas(string $role, string $permission): bool
    {
        return isset($this->map[$role][$permission]);
    }

    /** @return list<string> */
    public function permissionsOf(string $role): array
    {
        return array_keys($this->map[$role] ?? []);
    }
}
