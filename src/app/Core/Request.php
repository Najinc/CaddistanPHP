<?php

namespace App\Core;

/**
 * Encapsule la requête HTTP courante.
 */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $files,
        private readonly array $server,
    ) {}

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    /**
     * Paramètre GET sous forme de chaîne.
     * PHP transforme ?id[]=1 en tableau : on le refuse (400) au lieu de le laisser passer
     * dans le code qui attend une chaîne (warnings, contournement de comparaisons, erreurs SQL…).
     */
    public function query(string $key, ?string $default = null): ?string
    {
        return self::scalar($this->query, $key, $default);
    }

    /** Paramètre POST sous forme de chaîne (tableau refusé, cf. query()). */
    public function input(string $key, ?string $default = null): ?string
    {
        return self::scalar($this->body, $key, $default);
    }

    /** Valeur POST nettoyée des espaces. */
    public function string(string $key, string $default = ''): string
    {
        return trim($this->input($key) ?? $default);
    }

    /** Seul cas où l'on accepte un tableau : quand on le demande explicitement (ex : quantities[12]=3). */
    public function inputArray(string $key): array
    {
        $value = $this->body[$key] ?? [];

        if (!is_array($value)) {
            throw HttpException::badRequest("Le champ « $key » doit être une liste.");
        }

        return $value;
    }

    private static function scalar(array $source, string $key, ?string $default): ?string
    {
        if (!array_key_exists($key, $source)) {
            return $default;
        }

        if (is_array($source[$key])) {
            throw HttpException::badRequest("Le paramètre « $key » doit être une valeur simple.");
        }

        return (string) $source[$key];
    }

    /** Tous les paramètres GET (pour le Validator). */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? null;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('Accept') ?? '', 'application/json');
    }
}
