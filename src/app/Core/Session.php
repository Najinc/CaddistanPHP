<?php

namespace App\Core;

/**
 * Accès à la session PHP + messages flash.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Récupère et vide les messages flash. */
    public static function pullFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $flashes;
    }

    /** Anciennes valeurs de formulaire (pour ré-afficher après une erreur). */
    public static function flashInput(array $input): void
    {
        $_SESSION['_old'] = $input;
    }

    public static function old(string $key, string $default = ''): string
    {
        $value = $_SESSION['_old'][$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public static function clearOldInput(): void
    {
        unset($_SESSION['_old']);
    }
}
