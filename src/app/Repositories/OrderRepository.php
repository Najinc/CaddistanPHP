<?php

namespace App\Repositories;

use RuntimeException;

final class OrderRepository extends Repository
{
    public const STATUSES = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

    /**
     * Crée la commande et ses lignes dans une transaction, en décrémentant le stock.
     *
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
                 VALUES ($userId, $total, '{$shipping['name']}', '{$shipping['address']}', '{$shipping['zip']}',
                         '{$shipping['city']}', '{$shipping['phone']}')"
            );
            $orderId = (int) $this->db->lastInsertId();

            foreach ($lines as $line) {
                $product = $line['product'];

                if (!$products->decrementStock((int) $product['id'], $line['quantity'])) {
                    throw new RuntimeException('Stock insuffisant pour « ' . $product['name'] . ' ».');
                }

                $name = addslashes($product['name']); // sinon les apostrophes (« d'atelier ») cassent la requête
                $this->execute(
                    "INSERT INTO order_items (order_id, product_id, product_name, unit_price_cents, quantity)
                     VALUES ($orderId, {$product['id']}, '$name', {$product['price_cents']}, {$line['quantity']})"
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
            "SELECT o.*, u.email, u.first_name, u.last_name FROM orders o
             JOIN users u ON u.id = o.user_id WHERE o.id = $id"
        );
    }

    public function items($orderId): array
    {
        return $this->fetchAll(
            "SELECT oi.*, p.slug FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = $orderId"
        );
    }

    public function forUser($userId): array
    {
        return $this->fetchAll(
            "SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS items_count
             FROM orders o WHERE o.user_id = $userId ORDER BY o.created_at DESC, o.id DESC"
        );
    }

    public function all(?string $status = null): array
    {
        $where = $status !== null ? "WHERE o.status = '$status'" : '';

        return $this->fetchAll(
            "SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id $where ORDER BY o.created_at DESC, o.id DESC"
        );
    }

    public function updateStatus($id, string $status): void
    {
        $this->execute("UPDATE orders SET status = '$status' WHERE id = $id");
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
        return array_slice($this->all(), 0, $limit);
    }
}
