<?php

namespace App\Core;

final class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $hits,
        public readonly int $limit,
        public readonly int $retryAfter,
    ) {}

    public function remaining(): int
    {
        return max(0, $this->limit - $this->hits);
    }

    /**
     * Vrai uniquement pour la toute première requête refusée de la tranche.
     * Sert à ne logger le dépassement qu'une fois : sinon une attaque à 10 000 requêtes écrirait
     * 10 000 lignes et remplirait le disque (le log devient lui-même un vecteur de déni de service).
     */
    public function justExceeded(): bool
    {
        return $this->hits === $this->limit + 1;
    }
}
