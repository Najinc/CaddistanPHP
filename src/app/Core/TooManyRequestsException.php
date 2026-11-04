<?php

namespace App\Core;

/** 429 Too Many Requests : envoyée avec l'en-tête Retry-After (en secondes). */
final class TooManyRequestsException extends HttpException
{
    public function __construct(public readonly int $retryAfter)
    {
        $minutes = (int) ceil($retryAfter / 60);

        parent::__construct(429, $retryAfter < 60
            ? "Trop de tentatives. Réessayez dans $retryAfter seconde(s)."
            : "Trop de tentatives. Réessayez dans $minutes minute(s).");
    }
}
