<?php

declare(strict_types=1);

namespace App\Auth\Exception;

use App\Core\HttpException;

/** Personne n'est connecté : 401 (redirection vers /connexion). */
final class AuthenticationException extends HttpException
{
    public function __construct()
    {
        parent::__construct(401, 'Authentification requise.');
    }
}
