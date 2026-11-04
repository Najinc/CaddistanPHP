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

    /** 403 : il manque une permission. */
    public static function missingPermission(string $permission): self
    {
        return new self(403, 'Accès refusé.', 'permission manquante : ' . $permission);
    }

    /** 404 : la ressource est à quelqu'un d'autre. On ne confirme pas qu'elle existe. */
    public static function notOwner(string $reason): self
    {
        return new self(404, 'Page introuvable.', $reason);
    }
}
