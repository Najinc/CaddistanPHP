<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Authorization\Gate;
use App\Auth\Exception\AccessDeniedException;
use App\Auth\Exception\AuthenticationException;
use Tests\Auth\Support\FakeOrder;
use Tests\Auth\Support\FakeUser;

final class GateTest extends TestCase
{
    private Gate $gate;
    private FakeUser $client;
    private FakeUser $otherClient;
    private FakeUser $admin;

    public function setUp(): void
    {
        $this->gate = new Gate(require dirname(__DIR__, 2) . '/app/Auth/permissions.php');
        $this->client = new FakeUser(1, 'alice@caddistan.test', '', 'client');
        $this->otherClient = new FakeUser(2, 'bob@caddistan.test', '', 'client');
        $this->admin = new FakeUser(3, 'admin@caddistan.test', '', 'admin');
    }

    public function test_admin_peut_modifier_un_caddy(): void
    {
        $this->gate->authorize($this->admin, 'caddy.edit');
        $this->assertTrue($this->gate->can($this->admin, 'caddy.edit'));
    }

    public function test_client_ne_peut_pas_modifier_un_caddy_403(): void
    {
        $e = $this->expectThrows(AccessDeniedException::class, fn () => $this->gate->authorize($this->client, 'caddy.edit'));

        $this->assertSame(403, $e->status);
        $this->assertSame('Accès refusé.', $e->getMessage());
    }

    public function test_personne_de_connecte_401(): void
    {
        $e = $this->expectThrows(AuthenticationException::class, fn () => $this->gate->authorize(null, 'order.view'));

        $this->assertSame(401, $e->status);
        $this->assertFalse($this->gate->can(null, 'order.view'));
    }

    public function test_permission_inconnue_refusee_meme_a_l_admin(): void
    {
        $this->assertFalse($this->gate->can($this->admin, 'caddy.edti'));
    }

    public function test_role_inconnu_n_a_aucune_permission(): void
    {
        $this->assertFalse($this->gate->can(new FakeUser(9, 'x@caddistan.test', '', 'superadmin'), 'order.view'));
    }

    public function test_un_client_voit_sa_propre_commande(): void
    {
        $this->gate->authorizeOwnership($this->client, new FakeOrder(ownerId: 1), 'order.view_any');
        $this->assertTrue(true);
    }

    public function test_un_client_ne_voit_pas_la_commande_d_un_autre_404(): void
    {
        $e = $this->expectThrows(
            AccessDeniedException::class,
            fn () => $this->gate->authorizeOwnership($this->otherClient, new FakeOrder(ownerId: 1), 'order.view_any'),
        );

        $this->assertSame(404, $e->status, 'on ne révèle pas que la commande existe');
        $this->assertSame('Page introuvable.', $e->getMessage());
    }

    public function test_l_admin_voit_toutes_les_commandes_grace_a_order_view_any(): void
    {
        $this->gate->authorizeOwnership($this->admin, new FakeOrder(ownerId: 1), 'order.view_any');
        $this->assertTrue(true);
    }

    public function test_sans_permission_globale_l_admin_est_traite_comme_les_autres(): void
    {
        $this->expectThrows(AccessDeniedException::class, fn () => $this->gate->authorizeOwnership($this->admin, new FakeOrder(ownerId: 1)));
    }

    public function test_propriete_sans_utilisateur_401(): void
    {
        $this->expectThrows(AuthenticationException::class, fn () => $this->gate->authorizeOwnership(null, new FakeOrder(ownerId: 1)));
    }
}
