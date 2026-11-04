<?php

/**
 * Point d'entrée de l'application Web (front controller).
 * Toutes les requêtes qui ne correspondent pas à un fichier statique arrivent ici.
 */

declare(strict_types=1);

use App\Core\ExceptionHandler;
use App\Core\Request;
use App\Core\Session;

require __DIR__ . '/../vendor/autoload.php';

// En premier : à partir d'ici, toute erreur (même fatale) donne une page générique et une ligne de log.
$handler = ExceptionHandler::fromEnv();
$handler->register();

// Variables lues depuis le .env (à la racine du projet, hors webroot, ignoré par git, injecté par Docker Compose).
// Si l'une manque, on s'arrête tout de suite plutôt que de tourner avec une config bancale.
require_env(['APP_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']);

Session::start();

$request = Request::fromGlobals();
$router = require __DIR__ . '/../routes.php';

try {
    $response = $router->dispatch($request);
} catch (Throwable $e) {
    $response = $handler->handle($e, $request);
}

$response->send();
