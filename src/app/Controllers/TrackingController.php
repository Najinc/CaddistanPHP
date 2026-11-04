<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Response;
use App\Repositories\OrderRepository;

/** Suivi de commande par lien signé : pas besoin de compte, le lien expire au bout de 7 jours. */
final class TrackingController extends Controller
{
    private const TTL = 7 * 24 * 3600;

    public static function linkFor(int $orderId): string
    {
        return rtrim((string) env('APP_URL'), '/') . Auth::signer()->sign('/suivi/commandes/' . $orderId, self::TTL);
    }

    public function show(string $id): Response
    {
        // L'id et l'expiration font partie de l'URL signée : s'ils sont modifiés, 403.
        Auth::signer()->verify((string) ($_SERVER['REQUEST_URI'] ?? ''));

        $orders = new OrderRepository();
        $order = $orders->find($this->routeId($id)) ?? throw HttpException::notFound('Commande introuvable.');

        // La signature est dans l'URL : on évite de l'envoyer à d'autres sites via le Referer.
        return $this->view('tracking/show', [
            'title' => 'Suivi de la commande n°' . $order['id'],
            'order' => $order,
            'items' => $orders->items($order['id']),
        ])->withHeader('Referrer-Policy', 'no-referrer');
    }
}
