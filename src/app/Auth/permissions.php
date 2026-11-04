<?php

declare(strict_types=1);

// Rôle => permissions. Le code vérifie toujours une permission, jamais un rôle.
return [
    'client' => [
        'order.create',
        'order.view',       // ses propres commandes seulement
    ],
    'admin' => [
        'order.create',
        'order.view',
        'order.view_any',   // les commandes de tous les clients
        'caddy.create',
        'caddy.edit',
        'caddy.delete',
        'caddy.import',     // import d'image par URL (Partie 4)
    ],
];
