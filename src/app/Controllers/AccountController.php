<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
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

        $firstName = $this->request->string('first_name');
        $lastName  = $this->request->string('last_name');
        $current   = (string) $this->request->input('current_password', '');
        $password  = (string) $this->request->input('password', '');

        $users->updateProfile(Auth::id(), $firstName, $lastName);

        if ($password !== '') {
            if ($current !== Auth::user()['password']) {
                return $this->back('/compte', 'Mot de passe actuel incorrect.');
            }
            $users->updatePassword(Auth::id(), $password);
        }

        Session::flash('success', 'Profil mis à jour.');

        return $this->redirect('/compte');
    }

    public function order(string $id): Response
    {
        $this->requireLogin();
        $orders = new OrderRepository();
        $order = $orders->find($id) ?? throw HttpException::notFound('Commande introuvable.');

        return $this->view('account/order', [
            'title' => 'Commande n°' . $order['id'],
            'order' => $order,
            'items' => $orders->items($order['id']),
        ]);
    }
}
