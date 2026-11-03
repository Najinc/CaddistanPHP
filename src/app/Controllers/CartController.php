<?php

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
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
        $v = $this->validator();
        $productId = $v->int('product_id', 'Le produit', 1, PHP_INT_MAX);
        $quantity  = $v->int('quantity', 'La quantité', 1, Cart::MAX_QUANTITY, required: false) ?? 1;
        $v->validate();

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
        // Ici un tableau est attendu (quantities[ID_PRODUIT]=QUANTITÉ) : on le demande explicitement
        $quantities = $this->request->inputArray('quantities');
        $v = new Validator($quantities);
        $clean = [];

        foreach (array_keys($quantities) as $productId) {
            $id = filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw HttpException::badRequest('Identifiant de produit invalide.');
            }
            $clean[$id] = $v->int((string) $productId, 'La quantité', 0, Cart::MAX_QUANTITY);
        }
        $v->validate();

        foreach ($clean as $productId => $quantity) {
            Cart::update($productId, $quantity);
        }

        Session::flash('success', 'Panier mis à jour.');

        return $this->redirect('/panier');
    }

    public function remove(): Response
    {
        $v = $this->validator();
        $productId = $v->int('product_id', 'Le produit', 1, PHP_INT_MAX);
        $v->validate();

        Cart::remove($productId);
        Session::flash('success', 'Article retiré du panier.');

        return $this->redirect('/panier');
    }
}
