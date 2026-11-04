<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Core\HttpException;

/** Lien signé refusé. Même message pour tout le monde, la cause est dans $reason pour les logs. */
final class InvalidSignatureException extends HttpException
{
    /** @param string $reason "missing", "invalid", "expired" ou "already_used" */
    public function __construct(public readonly string $reason)
    {
        parent::__construct(403, 'Lien invalide ou expiré.');
    }
}
