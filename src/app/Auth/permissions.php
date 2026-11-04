<?php

declare(strict_types=1);

// Rôle => permissions. Le code vérifie toujours une permission, jamais un rôle.
// Les rôles sont ceux de la colonne users.role (ENUM 'customer', 'admin').
return [
    'customer' => [
        'order.create',         // passer commande
        'order.view',           // voir ses propres commandes (la propriété est vérifiée en plus)
        'review.create',        // laisser un avis
    ],
    'admin' => [
        'order.create',
        'order.view',
        'review.create',
        'admin.dashboard',      // tableau de bord /admin
        'caddy.create',         // créer un produit
        'caddy.edit',           // lister et modifier les produits
        'caddy.delete',         // supprimer un produit
        'order.view_any',       // voir les commandes de tous les clients
        'order.update_status',  // changer le statut d'une commande
        'user.view_any',        // lister les utilisateurs
        'user.change_role',     // changer le rôle d'un utilisateur
    ],
];
