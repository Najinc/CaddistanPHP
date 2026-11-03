<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;

final class UserController extends Controller
{
    private const ROLES = ['customer', 'admin'];

    public function index(): Response
    {
        return $this->view('admin/users/index', [
            'title' => 'Utilisateurs',
            'users' => (new UserRepository())->all(),
            'roles' => self::ROLES,
        ]);
    }

    public function updateRole(string $id): Response
    {
        $users = new UserRepository();
        $user = $users->find($id) ?? throw HttpException::notFound('Utilisateur introuvable.');
        $role = $this->request->string('role');

        $users->updateRole($user['id'], $role);
        Session::flash('success', 'Rôle de ' . $user['email'] . ' mis à jour.');

        return $this->redirect('/admin/utilisateurs');
    }
}
