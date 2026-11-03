<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Response;
use App\Repositories\ProductRepository;
use App\Services\Cart;

/**
 * Endpoints JSON utilisés par le front (recherche instantanée, compteur du panier).
 */
final class ProductApiController extends Controller
{
    public function search(): Response
    {
        $q = trim((string) $this->request->query('q', ''));

        if ($q === '') {
            return $this->json(['data' => []]);
        }

        $result = (new ProductRepository())->search($q, null, 'nom', 1, 6);

        return $this->json([
            'data' => array_map(fn (array $p) => [
                'name'     => $p['name'],
                'slug'     => $p['slug'],
                'price'    => money((int) $p['price_cents']),
                'image'    => $p['image'],
                'category' => $p['category_name'],
                'url'      => '/produits/' . $p['slug'],
            ], $result['items']),
            'total' => $result['total'],
        ]);
    }

    public function cart(): Response
    {
        $details = Cart::details();

        return $this->json([
            'count' => Cart::count(),
            'total' => money($details['total']),
        ]);
    }
}
