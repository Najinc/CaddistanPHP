<?php

declare(strict_types=1);

namespace App\Auth\Exception;

/**
 * Base de toutes les erreurs de la Partie 3.
 *
 * - getMessage() est sûr à afficher au client (aucun détail interne).
 * - statusCode() donne le code HTTP à renvoyer : c'est la couche HTTP (Partie 1)
 *   ou le handler d'exceptions (Partie 5) qui construit la réponse.
 * - Les détails utiles aux logs (Partie 5) sont dans des propriétés dédiées.
 */
abstract class AuthException extends \RuntimeException
{
    abstract public function statusCode(): int;
}
