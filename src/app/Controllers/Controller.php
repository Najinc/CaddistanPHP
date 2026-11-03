<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

/**
 * Contrôleur de base : rendu de vues, redirections et garde-fous d'accès.
 */
abstract class Controller
{
    public function __construct(protected Request $request) {}

    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $html = View::render($template, $data);
        Session::clearOldInput();

        return Response::html($html, $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    /** Redirige vers la page précédente avec un message d'erreur et les saisies. */
    protected function back(string $fallback, ?string $error = null): Response
    {
        if ($error !== null) {
            Session::flash('error', $error);
        }
        Session::flashInput($this->request->all());

        return Response::redirect($this->request->header('Referer') ?? $fallback);
    }

    /** Validator sur les données POST. */
    protected function validator(): Validator
    {
        return new Validator($this->request->all());
    }

    /** Validator sur la query string (GET). */
    protected function queryValidator(): Validator
    {
        return new Validator($this->request->allQuery());
    }

    /** Paramètre de route {id} : entier positif strict, sinon 404 (/commandes/abc, /commandes/1 OR 1=1…). */
    protected function routeId(string $id): int
    {
        $int = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $int !== false ? $int : throw HttpException::notFound();
    }

    /** Paramètre de route {slug} : lettres minuscules, chiffres et tirets uniquement, sinon 404. */
    protected function routeSlug(string $slug): string
    {
        return preg_match('/^[a-z0-9-]{1,170}$/', $slug) ? $slug : throw HttpException::notFound();
    }

    protected function requireLogin(): void
    {
        if (!Auth::check()) {
            Session::flash('info', 'Connectez-vous pour continuer.');
            throw new HttpException(401);
        }
    }
}
