<?php

namespace App\Repositories;

use InvalidArgumentException;
use RuntimeException;

final class OrderRepository extends Repository
{
    public const STATUSES = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

    /**
     * Crée la commande et ses lignes dans une transaction, en décrémentant le stock.
     * 
     * @param int|string $userId
     * @param array $shipping name, address, zip, city, phone
     * @param array $lines    [['product' => array, 'quantity' => int], ...]
     */
    public function create($userId, array $shipping, array $lines): int
    {
        $products = new ProductRepository();
        $total = array_sum(array_map(fn ($l) => $l['product']['price_cents'] * $l['quantity'], $lines));

        $this->db->beginTransaction();

        try {
            $this->execute(
                "INSERT INTO orders (user_id, total_cents, shipping_name, shipping_address, shipping_zip, shipping_city, shipping_phone)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $total,
                    $shipping['name'],
                    $shipping['address'],
                    $shipping['zip'],
                    $shipping['city'],
                    $shipping['phone']
                ]
            );
            $orderId = (int) $this->lastInsertId();

            foreach ($lines as $line) {
                $product = $line['product'];

                if (!$products->decrementStock((int) $product['id'], $line['quantity'])) {
                    throw new RuntimeException('Stock insuffisant pour « ' . $product['name'] . ' ».');
                }

                $this->execute(
                    "INSERT INTO order_items (order_id, product_id, product_name, unit_price_cents, quantity)
                     VALUES (?, ?, ?, ?, ?)",
                    [
                        $orderId,
                        (int) $product['id'],
                        $product['name'],
                        (int) $product['price_cents'],
                        (int) $line['quantity']
                    ]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $orderId;
    }

    public function find($id): ?array
    {
        return $this->fetchOne(
            "SELECT o.id, o.status, o.created_at, o.total_cents, o.shipping_name, o.shipping_address, o.shipping_zip, o.shipping_city, o.shipping_phone, u.email, u.first_name, u.last_name FROM orders o
             JOIN users u ON u.id = o.user_id WHERE o.id = ?",
            [$id]
        );
    }

    public function items($orderId): array
    {
        return $this->fetchAll(
            "SELECT oi.product_name, oi.unit_price_cents, oi.quantity, p.slug FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?",
            [$orderId]
        );
    }

    public function forUser($userId): array
    {
        return $this->fetchAll(
            "SELECT o.id, o.status, o.created_at, o.total_cents, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS items_count
             FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC",
            [$userId]
        );
    }

    public function all(?string $status = null): array
    {
        $sql = "SELECT o.id, o.status, o.created_at, o.total_cents, u.email FROM orders o JOIN users u ON u.id = o.user_id";
        $params = [];

        if ($status !== null) {
            if (!in_array($status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Statut de commande invalide.');
            }
            $sql .= " WHERE o.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY o.created_at DESC, o.id DESC";

        return $this->fetchAll($sql, $params);
    }

    public function updateStatus($id, string $status): void
    {
        $this->execute(
            "UPDATE orders SET status = ? WHERE id = ?",
            [$status, $id]
        );
    }

    /** @return array{count: int, revenue: int} */
    public function stats(): array
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS count, COALESCE(SUM(total_cents), 0) AS revenue FROM orders WHERE status <> 'cancelled'"
        );

        return ['count' => (int) $row['count'], 'revenue' => (int) $row['revenue']];
    }

    public function latest(int $limit = 5): array
    {
        $limit = (int) $limit;
        
        return $this->fetchAll(
            "SELECT o.id, o.total_cents, o.status, u.email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC, o.id DESC LIMIT $limit"
        );
    }
}
