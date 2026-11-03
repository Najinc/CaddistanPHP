<?php

declare(strict_types=1);

namespace App\Auth\Session;

/**
 * Cookies sécurisés par défaut, pour la session ET pour tout autre cookie de l'application.
 *
 * - HttpOnly : inaccessible au JavaScript, donc une XSS ne peut pas voler le cookie.
 * - Secure   : envoyé uniquement en HTTPS.
 * - SameSite=Lax : pas envoyé sur les POST venant d'un autre site (défense CSRF en plus du token).
 * - Préfixe __Host- : le navigateur refuse le cookie s'il n'est pas Secure, s'il a un
 *   attribut Domain ou si Path n'est pas "/". Un sous-domaine ne peut donc pas l'écraser.
 */
final class CookiePolicy
{
    private const ALLOWED_SAME_SITE = ['Lax', 'Strict'];

    /**
     * @param bool $secure false uniquement pour un serveur local sans HTTPS.
     *                     Avec Docker et Caddy, le site est toujours en HTTPS : garder true.
     */
    public function __construct(
        public readonly bool $secure = true,
        public readonly string $sameSite = 'Lax',
    ) {
        if (!in_array($sameSite, self::ALLOWED_SAME_SITE, true)) {
            // SameSite=None renverrait le cookie sur les requêtes cross-site : on l'interdit.
            throw new \InvalidArgumentException('SameSite doit valoir Lax ou Strict.');
        }
    }

    public function name(string $name): string
    {
        return $this->secure ? '__Host-' . $name : $name;
    }

    /**
     * Options au format de setcookie(). HttpOnly ne se désactive qu'explicitement.
     *
     * @return array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    public function options(int $expires = 0, bool $httpOnly = true): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'domain' => '', // pas d'attribut Domain : obligatoire avec __Host-
            'secure' => $this->secure,
            'httponly' => $httpOnly,
            'samesite' => $this->sameSite,
        ];
    }

    public function set(string $name, string $value, int $expires = 0, bool $httpOnly = true): bool
    {
        return setcookie($this->name($name), $value, $this->options($expires, $httpOnly));
    }

    /** Supprime le cookie : mêmes attributs, date d'expiration dans le passé. */
    public function forget(string $name): bool
    {
        return setcookie($this->name($name), '', $this->options(1));
    }

    /** @param array<string, mixed> $cookies typiquement $_COOKIE */
    public function read(array $cookies, string $name): ?string
    {
        $value = $cookies[$this->name($name)] ?? null;

        return is_string($value) ? $value : null;
    }
}
