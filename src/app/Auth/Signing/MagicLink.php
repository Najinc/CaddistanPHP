<?php

declare(strict_types=1);

namespace App\Auth\Signing;

use App\Auth\Authenticatable;
use App\Auth\Exception\InvalidSignatureException;
use App\Auth\UserProviderInterface;

/**
 * Connexion sans mot de passe par un lien envoyé par email.
 *
 * Le lien est une URL signée (UrlSigner), plus :
 *  - une durée courte (15 minutes par défaut) ;
 *  - un usage unique : un lien déjà cliqué, ou intercepté puis rejoué, est refusé ;
 *  - une vérification du chemin : une URL signée pour une autre page ne permet pas de se connecter.
 */
final class MagicLink
{
    public const DEFAULT_PATH = '/connexion/lien';

    public function __construct(
        private readonly UrlSigner $signer,
        private readonly UsedTokenStoreInterface $usedTokens,
        private readonly UserProviderInterface $users,
        private readonly string $path = self::DEFAULT_PATH,
        private readonly int $ttlSeconds = 900,
    ) {
    }

    /** Chemin signé à envoyer par email (préfixé par APP_URL). */
    public function create(Authenticatable $user): string
    {
        $nonce = bin2hex(random_bytes(16));

        return $this->signer->sign(
            sprintf('%s?user=%d&nonce=%s', $this->path, $user->getAuthId(), $nonce),
            $this->ttlSeconds,
        );
    }

    /**
     * Vérifie et consomme le lien. Renvoie l'utilisateur à passer ensuite à Authenticator::login().
     *
     * @throws InvalidSignatureException
     */
    public function consume(string $requestUri): Authenticatable
    {
        $params = $this->signer->verify($requestUri);

        $path = explode('?', explode('#', $requestUri, 2)[0], 2)[0];
        if ($path !== $this->path) {
            throw new InvalidSignatureException('invalid');
        }

        $userId = $params['user'] ?? '';
        $nonce = $params['nonce'] ?? '';
        if (!ctype_digit($userId) || preg_match('/\A[0-9a-f]{32}\z/', $nonce) !== 1) {
            throw new InvalidSignatureException('invalid');
        }

        if (!$this->usedTokens->markUsed('magic-link:' . $nonce, (int) $params[UrlSigner::EXPIRES])) {
            throw new InvalidSignatureException('already_used');
        }

        return $this->users->findById((int) $userId) ?? throw new InvalidSignatureException('invalid');
    }
}
