<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Response;
use App\Repositories\OrderRepository;

/**
 * Suivi de commande par lien signé (Partie 3).
 *
 * Pas besoin de compte pour suivre la commande, mais le lien expire au bout de 7 jours,
 * et toute modification (id, date d'expiration…) rend la signature invalide : 403.
 */
final class TrackingController extends Controller
{
    private const TTL = 7 * 24 * 3600;

    /** Lien absolu à partager : APP_URL + chemin signé. */
    public static function linkFor(int $orderId): string
    {
        return rtrim((string) env('APP_URL'), '/') . Auth::signer()->sign('/suivi/commandes/' . $orderId, self::TTL);
    }

    public function show(string $id): Response
    {
        // Vérifié sur l'URL brute reçue : le chemin (donc l'id) et l'expiration sont signés.
        Auth::signer()->verify((string) ($_SERVER['REQUEST_URI'] ?? ''));

        $orders = new OrderRepository();
        $order = $orders->find($this->routeId($id)) ?? throw HttpException::notFound('Commande introuvable.');

        // La signature est dans l'URL : on ne la transmet pas aux autres sites via l'en-tête Referer.
        return $this->view('tracking/show', [
            'title' => 'Suivi de la commande n°' . $order['id'],
            'order' => $order,
            'items' => $orders->items($order['id']),
        ])->withHeader('Referrer-Policy', 'no-referrer');
    }
}
