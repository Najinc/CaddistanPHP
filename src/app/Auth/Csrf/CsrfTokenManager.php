<?php

declare(strict_types=1);

namespace App\Auth\Csrf;

use App\Auth\Exception\CsrfTokenMismatchException;
use App\Auth\Session\SessionInterface;

/** Un autre site peut faire envoyer un formulaire par le navigateur de la victime, mais pas lire ce token. */
final class CsrfTokenManager
{
    public const FIELD = '_csrf';

    private const SESSION_KEY = 'csrf_token';

    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function token(): string
    {
        return $this->session->get(self::SESSION_KEY) ?? $this->regenerate();
    }

    public function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    public function verify(string $method, mixed $submitted): void
    {
        // GET, HEAD et OPTIONS ne modifient rien.
        if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $expected = $this->session->get(self::SESSION_KEY);

        // hash_equals : comparaison en temps constant.
        if (!is_string($expected) || !is_string($submitted) || !hash_equals($expected, $submitted)) {
            throw new CsrfTokenMismatchException();
        }
    }

    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . $this->token() . '">';
    }
}
