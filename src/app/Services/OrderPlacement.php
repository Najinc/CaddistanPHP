<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Services\Payment\PaymentDeclinedException;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SimulatedPaymentGateway;
use PDO;
use Throwable;

/**
 * Passage de commande : TOUT passe (commande + lignes + stock + paiement), ou RIEN n'est enregistré.
 *
 * 1. BEGIN
 * 2. SELECT … FOR UPDATE sur les produits du panier : les lignes sont verrouillées jusqu'au COMMIT,
 *    deux clients ne peuvent pas acheter le dernier caddy en même temps, et le prix ne peut pas changer
 *    entre le moment où on le lit et celui où on l'enregistre.
 * 3. Prix et total RECALCULÉS ici depuis la base : le panier (session) ne contient que des
 *    identifiants et des quantités, et rien de ce qu'envoie le navigateur n'entre dans le calcul.
 * 4. INSERT commande (pending) + lignes, décrément du stock.
 * 5. Paiement. S'il est refusé => ROLLBACK : pas de commande, pas de ligne, stock intact.
 * 6. Commande « paid », COMMIT.
 *
 * Dans un vrai site, le paiement passerait par un prestataire externe : on évite alors de garder les
 * verrous pendant l'appel réseau (commande « pending » validée, puis paiement, puis mise à jour du statut
 * ou remboursement). Ici le paiement est simulé, donc il tient dans la transaction.
 */
final class OrderPlacement
{
    /** total_cents est un INT UNSIGNED en base. */
    private const MAX_TOTAL_CENTS = 4_294_967_295;

    public function __construct(
        private readonly PaymentGateway $payments = new SimulatedPaymentGateway(),
        private readonly ?PDO $db = null,
    ) {}

    /**
     * @param array{name: string, address: string, zip: string, city: string, phone: string} $shipping
     * @param array<int, int> $cart [id produit => quantité], tel que stocké en session (Cart::raw())
     * @return int id de la commande créée
     * @throws OrderException message affichable à l'utilisateur (stock, produit retiré, paiement refusé)
     */
    public function place(int $userId, array $shipping, array $cart): int
    {
        if ($cart === []) {
            throw new OrderException('Votre panier est vide.');
        }

        $db = $this->db ?? Database::connection();
        $db->beginTransaction();

        try {
            $products = $this->lockProducts($db, array_map('intval', array_keys($cart)));
            [$lines, $total] = $this->price($cart, $products);

            $orderId = $this->insertOrder($db, $userId, $total, $shipping);

            foreach ($lines as $line) {
                $this->insertItem($db, $orderId, $line);
                $this->decrementStock($db, $line);
            }

            $this->payments->charge($orderId, $total);

            $db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$orderId]);
            $db->commit();

            return $orderId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ($e instanceof PaymentDeclinedException) {
                Logger::security('payment.declined', ['reason' => $e->getMessage(), 'amount_cents' => $total ?? null], 'info');
            }

            throw $e;
        }
    }

    /** @return array<int, array> produits indexés par id, verrouillés jusqu'à la fin de la transaction */
    private function lockProducts(PDO $db, array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // ORDER BY id : deux transactions verrouillent toujours les lignes dans le même ordre (pas d'interblocage).
        $stmt = $db->prepare(
            "SELECT id, name, price_cents, stock, is_active FROM products
             WHERE id IN ($placeholders) ORDER BY id FOR UPDATE"
        );
        $stmt->execute($ids);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), null, 'id');
    }

    /** @return array{0: list<array{product: array, quantity: int, unit_price: int}>, 1: int} */
    private function price(array $cart, array $products): array
    {
        $lines = [];
        $total = 0;

        foreach ($cart as $id => $quantity) {
            $product = $products[(int) $id] ?? null;

            if ($product === null || !$product['is_active']) {
                throw new OrderException('Un article de votre panier n\'est plus disponible. Vérifiez votre panier.');
            }

            // Défense en profondeur : la session est côté serveur, mais on ne fait confiance à rien.
            if (!is_int($quantity) || $quantity < 1 || $quantity > Cart::MAX_QUANTITY) {
                throw new OrderException('Quantité invalide dans votre panier.');
            }

            if ((int) $product['stock'] < $quantity) {
                throw new OrderException(sprintf(
                    'Stock insuffisant pour « %s » (%d disponible(s)).',
                    $product['name'],
                    $product['stock'],
                ));
            }

            $unitPrice = (int) $product['price_cents'];   // prix de la base, au moment du verrou
            $total += $unitPrice * $quantity;
            $lines[] = ['product' => $product, 'quantity' => $quantity, 'unit_price' => $unitPrice];
        }

        if ($total > self::MAX_TOTAL_CENTS) {
            throw new OrderException('Montant de commande trop élevé.');
        }

        return [$lines, $total];
    }

    private function insertOrder(PDO $db, int $userId, int $total, array $shipping): int
    {
        $db->prepare(
            "INSERT INTO orders (user_id, status, total_cents, shipping_name, shipping_address, shipping_zip, shipping_city, shipping_phone)
             VALUES (?, 'pending', ?, ?, ?, ?, ?, ?)"
        )->execute([
            $userId, $total,
            $shipping['name'], $shipping['address'], $shipping['zip'], $shipping['city'], $shipping['phone'],
        ]);

        return (int) $db->lastInsertId();
    }

    private function insertItem(PDO $db, int $orderId, array $line): void
    {
        $db->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, unit_price_cents, quantity) VALUES (?, ?, ?, ?, ?)'
        )->execute([$orderId, (int) $line['product']['id'], $line['product']['name'], $line['unit_price'], $line['quantity']]);
    }

    private function decrementStock(PDO $db, array $line): void
    {
        $stmt = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        $stmt->execute([$line['quantity'], (int) $line['product']['id'], $line['quantity']]);

        // Ne devrait jamais arriver grâce au verrou, mais si c'est le cas on annule tout plutôt que de vendre à découvert.
        if ($stmt->rowCount() !== 1) {
            throw new OrderException('Stock insuffisant pour « ' . $line['product']['name'] . ' ».');
        }
    }
}
