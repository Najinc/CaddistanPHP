<?php

declare(strict_types=1);

namespace App\Auth\Session;

/** Ce dont la Partie 3 a besoin d'une session. Dans les tests, ArraySession la remplace. */
interface SessionInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    /** Nouvel identifiant, l'ancien est supprimé côté serveur. */
    public function regenerate(): void;

    /** Supprime tout : données, session côté serveur et cookie. */
    public function destroy(): void;
}
