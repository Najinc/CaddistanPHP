<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Authorization\Gate;
use App\Auth\Bridge\OwnedRow;
use App\Auth\Bridge\UserRow;
use App\Auth\Exception\AccessDeniedException;

final class BridgeTest extends TestCase
{
    public function test_une_ligne_users_devient_un_utilisateur(): void
    {
        // PDO peut renvoyer les entiers sous forme de chaînes : l'id est converti.
        $user = new UserRow(['id' => '7', 'email' => 'a@b.fr', 'password' => '$argon2id$x', 'role' => 'admin']);

        $this->assertSame(7, $user->getAuthId());
        $this->assertSame('$argon2id$x', $user->getAuthPasswordHash());
        $this->assertSame('admin', $user->getRole());
    }

    public function test_une_ligne_sans_role_n_a_aucune_permission(): void
    {
        $gate = new Gate(require dirname(__DIR__, 2) . '/app/Auth/permissions.php');

        $this->assertFalse($gate->can(new UserRow(['id' => 1]), 'order.view'));
    }

    public function test_une_commande_appartient_a_son_user_id(): void
    {
        $gate = new Gate(require dirname(__DIR__, 2) . '/app/Auth/permissions.php');
        $owner = new UserRow(['id' => 1, 'role' => 'customer']);
        $other = new UserRow(['id' => 2, 'role' => 'customer']);
        $order = new OwnedRow(['id' => 10, 'user_id' => '1']);

        $gate->authorizeOwnership($owner, $order);
        $e = $this->expectThrows(AccessDeniedException::class, fn () => $gate->authorizeOwnership($other, $order));
        $this->assertSame(404, $e->status);
    }
}
