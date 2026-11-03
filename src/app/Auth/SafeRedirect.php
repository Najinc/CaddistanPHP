<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Protection contre l'open redirect (ex. /login?redirect=https://evil.example).
 *
 * N'accepte qu'un chemin local. Sinon, un lien piégé vers le vrai Caddistan
 * renverrait la victime, juste après sa connexion, vers un faux site.
 */
final class SafeRedirect
{
    public static function target(mixed $candidate, string $fallback = '/'): string
    {
        if (!is_string($candidate) || $candidate === '' || strlen($candidate) > 2048) {
            return $fallback;
        }

        // Doit commencer par un seul "/" :
        //  - "//evil.example" et "/\evil.example" sont des URL absolues pour le navigateur ;
        //  - aucun antislash (les navigateurs le traitent comme "/") ;
        //  - aucun caractère de contrôle (le navigateur retire tabulations et retours à la ligne :
        //    "/\t/evil.example" deviendrait "//evil.example").
        if (preg_match('#\A/(?![/\\\\])[^\\\\\x00-\x1F\x7F]*\z#', $candidate) !== 1) {
            return $fallback;
        }

        return $candidate;
    }
}
