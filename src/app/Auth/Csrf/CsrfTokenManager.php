<?php

declare(strict_types=1);

namespace App\Auth\Csrf;

use App\Auth\Exception\CsrfTokenMismatchException;
use App\Auth\Session\SessionInterface;

/**
 * Token CSRF par session (synchronizer token).
 *
 * Un site tiers peut faire envoyer un POST par le navigateur de la victime,
 * cookies compris, mais il ne peut pas lire ce token : la requête est rejetée.
 *
 * Toute méthode autre que GET, HEAD et OPTIONS est vérifiée (POST, PUT, PATCH,
 * DELETE et toute méthode inconnue). Corollaire : aucune route GET ne doit
 * modifier l'état (la déconnexion se fait en POST).
 */
final class CsrfTokenManager
{
    /** Nom du champ caché dans les formulaires. */
    public const FIELD = '_csrf';
    /** En-tête pour les appels fetch() / AJAX. */
    public const HEADER = 'X-CSRF-Token';

    private const SESSION_KEY = '_csrf.token';
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        return is_string($token) ? $token : $this->regenerate();
    }

    /** Nouveau token. Appelé au login pour qu'un token connu avant la connexion ne serve plus. */
    public function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    public function isValid(mixed $submitted): bool
    {
        // Refuse tout ce qui n'est pas une chaîne, y compris un tableau injecté (_csrf[]=...).
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }

        $expected = $this->session->get(self::SESSION_KEY);

        // hash_equals : comparaison en temps constant, le temps de réponse ne révèle rien du token.
        return is_string($expected) && hash_equals($expected, $submitted);
    }

    public static function requiresCheck(string $method): bool
    {
        return !in_array(strtoupper($method), self::SAFE_METHODS, true);
    }

    /**
     * À appeler sur chaque requête, avant le contrôleur.
     * Utiliser la méthode HTTP réelle : un formulaire avec _method=DELETE est un POST, donc vérifié.
     *
     * @throws CsrfTokenMismatchException
     */
    public function verify(string $method, mixed $submittedToken): void
    {
        if (self::requiresCheck($method) && !$this->isValid($submittedToken)) {
            throw new CsrfTokenMismatchException();
        }
    }

    /**
     * Token soumis : champ de formulaire, sinon en-tête X-CSRF-Token.
     * Jamais dans l'URL (il fuirait dans l'historique, les logs et le Referer).
     *
     * @param array<mixed> $body   $_POST (ou le corps JSON décodé)
     * @param array<mixed> $server $_SERVER
     */
    public static function submittedToken(array $body, array $server): mixed
    {
        return $body[self::FIELD] ?? $server['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    /** Champ caché à mettre dans chaque formulaire POST. */
    public function field(): string
    {
        return sprintf('<input type="hidden" name="%s" value="%s">', self::FIELD, $this->escape($this->token()));
    }

    /** Balise meta lue par le JavaScript pour remplir l'en-tête X-CSRF-Token. */
    public function metaTag(): string
    {
        return sprintf('<meta name="csrf-token" content="%s">', $this->escape($this->token()));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
