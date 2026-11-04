<?php

declare(strict_types=1);

namespace App\Auth\Session;

/** Session PHP sécurisée. Tout est réglé ici, on ne dépend pas du php.ini. */
final class NativeSession implements SessionInterface
{
    private const LAST_ACTIVITY = 'last_activity';

    /**
     * @param bool          $secure      false seulement en local sans HTTPS
     * @param int           $idleTimeout secondes d'inactivité avant la déconnexion
     * @param \Closure|null $clock       horloge remplaçable dans les tests
     */
    public function __construct(
        private readonly bool $secure = true,
        private readonly int $idleTimeout = 1800,
        private readonly ?\Closure $clock = null,
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $this->open();

        // Inactif trop longtemps : on détruit la session et on repart sur une session vierge.
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

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true); // true : l'ancienne session est supprimée sur le serveur
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        session_destroy();

        // Supprime le cookie : mêmes attributs et date passée, sinon le navigateur l'ignore.
        setcookie(session_name(), '', ['expires' => 1] + $this->cookieOptions());
    }

    private function open(): void
    {
        // Préfixe __Host- : le navigateur refuse le cookie s'il n'est pas Secure ou s'il vise un sous-domaine.
        session_name(($this->secure ? '__Host-' : '') . 'caddistan_session');
        session_set_cookie_params(['lifetime' => 0] + $this->cookieOptions());
        session_start([
            'use_strict_mode' => true, // refuse un ID de session inventé par un attaquant
            'gc_maxlifetime' => $this->idleTimeout,
        ]);
    }

    /** HttpOnly : illisible en JavaScript. Secure : HTTPS uniquement. SameSite=Lax : pas envoyé sur un POST venant d'un autre site. */
    private function cookieOptions(): array
    {
        return ['path' => '/', 'secure' => $this->secure, 'httponly' => true, 'samesite' => 'Lax'];
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
