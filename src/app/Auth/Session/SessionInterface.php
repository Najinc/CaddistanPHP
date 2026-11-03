<?php

declare(strict_types=1);

namespace App\Auth\Session;

interface SessionInterface
{
    /** Démarre la session si ce n'est pas déjà fait. Les autres méthodes l'appellent d'elles-mêmes. */
    public function start(): void;

    public function id(): string;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function remove(string $key): void;

    /** Nouvel identifiant, données conservées, ancien identifiant supprimé côté serveur. */
    public function regenerate(): void;

    /** Destruction complète : données, fichier côté serveur et cookie. */
    public function destroy(): void;

    /** Vrai si la session précédente vient d'être détruite pour inactivité (pour afficher « session expirée »). */
    public function expiredDueToInactivity(): bool;
}
