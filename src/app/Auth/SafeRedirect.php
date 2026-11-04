<?php

declare(strict_types=1);

namespace App\Auth;

/** Contre l'open redirect : après le login, on ne redirige que vers une page de notre site. */
final class SafeRedirect
{
    public static function target(mixed $target, string $fallback = '/'): string
    {
        $isLocalPath = is_string($target)
            && str_starts_with($target, '/')
            && !str_starts_with($target, '//')                    // "//evil.com" est un autre site
            && preg_match('/[\\\\\x00-\x1F\x7F]/', $target) !== 1; // "/\evil.com" ou "/<tab>/evil.com" aussi, pour le navigateur

        return $isLocalPath ? $target : $fallback;
    }
}
