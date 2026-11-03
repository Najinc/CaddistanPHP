<?php

declare(strict_types=1);

namespace App\Auth\Exception;

/**
 * Utilisateur connecté mais pas autorisé.
 *
 * - forbidden() : 403, il lui manque une permission (ex. un client sur /admin).
 * - hidden()    : 404, la ressource appartient à quelqu'un d'autre. On répond
 *                 « introuvable » pour ne pas révéler qu'elle existe
 *                 (sinon on peut énumérer les commandes des autres clients).
 */
final class AccessDeniedException extends AuthException
{
    private function __construct(
        string $message,
        private readonly int $status,
        /** Permission refusée ou raison, pour les logs (Partie 5). Jamais affichée. */
        public readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function forbidden(string $permission): self
    {
        return new self('Accès refusé.', 403, 'permission manquante : ' . $permission);
    }

    public static function hidden(string $reason): self
    {
        return new self('Page introuvable.', 404, $reason);
    }

    public function statusCode(): int
    {
        return $this->status;
    }
}
