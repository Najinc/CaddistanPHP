<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\OrderRepository;
use App\Services\Cart;
use App\Services\OrderException;
use App\Services\OrderPlacement;

final class CheckoutController extends Controller
{
    public function index(): Response
    {
        $this->authorize('order.create');
        $cart = Cart::details();

        if ($cart['lines'] === []) {
            Session::flash('info', 'Votre panier est vide.');
            return $this->redirect('/panier');
        }

        return $this->view('checkout/index', ['title' => 'Commande', 'user' => Auth::user(), ...$cart]);
    }

    public function store(): Response
    {
        $this->authorize('order.create');
        $cart = Cart::details();

        if ($cart['lines'] === []) {
            return $this->redirect('/panier');
        }

        $v = $this->validator();
        $shipping = [
            'name'    => $v->string('name', 'Le nom', 2, 200, Validator::REGEX_NAME),
            'address' => $v->string('address', "L'adresse", 5, 255),
            'zip'     => $v->string('zip', 'Le code postal', 5, 5, '/^[0-9]{5}$/'),
            'city'    => $v->string('city', 'La ville', 1, 100, Validator::REGEX_NAME),
            'phone'   => $v->string('phone', 'Le téléphone', 10, 20, '/^\+?[0-9 .]+$/'),
        ];
        $v->validate();

        // Seuls les identifiants et quantités du panier sont transmis : prix et total sont recalculés
        // depuis la base, dans la transaction. On n'attrape que les erreurs métier (OrderException) :
        // une PDOException (qui hérite aussi de RuntimeException) afficherait du SQL au client.
        try {
            $orderId = (new OrderPlacement())->place(Auth::id(), $shipping, Cart::raw());
        } catch (OrderException $e) {
            return $this->back('/commande', $e->getMessage());
        }

        Cart::clear();
        Session::flash('success', 'Commande validée, merci !');

        return $this->redirect('/commande/confirmation/' . $orderId);
    }

    public function confirmation(string $id): Response
    {
        $this->authorize('order.view');
        $orders = new OrderRepository();
        $order = $orders->find($this->routeId($id)) ?? throw HttpException::notFound();
        // Commande d'un autre client : 404.
        Auth::authorizeOwner($order);

        return $this->view('checkout/confirmation', [
            'title' => 'Commande confirmée',
            'order' => $order,
            'items' => $orders->items($order['id']),
        ]);
    }
}
