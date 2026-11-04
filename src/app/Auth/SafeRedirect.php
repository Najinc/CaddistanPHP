<?php

declare(strict_types=1);

namespace App\Auth;

/** Après le login, on ne redirige que vers une page du site (anti open redirect). */
final class SafeRedirect
{
    public static function target(mixed $target, string $fallback = '/'): string
    {
        // "//evil.com", "/\evil.com" ou "/<tab>/evil.com" mèneraient vers un autre site.
        $isLocalPath = is_string($target)
            && str_starts_with($target, '/')
            && !str_starts_with($target, '//')
            && preg_match('/[\\\\\x00-\x1F\x7F]/', $target) !== 1;

        return $isLocalPath ? $target : $fallback;
    }
}
