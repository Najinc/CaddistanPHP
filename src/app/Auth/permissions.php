<?php

declare(strict_types=1);

/*
 * Rôles et permissions de Caddistan.
 *
 * Le code vérifie toujours une PERMISSION (ex. "caddy.edit"), jamais un rôle
 * directement : ajouter un rôle (ex. "vendeur") ne demande alors de modifier que ce fichier.
 */

return [
    'client' => [
        'order.create',     // passer commande
        'order.view',       // voir SES commandes (la propriété est vérifiée en plus)
    ],
    'admin' => [
        'order.create',
        'order.view',
        'order.view_any',   // voir les commandes de tous les clients
        'caddy.create',
        'caddy.edit',
        'caddy.delete',
        'caddy.import',     // import d'image par URL (Partie 4, protégé contre la SSRF)
    ],
];
