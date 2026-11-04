<?php

declare(strict_types=1);

namespace App\Auth\Signing;

use App\Auth\Exception\InvalidSignatureException;

/**
 * "/suivi/commandes/12" devient "/suivi/commandes/12?expires=…&signature=…".
 * La signature est un HMAC-SHA256 de tout ce qui la précède, avec APP_KEY :
 * changer un seul caractère la rend fausse.
 */
final class UrlSigner
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $key,
        private readonly ?\Closure $clock = null, // horloge remplaçable dans les tests
    ) {
        if (strlen($key) < 32) {
            throw new \InvalidArgumentException('APP_KEY trop courte : il faut au moins 32 octets aléatoires.');
        }
    }

    public function sign(string $url, int $ttlSeconds): string
    {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'expires=' . ($this->now() + $ttlSeconds);

        return $url . '&signature=' . $this->hmac($url);
    }

    /** @return array<string, string> les paramètres de l'URL */
    public function verify(string $url): array
    {
        // La signature est toujours en dernier : tout ce qui précède est signé.
        $parts = explode('&signature=', $url, 2);
        if (count($parts) !== 2) {
            throw new InvalidSignatureException('missing');
        }
        [$signedPart, $signature] = $parts;

        // hash_equals : comparaison en temps constant.
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
