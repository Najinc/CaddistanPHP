<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Accès aux utilisateurs dont la Partie 3 a besoin.
 *
 * À implémenter par la Partie 2 (Benjamin), avec des requêtes préparées.
 */
interface UserProviderInterface
{
    public function findById(int $id): ?Authenticatable;

    /** L'email reçu est déjà normalisé (voir Authenticator::normalizeEmail()). */
    public function findByEmail(string $email): ?Authenticatable;

    /** Appelée au login quand le hash doit être recalculé (password_needs_rehash). */
    public function updatePasswordHash(int $userId, string $passwordHash): void;
}
