<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Core\HttpException;

final class InvalidSignatureException extends HttpException
{
    /** @param string $reason "missing", "invalid", "expired" ou "already_used" (pour les logs) */
    public function __construct(public readonly string $reason)
    {
        parent::__construct(403, 'Lien invalide ou expiré.');
    }
}
