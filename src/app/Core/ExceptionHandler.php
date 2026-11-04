<?php

namespace App\Core;

use App\Auth\Exception\AccessDeniedException;
use App\Auth\Exception\CsrfTokenMismatchException;
use App\Auth\Exception\InvalidSignatureException;
use Throwable;

/**
 * Gestionnaire d'exceptions et d'erreurs de toute l'application.
 *
 * En production : le visiteur ne voit qu'une page générique + une référence (ex. « a3f9c2e1b07d4e55 »).
 * Le détail (classe, message, fichier, ligne, pile d'appels) part UNIQUEMENT dans storage/logs/error-*.log,
 * sous la même référence. Une trace affichée donnerait à l'attaquant les chemins, la version des
 * bibliothèques, des bouts de requêtes SQL…
 *
 * Mode debug (détail affiché) seulement si APP_DEBUG=1 ET APP_ENV différent de « production » :
 * un APP_DEBUG=1 oublié sur le serveur de prod ne suffit pas à tout exposer.
 *
 * Les exceptions de la Partie 3 (App\Auth\Exception\*) sont reconnues ici pour être loggées.
 * Si ces classes ne sont pas encore chargées, `instanceof` renvoie simplement false (pas d'erreur).
 */
final class ExceptionHandler
{
    private const FATAL_ERRORS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    private const GENERIC_MESSAGE = 'Une erreur interne est survenue.';

    public function __construct(public readonly bool $debug) {}

    public static function fromEnv(): self
    {
        $debug = filter_var(env('APP_DEBUG', '0'), FILTER_VALIDATE_BOOL)
              && env('APP_ENV', 'production') !== 'production';

        return new self($debug);
    }

