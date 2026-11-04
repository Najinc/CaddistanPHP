<?php

declare(strict_types=1);

namespace App\Auth\Signing;

use App\Auth\Exception\InvalidSignatureException;

/**
 * URL signées : un lien qui donne accès à une page précise, sans session, pendant un temps limité.
 *
 *   "/commandes/12/facture"  →  "/commandes/12/facture?expires=1767225600&signature=3f9a…"
 *
 * La signature est un HMAC-SHA256 de tout ce qui la précède, calculé avec APP_KEY.
 * Changer un seul caractère (l'id, la date…) rend la signature fausse.
 */
final class UrlSigner
{
    /** @param \Closure|null $clock horloge remplaçable dans les tests */
    public function __construct(
        #[\SensitiveParameter] private readonly string $key,
        private readonly ?\Closure $clock = null,
    ) {
        if (strlen($key) < 32) {
            throw new \InvalidArgumentException('APP_KEY trop courte : il faut au moins 32 octets aléatoires.');
        }
    }

    /** $url : chemin local déjà encodé, avec ou sans paramètres. */
    public function sign(string $url, int $ttlSeconds): string
    {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'expires=' . ($this->now() + $ttlSeconds);

        return $url . '&signature=' . $this->hmac($url);
    }

    /**
     * Vérifie l'URL reçue (typiquement $_SERVER['REQUEST_URI']) et renvoie ses paramètres.
     *
     * @return array<string, string>
     */
    public function verify(string $url): array
    {
        // La signature est toujours à la fin : tout ce qui est avant est signé.
        $parts = explode('&signature=', $url, 2);
        if (count($parts) !== 2) {
            throw new InvalidSignatureException('missing');
        }
        [$signedPart, $signature] = $parts;

        // hash_equals compare en temps constant : on ne peut pas deviner la signature caractère par caractère.
        if (!hash_equals($this->hmac($signedPart), $signature)) {
            throw new InvalidSignatureException('invalid');
        }

        parse_str((string) parse_url($signedPart, PHP_URL_QUERY), $params);
        if ((int) ($params['expires'] ?? 0) <= $this->now()) {
            throw new InvalidSignatureException('expired');
        }

        return $params;
    }

    private function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, $this->key);
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
