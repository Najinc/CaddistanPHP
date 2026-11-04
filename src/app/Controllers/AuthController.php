<?php

namespace App\Controllers;

use App\Auth\Authenticator;
use App\Auth\SafeRedirect;
use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
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
        $v = $this->validator();
        $email    = $v->email('email', "L'e-mail");
        $password = $v->password('password', 'Le mot de passe', 1, 72);
        $v->validate();

        if (!Auth::attempt($email, $password)) {
            Session::flashInput(['email' => $email]);
            // Même message que l'e-mail existe ou non : on ne révèle pas quels comptes existent.
            Session::flash('error', Authenticator::FAILURE_MESSAGE);
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

        $v = $this->validator();
        $data = [
            'first_name' => $v->string('first_name', 'Le prénom', 1, 100, Validator::REGEX_NAME),
            'last_name'  => $v->string('last_name', 'Le nom', 1, 100, Validator::REGEX_NAME),
            'email'      => $v->email('email', "L'e-mail"),
        ];
        $password = $v->password('password', 'Le mot de passe', 8, 72);
        if ($password !== $this->request->input('password_confirmation')) {
            $v->addError('password_confirmation', 'Les mots de passe ne correspondent pas.');
        }
        $v->validate();

        if ($users->findByEmail($data['email']) !== null) {
            Session::flashInput($data);
            Session::flash('error', 'Un compte existe déjà avec cette adresse.');
            return $this->redirect('/inscription');
        }

        $id = $users->create($data['email'], Auth::hashPassword($password), $data['first_name'], $data['last_name']);
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

    /** Redirige vers la page demandée avant la connexion, uniquement si c'est une page du site (anti open redirect). */
    private function redirectIntended(): Response
    {
        $target = SafeRedirect::target(Session::get('intended'), '/compte');
        Session::forget('intended');

        return $this->redirect($target);
    }
}
