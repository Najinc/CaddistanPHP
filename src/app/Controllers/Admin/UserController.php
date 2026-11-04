<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;

final class UserController extends Controller
{
    private const ROLES = ['customer', 'admin'];

    public function index(): Response
    {
        $this->authorize('user.view_any');

        return $this->view('admin/users/index', [
            'title' => 'Utilisateurs',
            'users' => (new UserRepository())->all(),
            'roles' => self::ROLES,
        ]);
    }

    public function updateRole(string $id): Response
    {
        $this->authorize('user.change_role');
        $users = new UserRepository();
        $user = $users->find($this->routeId($id)) ?? throw HttpException::notFound('Utilisateur introuvable.');

        // Évite qu'un admin se retire lui-même ses droits.
        if ((int) $user['id'] === Auth::id()) {
            return $this->back('/admin/utilisateurs', 'Vous ne pouvez pas modifier votre propre rôle.');
        }

        $v = $this->validator();
        $role = $v->in('role', 'Le rôle', self::ROLES);
        $v->validate();

        $users->updateRole($user['id'], $role);
        Session::flash('success', 'Rôle de ' . $user['email'] . ' mis à jour.');

        return $this->redirect('/admin/utilisateurs');
    }
}
