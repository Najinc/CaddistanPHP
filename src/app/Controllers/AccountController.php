<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;

final class AccountController extends Controller
{
    public function index(): Response
    {
        $this->requireLogin();

        return $this->view('account/index', [
            'title'  => 'Mon compte',
            'user'   => Auth::user(),
            'orders' => (new OrderRepository())->forUser(Auth::id()),
        ]);
    }

    public function update(): Response
    {
        $this->requireLogin();
        $users = new UserRepository();

        $v = $this->validator();
        $firstName = $v->string('first_name', 'Le prénom', 1, 100, Validator::REGEX_NAME);
        $lastName  = $v->string('last_name', 'Le nom', 1, 100, Validator::REGEX_NAME);
        $password  = $v->password('password', 'Le nouveau mot de passe', 8, 72, required: false);
        $current   = $v->password('current_password', 'Le mot de passe actuel', 1, 72, required: $password !== null);
        $v->validate();

        // Vérifié avant d'enregistrer quoi que ce soit.
        if ($password !== null && !Auth::checkPassword($current)) {
            return $this->back('/compte', 'Mot de passe actuel incorrect.');
        }

        $users->updateProfile(Auth::id(), $firstName, $lastName);

        if ($password !== null) {
            $users->updatePassword(Auth::id(), Auth::hashPassword($password));
            // Une session volée avant le changement ne sert plus.
            Auth::renewSession();
        }

        Session::flash('success', 'Profil mis à jour.');

        return $this->redirect('/compte');
    }

    public function order(string $id): Response
    {
        $this->authorize('order.view');
        $orders = new OrderRepository();
        $order = $orders->find($this->routeId($id)) ?? throw HttpException::notFound('Commande introuvable.');
        // Commande d'un autre client : 404.
        Auth::authorizeOwner($order);

        return $this->view('account/order', [
            'title'       => 'Commande n°' . $order['id'],
            'order'       => $order,
            'items'       => $orders->items($order['id']),
            'trackingUrl' => TrackingController::linkFor((int) $order['id']),
        ]);
    }
}
