<?php

namespace App\Core;

use RuntimeException;

/**
 * Exception transformée en page d'erreur HTTP par le front controller.
 */
class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }

    public static function notFound(string $message = 'Page introuvable'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Accès refusé'): self
    {
        return new self(403, $message);
    }
}
