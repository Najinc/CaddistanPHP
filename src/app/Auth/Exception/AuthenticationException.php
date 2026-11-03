<?php

declare(strict_types=1);

namespace App\Auth\Exception;

/** Aucun utilisateur connecté alors que la route l'exige : 401 (ou redirection vers le login pour une page HTML). */
final class AuthenticationException extends AuthException
{
    public function __construct(string $message = 'Authentification requise.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 401;
    }
}
