<?php

declare(strict_types=1);

namespace App\Auth\Signing;

/** Mémorise les jetons déjà utilisés par les magic links (implémenté par la Partie 2, par exemple avec une table). */
interface UsedTokenStoreInterface
{
    /**
     * Marque le jeton comme utilisé. Renvoie false s'il l'était déjà.
     * Doit être atomique : par exemple un INSERT sur une clé primaire, qui échoue si le jeton existe déjà.
     */
    public function markUsed(string $token, int $expiresAt): bool;
}
