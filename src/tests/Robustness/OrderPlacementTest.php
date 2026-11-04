<?php

declare(strict_types=1);

namespace Tests\Robustness;

use App\Core\Database;
use App\Services\OrderException;
use App\Services\OrderPlacement;
use App\Services\Payment\PaymentDeclinedException;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SimulatedPaymentGateway;
use PDO;

/**
 * Test d'intégration sur la vraie base : on note l'état avant, et tearDown() remet le stock et supprime
 * les commandes créées, pour ne pas abîmer les données de démo.
 */
final class OrderPlacementTest extends TestCase
{
    private const SHIPPING = ['name' => 'Test Robustesse', 'address' => '1 rue du Test', 'zip' => '75001', 'city' => 'Paris', 'phone' => '0600000000'];

    private PDO $db;
    private int $userId;
    /** @var array<int, array{price_cents: int, stock: int, is_active: int}> */
    private array $initial = [];
    private int $maxOrderId;

    public function setUp(): void
    {
        parent::setUp();

        try {
            $this->db = Database::connection();
        } catch (\PDOException) {
            throw new Skipped('base de données injoignable, lancer dans Docker');
        }

        $this->userId = (int) $this->db->query("SELECT id FROM users WHERE role = 'customer' ORDER BY id LIMIT 1")->fetchColumn();
        $this->maxOrderId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) FROM orders')->fetchColumn();

        foreach ($this->db->query('SELECT id, price_cents, stock, is_active FROM products ORDER BY id LIMIT 3') as $row) {
            $this->initial[(int) $row['id']] = array_map('intval', $row);
        }
    }

    public function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->prepare('DELETE FROM orders WHERE id > ?')->execute([$this->maxOrderId]);
            $restore = $this->db->prepare('UPDATE products SET price_cents = ?, stock = ?, is_active = ? WHERE id = ?');
            foreach ($this->initial as $id => $p) {
                $restore->execute([$p['price_cents'], $p['stock'], $p['is_active'], $id]);
            }
        }
        parent::tearDown();
    }

    public function test_prix_et_total_recalcules_depuis_la_base(): void
    {
        [$a, $b] = array_keys($this->initial);
        $orderId = (new OrderPlacement())->place($this->userId, self::SHIPPING, [$a => 2, $b => 1]);

        $expected = 2 * $this->initial[$a]['price_cents'] + $this->initial[$b]['price_cents'];
        $order = $this->row('SELECT status, total_cents FROM orders WHERE id = ?', $orderId);
        $this->assertSame('paid', $order['status']);
        $this->assertSame($expected, (int) $order['total_cents']);

        $unit = (int) $this->row('SELECT unit_price_cents FROM order_items WHERE order_id = ? AND quantity = 2', $orderId)['unit_price_cents'];
        $this->assertSame($this->initial[$a]['price_cents'], $unit);
        $this->assertSame($this->initial[$a]['stock'] - 2, $this->stock($a));
    }

    public function test_le_prix_utilise_est_celui_de_la_base_au_moment_de_la_commande(): void
    {
        [$a] = array_keys($this->initial);
        // L'admin change le prix entre l'ajout au panier et la commande : c'est le nouveau prix qui compte
        $this->db->prepare('UPDATE products SET price_cents = 12345 WHERE id = ?')->execute([$a]);

        $orderId = (new OrderPlacement())->place($this->userId, self::SHIPPING, [$a => 1]);

        $this->assertSame(12345, (int) $this->row('SELECT total_cents FROM orders WHERE id = ?', $orderId)['total_cents']);
    }

    public function test_paiement_refuse_rien_n_est_enregistre(): void
    {
        [$a, $b] = array_keys($this->initial);
        $declines = new class implements PaymentGateway {
            public function charge(int $orderId, int $amountCents): void
            {
                throw new PaymentDeclinedException('Carte refusée.');
            }
        };

        $this->expectThrows(PaymentDeclinedException::class,
            fn () => (new OrderPlacement($declines))->place($this->userId, self::SHIPPING, [$a => 1, $b => 1]));

        $this->assertNothingWritten();
        $this->assertContains('payment.declined', $this->logs());
    }

    public function test_plafond_du_paiement_simule(): void
    {
        [$a] = array_keys($this->initial);
        $this->db->prepare('UPDATE products SET price_cents = ?, stock = 99 WHERE id = ?')
                 ->execute([SimulatedPaymentGateway::CEILING_CENTS, $a]);

        $this->expectThrows(PaymentDeclinedException::class,
            fn () => (new OrderPlacement())->place($this->userId, self::SHIPPING, [$a => 2]));

        $this->assertSame(0, $this->ordersCreated());
        $this->assertSame(99, $this->stock($a));
    }

    public function test_panne_technique_au_milieu_rollback_aussi(): void
    {
        [$a, $b] = array_keys($this->initial);
        $crashes = new class implements PaymentGateway {
            public function charge(int $orderId, int $amountCents): void
            {
                throw new \RuntimeException('timeout du prestataire');
            }
        };

        $this->expectThrows(\RuntimeException::class,
            fn () => (new OrderPlacement($crashes))->place($this->userId, self::SHIPPING, [$a => 1, $b => 1]));

        $this->assertNothingWritten();
    }

    public function test_stock_insuffisant_sur_une_ligne_annule_toute_la_commande(): void
    {
        [$a, $b] = array_keys($this->initial);
        $this->db->prepare('UPDATE products SET stock = 1 WHERE id = ?')->execute([$b]);

        $e = $this->expectThrows(OrderException::class,
            fn () => (new OrderPlacement())->place($this->userId, self::SHIPPING, [$a => 1, $b => 2]));

        $this->assertContains('Stock insuffisant', $e->getMessage());
        $this->assertSame(0, $this->ordersCreated());
        $this->assertSame($this->initial[$a]['stock'], $this->stock($a), 'le stock de la 1re ligne ne doit pas avoir bougé');
    }

    public function test_produit_desactive_refuse(): void
    {
        [$a] = array_keys($this->initial);
        $this->db->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$a]);

        $this->expectThrows(OrderException::class,
            fn () => (new OrderPlacement())->place($this->userId, self::SHIPPING, [$a => 1]));
        $this->assertSame(0, $this->ordersCreated());
    }

    public function test_quantite_hors_limites_refusee(): void
    {
        [$a] = array_keys($this->initial);

        foreach ([0, -3, 100, '2'] as $quantity) {
            $this->expectThrows(OrderException::class,
                fn () => (new OrderPlacement())->place($this->userId, self::SHIPPING, [$a => $quantity]));
        }
        $this->assertSame(0, $this->ordersCreated());
    }

    private function assertNothingWritten(): void
    {
        $this->assertSame(0, $this->ordersCreated(), 'aucune commande ne doit rester');
        $items = (int) $this->row('SELECT COUNT(*) AS n FROM order_items WHERE order_id > ?', $this->maxOrderId)['n'];
        $this->assertSame(0, $items, 'aucune ligne de commande ne doit rester');
        foreach ($this->initial as $id => $p) {
            $this->assertSame($p['stock'], $this->stock($id), "stock du produit $id modifié");
        }
    }

    private function ordersCreated(): int
    {
        return (int) $this->row('SELECT COUNT(*) AS n FROM orders WHERE id > ?', $this->maxOrderId)['n'];
    }

    private function stock(int $id): int
    {
        return (int) $this->row('SELECT stock FROM products WHERE id = ?', $id)['stock'];
    }

    private function row(string $sql, int $param): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$param]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
