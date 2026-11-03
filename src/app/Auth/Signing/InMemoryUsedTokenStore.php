<?php

declare(strict_types=1);

namespace App\Auth\Signing;

/** Pour les tests uniquement : la mémoire est perdue à la fin de chaque requête. */
final class InMemoryUsedTokenStore implements UsedTokenStoreInterface
{
    /** @var array<string, int> */
    private array $used = [];

    public function markUsed(string $token, int $expiresAt): bool
    {
        if (isset($this->used[$token])) {
            return false;
        }
        $this->used[$token] = $expiresAt;

        return true;
    }
}
