<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Ce que la Partie 3 attend d'un utilisateur.
 *
 * À implémenter par le modèle User de la Partie 2 (Benjamin).
 */
interface Authenticatable
{
    public function getAuthId(): int;

    /** Hash Argon2id stocké en base (colonne VARCHAR(255)). Jamais le mot de passe en clair. */
    public function getAuthPasswordHash(): string;

    /** Rôle (ex. "client", "admin"). Fixé côté serveur, jamais depuis une entrée utilisateur. */
    public function getRole(): string;
}
