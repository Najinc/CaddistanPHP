<?php

declare(strict_types=1);

namespace App\Auth\Exception;

/**
 * URL signée (ou magic link) refusée : 403.
 *
 * Le message affiché est volontairement le même quelle que soit la cause ;
 * la cause exacte est dans $reason pour les logs (Partie 5).
 */
final class InvalidSignatureException extends AuthException
{
    public const MISSING = 'missing';
    public const INVALID = 'invalid';
    public const EXPIRED = 'expired';
    public const ALREADY_USED = 'already_used';

    private function __construct(public readonly string $reason)
    {
        parent::__construct('Lien invalide ou expiré.');
    }

    public static function missing(): self
    {
        return new self(self::MISSING);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function alreadyUsed(): self
    {
        return new self(self::ALREADY_USED);
    }

    public function statusCode(): int
    {
        return 403;
    }
}
