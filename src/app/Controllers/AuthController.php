<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        if (Auth::check()) {
            return $this->redirect('/compte');
        }

        return $this->view('auth/login', ['title' => 'Connexion']);
    }

    public function login(): Response
    {
        $email = $this->request->string('email');
        $password = (string) $this->request->input('password', '');

        if (!Auth::attempt($email, $password)) {
            Session::flashInput(['email' => $email]);
            Session::flash('error', 'Identifiants incorrects.');
            return $this->redirect('/connexion');
        }

        Session::flash('success', 'Bon retour parmi nous, ' . Auth::user()['first_name'] . ' !');

        return $this->redirectIntended();
    }

    public function showRegister(): Response
    {
        if (Auth::check()) {
            return $this->redirect('/compte');
        }

        return $this->view('auth/register', ['title' => 'Créer un compte']);
    }

    public function register(): Response
    {
        $users = new UserRepository();
        $data = [
            'first_name' => $this->request->string('first_name'),
            'last_name'  => $this->request->string('last_name'),
            'email'      => $this->request->string('email'),
        ];
        $password = (string) $this->request->input('password', '');
        $confirmation = (string) $this->request->input('password_confirmation', '');

        $error = match (true) {
            $password !== $confirmation                  => 'Les mots de passe ne correspondent pas.',
            $users->findByEmail($data['email']) !== null => 'Un compte existe déjà avec cette adresse.',
            default                                      => null,
        };

        if ($error !== null) {
            Session::flashInput($data);
            Session::flash('error', $error);
            return $this->redirect('/inscription');
        }

        $id = $users->create($data['email'], $password, $data['first_name'], $data['last_name']);
        Auth::login($users->find($id));
        Session::flash('success', 'Bienvenue chez Caddistan, ' . $data['first_name'] . ' !');

        return $this->redirectIntended();
    }

    public function logout(): Response
    {
        Auth::logout();
        Session::start();
        Session::flash('success', 'Vous êtes déconnecté.');

        return $this->redirect('/');
    }

    /** Redirige vers la page demandée avant la connexion. */
    private function redirectIntended(): Response
    {
        $target = Session::get('intended', '/compte');
        Session::forget('intended');

        return $this->redirect($target);
    }
}
