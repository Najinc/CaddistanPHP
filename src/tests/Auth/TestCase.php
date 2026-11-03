<?php

declare(strict_types=1);

namespace Tests\Auth;

final class AssertionFailed extends \Exception
{
}

/** Mini-framework de test (aucune dépendance) : on hérite et on écrit des méthodes test_*. */
abstract class TestCase
{
    public function setUp(): void
    {
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

    protected function assertNotSame(mixed $unexpected, mixed $actual, string $message = ''): void
    {
        if ($unexpected === $actual) {
            throw new AssertionFailed(trim($message . ' valeur inattendue ' . self::export($actual)));
        }
    }

    protected function assertMatches(string $pattern, string $actual, string $message = ''): void
    {
        if (preg_match($pattern, $actual) !== 1) {
            throw new AssertionFailed(trim($message . ' ' . self::export($actual) . ' ne correspond pas à ' . $pattern));
        }
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
