<?php

declare(strict_types=1);

/*
 * Tests de la Partie 5 (robustesse), sans dépendance externe.
 *
 *     docker compose exec app php tests/Robustness/run.php
 *
 * OrderPlacementTest utilise la vraie base MariaDB (il remet le stock et supprime ses commandes à la fin) :
 * il est ignoré si la base n'est pas joignable (lancement hors Docker).
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/TestCase.php';

// Tout avertissement fait échouer le test en cours.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$files = glob(__DIR__ . '/*Test.php') ?: [];
foreach ($files as $file) {
    require_once $file;
}

$passed = $failed = $skipped = 0;

foreach ($files as $file) {
    $class = 'Tests\\Robustness\\' . basename($file, '.php');
    echo PHP_EOL . basename($file, '.php') . PHP_EOL;

    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test_')) {
            continue;
        }
        $label = str_replace('_', ' ', substr($method, 5));
        $test = new $class();
        try {
            $test->setUp();
            $test->{$method}();
            $passed++;
            echo "  [ok]    {$label}" . PHP_EOL;
        } catch (Tests\Robustness\Skipped $e) {
            $skipped++;
            echo "  [ignoré] {$label} ({$e->getMessage()})" . PHP_EOL;
        } catch (Throwable $e) {
            $failed++;
            echo "  [ECHEC] {$label}" . PHP_EOL;
            printf('          %s: %s (%s:%d)%s', $e::class, $e->getMessage(), basename($e->getFile()), $e->getLine(), PHP_EOL);
        } finally {
            $test->tearDown();
        }
    }
}

printf('%s%d réussi(s), %d échec(s), %d ignoré(s)%s', PHP_EOL, $passed, $failed, $skipped, PHP_EOL);
exit($failed === 0 ? 0 : 1);
