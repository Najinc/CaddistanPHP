<?php

/**
 * Point d'entrée de l'application Web (front controller).
 * Toutes les requêtes qui ne correspondent pas à un fichier statique arrivent ici.
 */

declare(strict_types=1);

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

require __DIR__ . '/../vendor/autoload.php';

Session::start();

$request = Request::fromGlobals();
$router = require __DIR__ . '/../routes.php';

try {
    $response = $router->dispatch($request);
} catch (HttpException $e) {
    $response = handleHttpException($e, $request);
} catch (Throwable $e) {
    $response = renderError(500, 'Une erreur interne est survenue.', env('APP_DEBUG') ? $e : null, $request);
}

$response->send();

function handleHttpException(HttpException $e, Request $request): Response
{
    // Non connecté : on mémorise la page demandée puis on redirige vers la connexion
    if ($e->status === 401 && !$request->wantsJson()) {
        if ($request->method === 'GET') {
            Session::set('intended', $request->path);
        }
        return Response::redirect('/connexion');
    }

    return renderError($e->status, $e->getMessage(), null, $request);
}

function renderError(int $status, string $message, ?Throwable $exception, Request $request): Response
{
    if ($request->wantsJson() || str_starts_with($request->path, '/api/')) {
        return Response::json(['error' => $message ?: 'Erreur'], $status);
    }

    try {
        return Response::html(View::render('errors/error', [
            'title'     => 'Erreur ' . $status,
            'status'    => $status,
            'message'   => $message,
            'exception' => $exception,
        ]), $status);
    } catch (Throwable) {
        // La vue elle-même a échoué (ex : base de données indisponible dans le layout)
        return Response::html('<h1>Erreur ' . $status . '</h1>', $status);
    }
}
