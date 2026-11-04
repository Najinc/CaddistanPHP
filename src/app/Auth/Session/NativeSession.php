<?php

declare(strict_types=1);

namespace App\Auth\Session;

/** Session PHP configurée dans le code, sans dépendre du php.ini. */
final class NativeSession implements SessionInterface
{
    private const LAST_ACTIVITY = 'last_activity';

    public function __construct(
        private readonly bool $secure = true,       // false seulement en local sans HTTPS
        private readonly int $idleTimeout = 1800,   // en secondes
        private readonly ?\Closure $clock = null,   // horloge remplaçable dans les tests
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $this->open();

        // Trop longtemps inactif : on repart sur une session vierge.
        $lastActivity = $_SESSION[self::LAST_ACTIVITY] ?? null;
        if (is_int($lastActivity) && $this->now() - $lastActivity > $this->idleTimeout) {
            $this->destroy();
            $this->open();
        }

        $_SESSION[self::LAST_ACTIVITY] = $this->now();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true); // true : supprime l'ancienne session sur le serveur
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        session_destroy();

        // Mêmes attributs que le cookie d'origine, sinon le navigateur ne le supprime pas.
        setcookie(session_name(), '', ['expires' => 1] + $this->cookieOptions());
    }

    private function open(): void
    {
        // __Host- : le navigateur refuse le cookie s'il n'est pas Secure ou s'il vise un sous-domaine.
        session_name(($this->secure ? '__Host-' : '') . 'caddistan_session');
        session_set_cookie_params(['lifetime' => 0] + $this->cookieOptions());
        session_start([
            'use_strict_mode' => true, // refuse un ID de session inventé par un attaquant
            'gc_maxlifetime' => $this->idleTimeout,
        ]);
    }

    // HttpOnly : illisible en JS. Secure : HTTPS seulement. Lax : pas envoyé sur un POST venant d'un autre site.
    private function cookieOptions(): array
    {
        return ['path' => '/', 'secure' => $this->secure, 'httponly' => true, 'samesite' => 'Lax'];
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
