<?php

namespace App\Core;

/**
 * 422 Unprocessable Content : la requête est bien formée mais les données ne respectent pas les règles
 * (longueur, format, valeur hors liste blanche…). Contient la liste des erreurs par champ.
 */
final class ValidationException extends HttpException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(422, 'Les données envoyées sont invalides.');
    }
}
