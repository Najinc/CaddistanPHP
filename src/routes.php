<?php

/**
 * Déclaration des routes de l'application.
 */

use App\Controllers\AccountController;
use App\Controllers\Admin;
use App\Controllers\Api\ProductApiController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\DocumentController;
use App\Controllers\HomeController;
use App\Controllers\ProductController;
use App\Controllers\ReviewController;
use App\Controllers\TrackingController;
use App\Core\Router;

$router = new Router();

// Catalogue
$router->get('/', [HomeController::class, 'index']);
$router->get('/produits', [ProductController::class, 'index']);
$router->get('/produits/{slug}', [ProductController::class, 'show']);
$router->post('/produits/{slug}/avis', [ReviewController::class, 'store']);
$router->get('/documents/{filename}', [DocumentController::class, 'download']);

// Panier & commande
$router->get('/panier', [CartController::class, 'index']);
$router->post('/panier/ajouter', [CartController::class, 'add']);
$router->post('/panier/modifier', [CartController::class, 'update']);
$router->post('/panier/retirer', [CartController::class, 'remove']);
$router->get('/commande', [CheckoutController::class, 'index']);
$router->post('/commande', [CheckoutController::class, 'store']);
$router->get('/commande/confirmation/{id}', [CheckoutController::class, 'confirmation']);

// Authentification
$router->get('/connexion', [AuthController::class, 'showLogin']);
$router->post('/connexion', [AuthController::class, 'login']);
$router->get('/inscription', [AuthController::class, 'showRegister']);
$router->post('/inscription', [AuthController::class, 'register']);
$router->post('/deconnexion', [AuthController::class, 'logout']);

// Espace client
$router->get('/compte', [AccountController::class, 'index']);
$router->post('/compte', [AccountController::class, 'update']);
$router->get('/compte/commandes/{id}', [AccountController::class, 'order']);

// Suivi de commande par lien signé
$router->get('/suivi/commandes/{id}', [TrackingController::class, 'show']);

// API JSON
$router->get('/api/produits', [ProductApiController::class, 'search']);
$router->get('/api/panier', [ProductApiController::class, 'cart']);

// Administration
$router->get('/admin', [Admin\DashboardController::class, 'index']);
$router->get('/admin/produits', [Admin\ProductController::class, 'index']);
$router->get('/admin/produits/nouveau', [Admin\ProductController::class, 'create']);
$router->post('/admin/produits', [Admin\ProductController::class, 'store']);
$router->get('/admin/produits/{id}/modifier', [Admin\ProductController::class, 'edit']);
$router->post('/admin/produits/{id}', [Admin\ProductController::class, 'update']);
$router->post('/admin/produits/{id}/supprimer', [Admin\ProductController::class, 'destroy']);
$router->get('/admin/commandes', [Admin\OrderController::class, 'index']);
$router->get('/admin/commandes/{id}', [Admin\OrderController::class, 'show']);
$router->post('/admin/commandes/{id}/statut', [Admin\OrderController::class, 'updateStatus']);
$router->get('/admin/utilisateurs', [Admin\UserController::class, 'index']);
$router->post('/admin/utilisateurs/{id}/role', [Admin\UserController::class, 'updateRole']);

return $router;
