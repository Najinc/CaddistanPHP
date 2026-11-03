<?php

declare(strict_types=1);

/*
 * Tests de la Partie 3 (authentification et accès), sans dépendance externe.
 *
 * Depuis le dossier src/ (ou /var/www dans le conteneur) :
 *     composer dump-autoload
 *     php tests/Auth/run.php
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/TestCase.php';
require __DIR__ . '/Support/Fakes.php';

// Tout avertissement ou dépréciation fait échouer le test en cours.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false; // erreur volontairement masquée par @
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$files = glob(__DIR__ . '/*Test.php') ?: [];
foreach ($files as $file) {
    require_once $file;
}

$passed = 0;
$failed = 0;

foreach ($files as $file) {
    $class = 'Tests\\Auth\\' . basename($file, '.php');
    echo PHP_EOL . basename($file, '.php') . PHP_EOL;

    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test_')) {
            continue;
        }
        $label = str_replace('_', ' ', substr($method, 5));
        try {
            $test = new $class();
            $test->setUp();
            $test->{$method}();
            $passed++;
            echo "  [ok]    {$label}" . PHP_EOL;
        } catch (Throwable $e) {
            $failed++;
            echo "  [ECHEC] {$label}" . PHP_EOL;
            printf('          %s: %s (%s:%d)%s', $e::class, $e->getMessage(), basename($e->getFile()), $e->getLine(), PHP_EOL);
        }
    }
}

printf('%s%d réussi(s), %d échec(s)%s', PHP_EOL, $passed, $failed, PHP_EOL);
exit($failed === 0 ? 0 : 1);
