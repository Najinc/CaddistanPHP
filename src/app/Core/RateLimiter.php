<?php

namespace App\Core;

/**
 * Rate limiting en FENÊTRE FIXE.
 *
 * Le temps est découpé en tranches de $windowSeconds alignées sur l'horloge (ex. 10:00-10:15, 10:15-10:30).
 * Chaque requête incrémente le compteur de la tranche en cours ; au-delà de $maxAttempts, on refuse
 * jusqu'à la tranche suivante (Retry-After = secondes restantes avant la fin de la tranche).
 *
 *   $result = $limiter->hit('login:ip:1.2.3.4', maxAttempts: 20, windowSeconds: 900);
 *   if (!$result->allowed) { ... 429 + Retry-After: $result->retryAfter ... }
 *
 * Limite connue de la fenêtre fixe : à cheval sur deux tranches, on peut passer jusqu'à 2 × max
 * requêtes en peu de temps (max à 10:14:59, max à 10:15:00). C'est le compromis accepté contre la
 * simplicité (un seul compteur par clé) ; la fenêtre glissante corrige ça au prix de plus de stockage.
 *
 * On compte la tentative AVANT de la traiter (incrément atomique) : vérifier puis incrémenter après
 * coup laisserait passer une rafale de requêtes parallèles qui lisent toutes « 4 tentatives ».
 */
final class RateLimiter
{
    public function __construct(
        private readonly Cache $cache,
        private readonly ?\Closure $clock = null,
    ) {}

    public function hit(string $key, int $maxAttempts, int $windowSeconds): RateLimitResult
    {
        [$bucket, $retryAfter] = $this->window($key, $windowSeconds);

        $hits = $this->cache->increment($bucket, $retryAfter);

        // Une fois sur 100, on supprime les compteurs des tranches passées (sinon un fichier par IP et par tranche s'accumule).
        if (random_int(1, 100) === 1) {
            $this->cache->prune();
        }

        return new RateLimitResult(
            allowed: $hits <= $maxAttempts,
            hits: $hits,
            limit: $maxAttempts,
            retryAfter: $retryAfter,
        );
    }

    /** Remet le compteur de la tranche en cours à zéro (ex. login réussi pour ce compte). */
    public function clear(string $key, int $windowSeconds): void
    {
        $this->cache->forget($this->window($key, $windowSeconds)[0]);
    }

    /** @return array{0: string, 1: int} clé du compteur de la tranche courante, secondes avant la tranche suivante */
    private function window(string $key, int $windowSeconds): array
    {
        $now = $this->clock !== null ? ($this->clock)() : time();
        $window = intdiv($now, $windowSeconds);
        $resetAt = ($window + 1) * $windowSeconds;

        return ["ratelimit:$key:$windowSeconds:$window", max(1, $resetAt - $now)];
    }
}
