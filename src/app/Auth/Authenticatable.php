<?php

declare(strict_types=1);

namespace App\Auth;

/** Utilisateur, tel que la Partie 3 en a besoin (implémenté par la Partie 2). */
interface Authenticatable
{
    public function getAuthId(): int;

    public function getAuthPasswordHash(): string;

    public function getRole(): string;
}
