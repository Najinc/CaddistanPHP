<?php

namespace App\Services;

use App\Core\Session;
use App\Repositories\ProductRepository;

/**
 * Panier stocké en session : [product_id => quantité].
 */
final class Cart
{
    private const KEY = 'cart';
    public const MAX_QUANTITY = 99;

    public static function add(int $productId, int $quantity = 1): void
    {
        $cart = self::raw();
        $cart[$productId] = min(self::MAX_QUANTITY, ($cart[$productId] ?? 0) + $quantity);
        Session::set(self::KEY, $cart);
    }

    public static function update(int $productId, int $quantity): void
    {
        $cart = self::raw();

        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = min(self::MAX_QUANTITY, $quantity);
        }

        Session::set(self::KEY, $cart);
    }

    public static function remove(int $productId): void
    {
        self::update($productId, 0);
    }

    public static function clear(): void
    {
        Session::forget(self::KEY);
    }

    public static function count(): int
    {
        return array_sum(self::raw());
    }

    /** @return array<int, int> */
    public static function raw(): array
    {
        return Session::get(self::KEY, []);
    }

    /**
     * Lignes du panier enrichies avec les produits (les produits supprimés/désactivés sont ignorés).
     *
     * @return array{lines: array, total: int}
     */
    public static function details(): array
    {
        $raw = self::raw();
        $products = (new ProductRepository())->findMany(array_keys($raw));
        $lines = [];
        $total = 0;

        foreach ($raw as $id => $quantity) {
            if (!isset($products[$id])) {
                continue;
            }

            $subtotal = $products[$id]['price_cents'] * $quantity;
            $total += $subtotal;
            $lines[] = ['product' => $products[$id], 'quantity' => $quantity, 'subtotal' => $subtotal];
        }

        return ['lines' => $lines, 'total' => $total];
    }
}
