<?php

declare(strict_types=1);

namespace App\Auth\Exception;

/** Token CSRF absent ou invalide sur une requête qui modifie l'état : 403. */
final class CsrfTokenMismatchException extends AuthException
{
    public function __construct()
    {
        parent::__construct('Jeton CSRF invalide ou expiré. Rechargez la page et réessayez.');
    }

    public function statusCode(): int
    {
        return 403;
    }
}
