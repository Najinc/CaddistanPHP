<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Core\HttpException;

final class AccessDeniedException extends HttpException
{
    /** $reason sert aux logs, il n'est jamais affiché. */
    private function __construct(int $status, string $message, public readonly string $reason)
    {
        parent::__construct($status, $message);
    }

    public static function missingPermission(string $permission): self
    {
        return new self(403, 'Accès refusé.', 'permission manquante : ' . $permission);
    }

    // 404 plutôt que 403 : on ne confirme pas que la ressource existe.
    public static function notOwner(): self
    {
        return new self(404, 'Page introuvable.', "ressource d'un autre utilisateur");
    }
}
