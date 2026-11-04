<?php

declare(strict_types=1);

namespace Tests\Robustness;

use App\Core\ExceptionHandler;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\TooManyRequestsException;

final class ExceptionHandlerTest extends TestCase
{
    public function test_en_prod_la_page_500_est_generique_et_le_detail_part_dans_les_logs(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $error = new \PDOException("SQLSTATE[42S22]: Unknown column 'passwd' in 'where clause'");

        [$status, $body] = $this->send($handler->handle($error, $this->api()));

        $this->assertSame(500, $status);
        $this->assertNotContains('SQLSTATE', $body, 'le message technique ne doit pas être affiché');
        $this->assertNotContains('.php', $body, 'aucun chemin de fichier affiché');
        $this->assertContains(Logger::requestId(), $body, 'la référence doit être affichée');

        $entry = $this->logEntries()[0];
        $this->assertSame('exception', $entry['event']);
        $this->assertSame(Logger::requestId(), $entry['request_id'], 'même référence que la page');
        $this->assertSame(\PDOException::class, $entry['context']['class']);
        $this->assertContains('SQLSTATE', $entry['context']['message']);
    }

    public function test_la_trace_loggee_ne_contient_pas_les_arguments(): void
    {
        $handler = new ExceptionHandler(debug: false);
        $login = function (string $email, string $password): never {
            throw new \RuntimeException('base indisponible');
        };

        try {
            $login('a@b.fr', 'MotDePasseSecret!');
        } catch (\RuntimeException $e) {
            $handler->report($e);
        }

        $this->assertNotContains('MotDePasseSecret!', $this->logs());
        $this->assertContains('{closure', $this->logs(), 'la trace doit quand même indiquer la fonction');
    }

    public function test_debug_impossible_en_production_meme_avec_app_debug(): void
    {
        $this->withEnv(['APP_DEBUG' => '1', 'APP_ENV' => 'production'], function () {
            $this->assertFalse(ExceptionHandler::fromEnv()->debug);
        });
        $this->withEnv(['APP_DEBUG' => '1', 'APP_ENV' => 'local'], function () {
            $this->assertTrue(ExceptionHandler::fromEnv()->debug);
        });
        $this->withEnv(['APP_DEBUG' => '1', 'APP_ENV' => false], function () {
            $this->assertFalse(ExceptionHandler::fromEnv()->debug, 'APP_ENV absent => production par défaut');
        });
    }

    public function test_429_avec_retry_after(): void
    {
        $response = (new ExceptionHandler(false))->handle(new TooManyRequestsException(42), $this->api());

        $this->assertSame(429, $response->status());
        $headers = (fn () => $this->headers)->call($response);
        $this->assertSame('42', $headers['Retry-After']);
    }

    public function test_un_403_est_logge_mais_pas_un_404(): void
    {
        $handler = new ExceptionHandler(false);
        $handler->handle(HttpException::forbidden('permission manquante : caddy.edit'), $this->api());
        $handler->handle(HttpException::notFound(), $this->api());

        $entries = $this->logEntries();
        $this->assertSame(1, count($entries));
        $this->assertSame('access.denied', $entries[0]['event']);
    }

    public function test_un_warning_devient_une_exception(): void
    {
        $handler = new ExceptionHandler(false);

        $this->expectThrows(\ErrorException::class, fn () => $handler->handleError(E_WARNING, 'Undefined array key', __FILE__, 1));

        // Une dépréciation est loggée, pas transformée en erreur 500
        $this->assertTrue($handler->handleError(E_DEPRECATED, 'fonction obsolète', __FILE__, 1));
        $this->assertSame('deprecated', $this->logEntries()[0]['event']);

        // Erreur masquée par @ (error_reporting() vaut alors 0 pour cet appel) : laissée à PHP
        $level = error_reporting(0);
        try {
            $this->assertFalse($handler->handleError(E_WARNING, 'masqué par @', __FILE__, 1));
        } finally {
            error_reporting($level);
        }
    }

    /** Requête API : la réponse est en JSON, pas besoin de la base pour le layout. */
    private function api(): Request
    {
        return new Request('GET', '/api/produits', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    }

    /** @return array{0: int, 1: string} */
    private function send(Response $response): array
    {
        // Lecture du corps sans send() : en CLI, header() échouerait (la sortie du runner a déjà commencé).
        return [$response->status(), (fn () => $this->body)->call($response)];
    }

    private function withEnv(array $vars, callable $callback): void
    {
        $old = array_map(fn ($k) => getenv($k), array_combine(array_keys($vars), array_keys($vars)));
        foreach ($vars as $k => $v) {
            putenv($v === false ? $k : "$k=$v");
        }
        try {
            $callback();
        } finally {
            foreach ($old as $k => $v) {
                putenv($v === false ? $k : "$k=$v");
            }
        }
    }
}
