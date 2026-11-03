<?php

declare(strict_types=1);

namespace App\Auth\Signing;

/**
 * Mémorise les jetons à usage unique déjà consommés (magic links).
 *
 * À implémenter par la Partie 2 (table en base) ou la Partie 5 (cache).
 * InMemoryUsedTokenStore ne sert qu'aux tests.
 */
interface UsedTokenStoreInterface
{
    /**
     * Marque le jeton comme utilisé. Renvoie false s'il l'était déjà.
     *
     * DOIT être atomique : deux requêtes simultanées ne doivent pas obtenir true
     * toutes les deux. Exemple : un INSERT dans une table qui a une contrainte
     * UNIQUE sur le jeton, où l'échec de l'INSERT signifie « déjà utilisé ».
     *
     * @param int $expiresAt timestamp après lequel on peut oublier le jeton (le lien est expiré de toute façon)
     */
    public function markUsed(string $token, int $expiresAt): bool;
}
