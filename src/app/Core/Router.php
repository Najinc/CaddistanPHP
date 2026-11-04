<?php

namespace App\Core;

/**
 * Routeur minimaliste : GET/POST + paramètres nommés ({slug}, {id}).
 */
final class Router
{
    /** @var array<string, array<int, array{pattern: string, params: string[], action: array}>> */
    private array $routes = [];

    public function get(string $path, array $action): void
    {
        $this->add('GET', $path, $action);
    }

    public function post(string $path, array $action): void
    {
        $this->add('POST', $path, $action);
    }

    private function add(string $method, string $path, array $action): void
    {
        preg_match_all('#\{(\w+)\}#', $path, $matches);

        $pattern = preg_replace_callback('#\{(\w+)\}#', function ($m) {
            $paramName = $m[1];

            if ($paramName === 'id' || str_ends_with($paramName, '_id')) {
                return '(\d+)';
            }

            if ($paramName === 'slug') {
                return '([\w-]+)';
            }

            if ($paramName === 'filename') {
                return '([a-zA-Z0-9_.-]+)';
            }

            return '([^/]+)';
        }, rtrim($path, '/') ?: '/');

        $this->routes[$method][] = [
            'pattern' => '#^' . $pattern . '$#',
            'params'  => $matches[1],
            'action'  => $action,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes[$request->method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $request->path, $matches)) {
                continue;
            }

            $params = array_map('rawurldecode', array_slice($matches, 1));
            [$class, $method] = $route['action'];

            $controller = new $class($request);

            return $controller->$method(...$params);
        }

        if ($this->matchesOtherMethod($request)) {
            throw new HttpException(405, 'Méthode non autorisée');
        }

        throw HttpException::notFound();
    }

    private function matchesOtherMethod(Request $request): bool
    {
        foreach ($this->routes as $method => $routes) {
            if ($method === $request->method) {
                continue;
            }
            foreach ($routes as $route) {
                if (preg_match($route['pattern'], $request->path)) {
                    return true;
                }
            }
        }

        return false;
    }
}
