<?php

namespace App\Core;

/**
 * Réponse HTTP renvoyée par les contrôleurs.
 * Point central pour, plus tard, ajouter les en-têtes de sécurité.
 */
class Response
{
    public function __construct(
        protected string $body = '',
        protected int $status = 200,
        protected array $headers = [],
    ) {}

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function download(string $path, string $filename, string $contentType = 'application/octet-stream'): self
    {
        return new self((string) file_get_contents($path), 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
