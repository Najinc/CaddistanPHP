<?php

declare(strict_types=1);

namespace App\Auth\Csrf;

use App\Auth\Exception\CsrfTokenMismatchException;
use App\Auth\Session\SessionInterface;

/**
 * Un site pirate peut faire envoyer un formulaire par le navigateur de la victime (cookies compris),
 * mais il ne peut pas lire ce token : sa requête est refusée.
 */
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

    /** Appelé au login : un token connu avant la connexion ne sert plus. */
    public function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    /** À appeler avant chaque contrôleur. GET, HEAD et OPTIONS ne doivent rien modifier : pas de vérification. */
    public function verify(string $method, mixed $submitted): void
    {
        if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $expected = $this->session->get(self::SESSION_KEY);

        // hash_equals compare en temps constant : le temps de réponse ne révèle rien du token.
        if (!is_string($expected) || !is_string($submitted) || !hash_equals($expected, $submitted)) {
            throw new CsrfTokenMismatchException();
        }
    }

    /** Champ caché à mettre dans chaque formulaire POST. */
    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . $this->token() . '">';
    }
}
