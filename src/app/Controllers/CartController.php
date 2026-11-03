<?php

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ProductRepository;
use App\Services\Cart;

final class CartController extends Controller
{
    public function index(): Response
    {
        return $this->view('cart/index', ['title' => 'Mon panier', ...Cart::details()]);
    }

    public function add(): Response
    {
        $productId = $this->request->input('product_id');
        $quantity  = (int) $this->request->input('quantity', 1);

        $product = (new ProductRepository())->find($productId);
        if ($product === null || !$product['is_active']) {
            throw HttpException::notFound('Produit introuvable.');
        }

        Cart::add($productId, $quantity);

        if ($this->request->wantsJson()) {
            return $this->json(['count' => Cart::count(), 'message' => $product['name'] . ' ajouté au panier.']);
        }

        Session::flash('success', '« ' . $product['name'] . ' » a été ajouté au panier.');

        return $this->redirect('/panier');
    }

    public function update(): Response
    {
        $quantities = $this->request->input('quantities', []);

        if (is_array($quantities)) {
            foreach ($quantities as $productId => $quantity) {
                Cart::update((int) $productId, (int) $quantity);
            }
        }

        Session::flash('success', 'Panier mis à jour.');

        return $this->redirect('/panier');
    }

    public function remove(): Response
    {
        Cart::remove((int) $this->request->input('product_id'));
        Session::flash('success', 'Article retiré du panier.');

        return $this->redirect('/panier');
    }
}
