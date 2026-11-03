<?php

namespace App\Core;

use App\Repositories\UserRepository;

/**
 * Gestion de l'utilisateur connecté.
 */
final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): bool
    {
        $user = (new UserRepository())->findByCredentials($email, $password);

        if ($user === null) {
            return false;
        }

        self::login($user);

        return true;
    }

    public static function login(array $user): void
    {
        Session::set('user_id', (int) $user['id']);
        self::$user = $user;
    }

    public static function logout(): void
    {
        self::$user = null;
        Session::destroy();
    }

    public static function user(): ?array
    {
        if (self::$user === null && ($id = Session::get('user_id')) !== null) {
            self::$user = (new UserRepository())->find($id);
        }

        return self::$user;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int) self::user()['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }
}
