<?php

declare(strict_types=1);

namespace App\Auth\Signing;

use App\Auth\Exception\InvalidSignatureException;

/**
 * Routes signées : HMAC-SHA256 sur le chemin, les paramètres et la date d'expiration.
 *
 *   $url = $signer->sign('/commandes/12/facture', 3600);
 *   // "/commandes/12/facture?expires=1767225600&signature=3f9a…"
 *   $signer->verify($_SERVER['REQUEST_URI']); // lève une exception si un seul caractère a changé
 *
 * Sans APP_KEY, impossible de fabriquer une signature valide : on peut donc
 * envoyer un lien qui donne accès à une ressource précise, pour une durée limitée,
 * sans session.
 */
final class UrlSigner
{
    public const SIGNATURE = 'signature';
    public const EXPIRES = 'expires';

    private const MIN_KEY_BYTES = 32;

    private readonly string $key;

    /** @param (\Closure(): int)|null $clock horloge injectable (tests) ; time() par défaut */
    public function __construct(
        #[\SensitiveParameter] string $appKey,
        private readonly ?\Closure $clock = null,
    ) {
        if (strlen($appKey) < self::MIN_KEY_BYTES) {
            throw new \InvalidArgumentException('APP_KEY trop courte : il faut au moins 32 octets aléatoires.');
        }

        // Sous-clé dédiée aux URL signées : si APP_KEY sert aussi ailleurs, une signature
        // calculée pour un autre usage ne peut pas être réutilisée ici.
        $this->key = hash_hkdf('sha256', $appKey, 32, 'caddistan/signed-url/v1');
    }

    /**
     * @param string $pathAndQuery chemin local, avec ou sans paramètres (ex. "/commandes/12/facture?format=pdf")
     * @return string le même chemin, avec "expires" et "signature" ajoutés
     */
    public function sign(string $pathAndQuery, int $ttlSeconds): string
    {
        if ($ttlSeconds <= 0) {
            throw new \InvalidArgumentException('La durée de validité doit être positive.');
        }

        [$path, $params] = self::split($pathAndQuery);

        if (preg_match('#\A/(?![/\\\\])#', $path) !== 1) {
            throw new \InvalidArgumentException('Seul un chemin local peut être signé (ex. "/commandes/12/facture").');
        }
        foreach ([self::SIGNATURE, self::EXPIRES] as $reserved) {
            if (array_key_exists($reserved, $params)) {
                throw new \InvalidArgumentException('Paramètre réservé : ' . $reserved);
            }
        }

        $params[self::EXPIRES] = (string) ($this->now() + $ttlSeconds);
        $params[self::SIGNATURE] = $this->compute($path, $params);

        return $path . '?' . self::buildQuery($params);
    }

    /**
     * @param string $requestUri l'URL reçue, typiquement $_SERVER['REQUEST_URI']
     * @return array<string, string> les paramètres vérifiés (dont "expires", hors "signature")
     *
     * @throws InvalidSignatureException
     */
    public function verify(string $requestUri): array
    {
        try {
            [$path, $params] = self::split($requestUri);
        } catch (\InvalidArgumentException) {
            throw new InvalidSignatureException('invalid');
        }

        $signature = $params[self::SIGNATURE] ?? null;
        $expires = $params[self::EXPIRES] ?? null;
        if ($signature === null || $expires === null) {
            throw new InvalidSignatureException('missing');
        }
        unset($params[self::SIGNATURE]);

        // hash_equals : comparaison en temps constant. Une comparaison classique (===) s'arrête
        // au premier caractère différent, et le temps de réponse permettrait de deviner la signature.
        if (preg_match('/\A[0-9a-f]{64}\z/', $signature) !== 1
            || !hash_equals($this->compute($path, $params), $signature)) {
            throw new InvalidSignatureException('invalid');
        }

        // On vérifie l'expiration APRÈS la signature : la date fait partie des données signées,
        // on sait donc qu'elle n'a pas été modifiée.
        if (!ctype_digit($expires) || $this->now() >= (int) $expires) {
            throw new InvalidSignatureException('expired');
        }

        return $params;
    }

    /** @param array<string, string> $params */
    private function compute(string $path, array $params): string
    {
        // Ordre canonique : réordonner les paramètres ne change pas la signature.
        ksort($params, SORT_STRING);

        return hash_hmac('sha256', $path . '?' . self::buildQuery($params), $this->key);
    }

    /**
     * Découpe l'URL brute, sans passer par $_GET : PHP y transforme certains noms
     * (points, crochets...) et la signature ne correspondrait plus à ce qui a été reçu.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function split(string $uri): array
    {
        $uri = explode('#', $uri, 2)[0];
        [$path, $query] = array_pad(explode('?', $uri, 2), 2, '');

        $params = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($name);
            if (array_key_exists($name, $params)) {
                // Un paramètre en double serait ambigu (lequel l'application lirait-elle ?) : on refuse.
                throw new \InvalidArgumentException('Paramètre en double : ' . $name);
            }
            $params[$name] = urldecode($value);
        }

        return [$path, $params];
    }

    /** @param array<array-key, string> $params */
    private static function buildQuery(array $params): string
    {
        $parts = [];
        foreach ($params as $name => $value) {
            $parts[] = rawurlencode((string) $name) . '=' . rawurlencode($value);
        }

        return implode('&', $parts);
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
