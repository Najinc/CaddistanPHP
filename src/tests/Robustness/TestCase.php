<?php

declare(strict_types=1);

namespace Tests\Robustness;

use App\Core\Logger;

final class AssertionFailed extends \Exception
{
}

final class Skipped extends \Exception
{
}

/** Mini-framework de test : on hérite et on écrit des méthodes test_*. */
abstract class TestCase
{
    /** Dossier temporaire propre à chaque test (logs, cache). */
    protected string $tmp;

    public function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/caddistan-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/logs', 0777, true);
        Logger::setDirectory($this->tmp . '/logs');
    }

    public function tearDown(): void
    {
        if (isset($this->tmp) && is_dir($this->tmp)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($it as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->tmp);
        }
    }

    /** Contenu brut de tous les fichiers de log écrits pendant le test. */
    protected function logs(): string
    {
        return implode('', array_map('file_get_contents', glob($this->tmp . '/logs/*.log') ?: []));
    }

    /** @return list<array> une entrée par ligne de log */
    protected function logEntries(): array
    {
        $lines = array_filter(explode("\n", $this->logs()));

        return array_values(array_map(fn ($l) => json_decode($l, true, flags: JSON_THROW_ON_ERROR), $lines));
    }

    protected function assertTrue(bool $condition, string $message = 'attendu : vrai'): void
    {
        if (!$condition) {
            throw new AssertionFailed($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'attendu : faux'): void
    {
        $this->assertTrue(!$condition, $message);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionFailed(trim($message . ' attendu ' . self::export($expected) . ', obtenu ' . self::export($actual)));
        }
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertTrue(str_contains($haystack, $needle), trim($message . ' "' . $needle . '" absent'));
    }

    protected function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertFalse(str_contains($haystack, $needle), trim($message . ' "' . $needle . '" présent alors qu\'il ne devrait pas'));
    }

    /**
     * @template T of \Throwable
     * @param class-string<T> $class
     * @return T
     */
    protected function expectThrows(string $class, callable $callback): \Throwable
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            if ($e instanceof $class) {
                return $e;
            }
            throw new AssertionFailed(sprintf('%s attendue, %s levée : %s', $class, $e::class, $e->getMessage()));
        }

        throw new AssertionFailed($class . ' attendue, aucune exception levée');
    }

    private static function export(mixed $value): string
    {
        return is_string($value) ? '"' . $value . '"' : var_export($value, true);
    }
}
