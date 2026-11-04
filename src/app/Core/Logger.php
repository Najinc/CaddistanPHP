<?php

namespace App\Core;

/**
 * Journal applicatif : une ligne JSON par événement, dans storage/logs/ (hors webroot).
 *
 *   Logger::security('auth.login_failed', ['email' => $email]);        // security-AAAA-MM-JJ.log
 *   Logger::error('exception', ['class' => ..., 'message' => ...]);    // error-AAAA-MM-JJ.log
 *
 * Protections :
 *  - Injection de logs : toute donnée vient potentiellement de l'attaquant (e-mail, User-Agent, URL…).
 *    Les caractères de contrôle (\r, \n, \t, \0, ESC…) et les séparateurs Unicode U+2028 / U+2029 sont
 *    remplacés par leur forme visible (\x0A…) : impossible de fabriquer une fausse ligne de log.
 *    Le format JSON ajoute une seconde barrière (json_encode échappe aussi les retours à la ligne).
 *  - Secrets : toute clé qui ressemble à un mot de passe, un token, un cookie, une signature… est
 *    remplacée par [MASQUÉ], à n'importe quelle profondeur, y compris dans la query string de l'URL.
 *  - Taille : chaque chaîne est tronquée (un attaquant ne remplit pas le disque avec un e-mail de 1 Mo).
 */
final class Logger
{
    public const REDACTED = '[MASQUÉ]';

    /** Clés dont la valeur n'est jamais écrite (mots de passe, jetons, cookies, signatures, moyens de paiement). */
    private const SENSITIVE_KEY = '/pass(word|wd|phrase)?|pwd|secret|token|csrf|signature|cookie|session|authorization|api[_-]?key|card|cvv|cvc|iban/i';

    private const MAX_STRING_LENGTH = 500;
    private const MAX_DEPTH = 5;

    private static string $directory = __DIR__ . '/../../storage/logs';
    private static ?string $requestId = null;

    /** Événement de sécurité (échec de login, CSRF, 403, 429, upload refusé, SSRF, path traversal…). */
    public static function security(string $event, array $context = [], string $level = 'warning'): void
    {
        self::write('security', $level, $event, $context);
    }

    /** Erreur technique (exception non gérée, erreur fatale). */
    public static function error(string $event, array $context = []): void
    {
        self::write('error', 'error', $event, $context);
    }

    /** Identifiant unique de la requête : affiché sur la page d'erreur 500 pour retrouver la ligne de log. */
    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(8));
    }

    /** Pour les tests : écrire dans un dossier temporaire. */
    public static function setDirectory(string $directory): void
    {
        self::$directory = rtrim($directory, '/\\');
    }

    /** Vrai si la clé désigne une donnée qu'on ne doit jamais écrire (password, _token, signature…). */
    public static function isSensitiveKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_KEY, $key) === 1;
    }

    /** Nettoie récursivement une valeur avant de l'écrire (secrets masqués, contrôles neutralisés, tronqué). */
    public static function sanitize(mixed $value, string|int|null $key = null, int $depth = 0): mixed
    {
        if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            if ($depth >= self::MAX_DEPTH) {
                return '[…]';
            }

            $clean = [];
            foreach ($value as $k => $v) {
                // Les clés aussi viennent de l'attaquant (?a%0Ab=1) : on les nettoie comme les valeurs.
                $clean[is_string($k) ? self::cleanString($k) : $k] = self::sanitize($v, $k, $depth + 1);
            }

            return $clean;
        }

        return match (true) {
            is_string($value) => self::cleanString($value),
            is_int($value), is_float($value), is_bool($value), $value === null => $value,
            $value instanceof \Stringable => self::cleanString((string) $value),
            default           => '[' . get_debug_type($value) . ']',
        };
    }

    /** URL sans les paramètres sensibles : /reset?token=abc&page=2 => /reset?token=[MASQUÉ]&page=2 */
    public static function sanitizeUri(string $uri): string
    {
        $path  = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = parse_url($uri, PHP_URL_QUERY);

        if ($query === null || $query === false || $query === '') {
            return self::cleanString($path);
        }

        parse_str($query, $params);

        return self::cleanString($path . '?' . urldecode(http_build_query(self::sanitize($params))));
    }

    private static function cleanString(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_STRING_LENGTH) . '…[tronqué]';
        }

        // \p{Cc} : caractères de contrôle (\r \n \t \0 ESC…) ; \p{Zl} / \p{Zp} : séparateurs de ligne Unicode.
        return preg_replace_callback(
            '/[\p{Cc}\p{Zl}\p{Zp}]/u',
            fn (array $m) => sprintf('\\x%02X', mb_ord($m[0])),
            $value,
        );
    }

    private static function write(string $channel, string $level, string $event, array $context): void
    {
        $entry = [
            'time'       => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.vP'),
            'level'      => $level,
            'event'      => self::cleanString($event),
            'request_id' => self::requestId(),
            ...self::requestContext(),
            'context'    => self::sanitize($context),
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $file = sprintf('%s/%s-%s.log', self::$directory, $channel, date('Y-m-d'));

        // Si le fichier n'est pas inscriptible, on ne casse pas la requête : on passe par le log de PHP (stderr Docker).
        if ($line === false || @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            error_log('[caddistan] ' . ($line ?: $event));
        }
    }

    /** Qui, d'où, quoi : lu directement dans $_SERVER pour fonctionner même avant le routeur. */
    private static function requestContext(): array
    {
        if (PHP_SAPI === 'cli') {
            return ['ip' => 'cli'];
        }

        return [
            // REMOTE_ADDR uniquement : X-Forwarded-For est fourni par le client, donc falsifiable.
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'method'     => self::cleanString($_SERVER['REQUEST_METHOD'] ?? ''),
            'uri'        => self::sanitizeUri($_SERVER['REQUEST_URI'] ?? '/'),
            'user_id'    => session_status() === PHP_SESSION_ACTIVE ? ($_SESSION['user_id'] ?? null) : null,
            'user_agent' => self::cleanString(mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200)),
        ];
    }
}