    /** À appeler tout en haut de public/index.php, avant tout le reste. */
    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->debug ? '1' : '0');
        ini_set('log_errors', '1');
        // Sans ça, les traces contiennent la VALEUR des arguments : Auth::attempt('a@b.fr', 'MotDePasse!')
        // finirait en clair dans les logs (et à l'écran en debug).
        ini_set('zend.exception_ignore_args', '1');

        set_error_handler($this->handleError(...));
        set_exception_handler($this->handleUncaught(...));
        register_shutdown_function($this->handleShutdown(...));
    }

    /**
     * Warnings / notices => ErrorException : un warning ignoré (fichier illisible, clé absente…) ne laisse
     * plus le code continuer avec une valeur bancale, il devient une erreur 500 loggée.
     * Les dépréciations sont seulement loggées (elles ne doivent pas casser la page).
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false; // erreur volontairement masquée par @
        }

        if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            Logger::error('deprecated', ['message' => $message, 'file' => self::relative($file), 'line' => $line]);
            return true;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /** Exception levée hors du try de index.php (ex. pendant le démarrage de la session). */
    public function handleUncaught(Throwable $e): void
    {
        $this->report($e);
        $this->emergencyPage();
    }

    /** Erreur fatale (mémoire épuisée, timeout, erreur de syntaxe…) : aucun handler n'a pu l'attraper. */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || ($error['type'] & self::FATAL_ERRORS) === 0) {
            return;
        }

        Logger::error('fatal_error', [
            'message' => $error['message'],
            'file'    => self::relative($error['file']),
            'line'    => $error['line'],
        ]);
        $this->emergencyPage();
    }

    /** Transforme n'importe quelle exception en réponse HTTP propre (appelé par le try de index.php). */
    public function handle(Throwable $e, Request $request): Response
    {
        return match (true) {
            $e instanceof ValidationException      => $this->validationFailed($e, $request),
            $e instanceof TooManyRequestsException => $this->render(429, $e->getMessage(), $request)
                                                           ->withHeader('Retry-After', (string) $e->retryAfter),
            $e instanceof HttpException            => $this->httpError($e, $request),
            default                                => $this->serverError($e, $request),
        };
    }

    /** Écrit l'exception (et ses exceptions précédentes) dans le log d'erreurs, sans arguments ni secrets. */
    public function report(Throwable $e): void
    {
        Logger::error('exception', self::describe($e));
    }

    /**
     * Données invalides :
     *  - API / AJAX : 422 + détail des erreurs en JSON ;
     *  - formulaire HTML (POST) : retour au formulaire avec les erreurs et les saisies (Post/Redirect/Get) ;
     *  - page GET (filtres du catalogue…) : page d'erreur 422.
     */
    private function validationFailed(ValidationException $e, Request $request): Response
    {
        if ($this->wantsJson($request)) {
            return Response::json(['error' => $e->getMessage(), 'errors' => $e->errors], 422);
        }

        if ($request->method === 'POST') {
            foreach ($e->errors as $message) {
                Session::flash('error', $message);
            }
            // Les saisies sont remises dans le formulaire… sauf les mots de passe et jetons :
            // ils n'ont rien à faire dans le fichier de session sur le disque.
            Session::flashInput(array_filter(
                $request->all(),
                fn ($key) => !Logger::isSensitiveKey((string) $key),
                ARRAY_FILTER_USE_KEY,
            ));

            return Response::redirect($request->header('Referer') ?? '/');
        }

        return $this->render(422, implode(' ', $e->errors), $request);
    }

    private function httpError(HttpException $e, Request $request): Response
    {
        $this->logSecurityEvent($e);

        // Non connecté : on mémorise la page demandée puis on redirige vers la connexion
        if ($e->status === 401 && !$request->wantsJson()) {
            if ($request->method === 'GET') {
                Session::set('intended', $request->path);
            }
            return Response::redirect('/connexion');
        }

        return $this->render($e->status, $e->getMessage(), $request);
    }

    private function serverError(Throwable $e, Request $request): Response
    {
        $this->report($e);

        return $this->render(500, self::GENERIC_MESSAGE, $request, $this->debug ? $e : null);
    }

    /** 403, CSRF invalide, signature refusée, accès à la ressource d'un autre (IDOR) : on garde une trace. */
    private function logSecurityEvent(HttpException $e): void
    {
        [$event, $context] = match (true) {
            $e instanceof CsrfTokenMismatchException => ['csrf.invalid', []],
            $e instanceof InvalidSignatureException  => ['signature.invalid', ['reason' => $e->reason ?? null]],
            $e instanceof AccessDeniedException      => ['access.denied', ['status' => $e->status, 'reason' => $e->reason]],
            $e->status === 403                       => ['access.denied', ['status' => 403, 'reason' => $e->getMessage()]],
            default                                  => [null, []],
        };

        if ($event !== null) {
            Logger::security($event, $context);
        }
    }

    private function render(int $status, string $message, Request $request, ?Throwable $exception = null): Response
    {
        $reference = $status >= 500 ? Logger::requestId() : null;

        if ($this->wantsJson($request)) {
            return Response::json(array_filter(['error' => $message ?: 'Erreur', 'reference' => $reference]), $status);
        }

        try {
            return Response::html(View::render('errors/error', [
                'title'     => 'Erreur ' . $status,
                'status'    => $status,
                'message'   => $message,
                'reference' => $reference,
                'exception' => $exception,
            ]), $status);
        } catch (Throwable $viewError) {
            // La vue elle-même a échoué (ex : base de données indisponible dans le layout)
            $this->report($viewError);

            return Response::html('<h1>Erreur ' . $status . '</h1>', $status);
        }
    }

    /** Dernier recours, quand on ne peut plus passer par Response / View. */
    private function emergencyPage(): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<h1>Erreur 500</h1><p>' . self::GENERIC_MESSAGE . '</p><p>Référence : '
            . htmlspecialchars(Logger::requestId(), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    private function wantsJson(Request $request): bool
    {
        return $request->wantsJson() || str_starts_with($request->path, '/api/');
    }

    private static function describe(Throwable $e): array
    {
        return [
            'class'    => $e::class,
            'message'  => $e->getMessage(),
            'code'     => $e->getCode(),
            'file'     => self::relative($e->getFile()),
            'line'     => $e->getLine(),
            'trace'    => self::trace($e),
            'previous' => $e->getPrevious() !== null ? self::describe($e->getPrevious()) : null,
        ];
    }

    /** Pile d'appels SANS les arguments (getTraceAsString() les inclurait si l'ini n'est pas appliqué). */
    private static function trace(Throwable $e): array
    {
        return array_map(
            fn (array $frame) => sprintf(
                '%s:%s %s%s%s()',
                self::relative($frame['file'] ?? '[interne]'),
                $frame['line'] ?? '?',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'],
            ),
            array_slice($e->getTrace(), 0, 15),
        );
    }

    /** /var/www/app/Core/View.php => app/Core/View.php (le chemin absolu du serveur n'apporte rien). */
    private static function relative(string $file): string
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }
}
