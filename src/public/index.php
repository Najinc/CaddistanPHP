<?php

/**
 * Point d'entrée de l'application Web (front controller).
 * Toutes les requêtes qui ne correspondent pas à un fichier statique arrivent ici.
 */

declare(strict_types=1);

use App\Auth\Csrf\CsrfTokenManager;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\View;

require __DIR__ . '/../vendor/autoload.php';

// Variables lues depuis le .env (à la racine du projet, hors webroot, ignoré par git, injecté par Docker Compose).
// Si l'une manque, on s'arrête tout de suite plutôt que de tourner avec une config bancale.
require_env(['APP_URL', 'APP_KEY', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']);

Session::start();

$request = Request::fromGlobals();
$router = require __DIR__ . '/../routes.php';

try {
    // Tout POST doit porter le token CSRF de la session, sinon 403.
    Auth::csrf()->verify($request->method, $request->input(CsrfTokenManager::FIELD));

    $response = $router->dispatch($request);
} catch (ValidationException $e) {
    $response = handleValidationException($e, $request);
} catch (HttpException $e) {
    $response = handleHttpException($e, $request);
} catch (Throwable $e) {
    $response = renderError(500, 'Une erreur interne est survenue.', env('APP_DEBUG') ? $e : null, $request);
}

$response->send();

/**
 * Données invalides :
 *  - API / AJAX : 422 + détail des erreurs en JSON ;
 *  - formulaire HTML (POST) : retour au formulaire avec les erreurs et les saisies (pattern Post/Redirect/Get) ;
 *  - page GET (filtres du catalogue…) : page d'erreur 422.
 */
function handleValidationException(ValidationException $e, Request $request): Response
{
    if ($request->wantsJson() || str_starts_with($request->path, '/api/')) {
        return Response::json(['error' => $e->getMessage(), 'errors' => $e->errors], 422);
    }

    if ($request->method === 'POST') {
        foreach ($e->errors as $message) {
            Session::flash('error', $message);
        }
        Session::flashInput($request->all());

        return Response::redirect($request->header('Referer') ?? '/');
    }

    return renderError(422, implode(' ', $e->errors), null, $request);
}

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
