<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Core\HttpException;

final class CsrfTokenMismatchException extends HttpException
{
    public function __construct()
    {
        parent::__construct(403, 'Jeton CSRF invalide ou expiré. Rechargez la page et réessayez.');
    }
}
