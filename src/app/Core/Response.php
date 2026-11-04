<?php

namespace App\Core;

/**
 * Réponse HTTP renvoyée par les contrôleurs.
 * Point central des en-têtes de sécurité : ils sont ajoutés à l'envoi (voir SecurityHeaders).
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

    /**
     * Réponse JSON sécurisée :
     *  - JSON_HEX_TAG / JSON_HEX_AMP (+ APOS / QUOT) : < > & ' " sont encodés en \u003C, \u0026…
     *    => même si le JSON finit dans une page HTML, il ne peut pas fermer une balise <script>.
     *  - JSON_THROW_ON_ERROR : une donnée non encodable lève une exception au lieu de renvoyer
     *    silencieusement `false` (réponse vide avec un code 200).
     *  - Content-Type explicite avec charset : le navigateur n'a rien à deviner.
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
               | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

        return new self(json_encode($data, $flags), $status, ['Content-Type' => 'application/json; charset=UTF-8']);
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

    /**
     * En-têtes réellement envoyés : ceux de la réponse, complétés par les en-têtes de sécurité.
     * Une réponse peut en durcir un (withHeader), mais ne peut pas en être privée par oubli.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers + SecurityHeaders::all();
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers() as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
