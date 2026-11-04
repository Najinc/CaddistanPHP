<?php

declare(strict_types=1);

namespace App\Auth\Signing;

interface UsedTokenStoreInterface
{
    /** Renvoie false si le jeton a déjà servi. Doit être atomique (ex : INSERT sur une clé primaire). */
    public function markUsed(string $token, int $expiresAt): bool;
}
