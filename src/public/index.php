<?php

/**
 * Point d'entrée de l'application Web (front controller).
 * Toutes les requêtes qui ne correspondent pas à un fichier statique arrivent ici.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Cache;
use App\Core\ExceptionHandler;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\Throttle;

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
$throttle = new Throttle(new RateLimiter(new Cache()));

try {
    $wasGuest = !Auth::check();
    $throttle->before($request);            // 429 + Retry-After si la limite est dépassée
    $response = $router->dispatch($request);
    $throttle->after($request, $wasGuest);  // login réussi : remise à zéro du compteur du compte
} catch (Throwable $e) {
    $response = $handler->handle($e, $request);
}

$response->send();
