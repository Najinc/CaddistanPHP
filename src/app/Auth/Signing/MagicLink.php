<?php

declare(strict_types=1);

namespace App\Auth\Signing;

use App\Auth\Authenticatable;
use App\Auth\Exception\InvalidSignatureException;
use App\Auth\UserProviderInterface;

/** Connexion sans mot de passe par un lien envoyé par email : URL signée, valable 15 minutes, utilisable une seule fois. */
final class MagicLink
{
    private const PATH = '/connexion/lien';
    private const TTL = 900;

    public function __construct(
        private readonly UrlSigner $signer,
        private readonly UsedTokenStoreInterface $usedTokens,
        private readonly UserProviderInterface $users,
    ) {
    }

    /** Chemin à envoyer par email, précédé de APP_URL. */
    public function create(Authenticatable $user): string
    {
        $nonce = bin2hex(random_bytes(16));

        return $this->signer->sign(self::PATH . '?user=' . $user->getAuthId() . '&nonce=' . $nonce, self::TTL);
    }

    /** Vérifie le lien et le marque comme utilisé. Renvoie l'utilisateur à passer à Authenticator::login(). */
    public function consume(string $url): Authenticatable
    {
        $params = $this->signer->verify($url);

        // Une URL signée pour une autre page ne doit pas permettre de se connecter.
        if (parse_url($url, PHP_URL_PATH) !== self::PATH) {
            throw new InvalidSignatureException('invalid');
        }

        if (!$this->usedTokens->markUsed($params['nonce'], (int) $params['expires'])) {
            throw new InvalidSignatureException('already_used');
        }

        return $this->users->findById((int) $params['user']) ?? throw new InvalidSignatureException('invalid');
    }
}
