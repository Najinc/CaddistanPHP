<?php

declare(strict_types=1);

// Rôle (colonne users.role) => permissions. Le code vérifie toujours une permission, jamais un rôle.
return [
    'customer' => [
        'order.create',
        'order.view',           // ses propres commandes uniquement
        'review.create',
    ],
    'admin' => [
        'order.create',
        'order.view',
        'review.create',
        'admin.dashboard',
        'caddy.create',
        'caddy.edit',
        'caddy.delete',
        'order.view_any',       // les commandes de tous les clients (back-office)
        'order.update_status',
        'user.view_any',
        'user.change_role',
    ],
];
