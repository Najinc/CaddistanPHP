<?php

namespace App\Core;

/**
 * Applique les limites de requêtes, avant le contrôleur (appelé par public/index.php).
 *
 * | Route            | Par IP          | Par compte                     |
 * |------------------|-----------------|--------------------------------|
 * | POST /connexion  | 20 / 15 min     | 5 / 15 min (e-mail saisi)      |
 * | POST /inscription| 5 / heure       | –                              |
 * | /api/*           | 60 / minute     | 60 / minute (si connecté)      |
 *
 * Pourquoi les deux clés sur le login :
 *  - par compte : bloque le brute force d'UN compte, même depuis un botnet (une IP différente par essai) ;
 *  - par IP     : bloque une IP qui essaie un mot de passe courant sur BEAUCOUP de comptes (password spraying).
 * La limite par compte s'applique que l'e-mail existe ou non : sinon la réponse 429 révélerait quels
 * comptes existent. L'IP vient de REMOTE_ADDR (fixée par Caddy), jamais de X-Forwarded-For (falsifiable).
 */
final class Throttle
{
    public const LOGIN_PER_IP      = [20, 900];
    public const LOGIN_PER_ACCOUNT = [5, 900];
    public const REGISTER_PER_IP   = [5, 3600];
    public const API_PER_IP        = [60, 60];
    public const API_PER_USER      = [60, 60];

    public function __construct(private readonly RateLimiter $limiter) {}

    /** @throws TooManyRequestsException */
    public function before(Request $request): void
    {
        foreach ($this->limitsFor($request) as [$rule, $identifier, [$max, $window]]) {
            $result = $this->limiter->hit("$rule:$identifier", $max, $window);

            if ($result->allowed) {
                continue;
            }

            if ($result->justExceeded()) {
                Logger::security('rate_limit.exceeded', [
                    'rule'        => $rule,
                    'identifier'  => $identifier,
                    'limit'       => "$max / {$window}s",
                    'retry_after' => $result->retryAfter,
                ]);
            }

            throw new TooManyRequestsException($result->retryAfter);
        }
    }

    /**
     * Login réussi : on remet à zéro le compteur du compte (un utilisateur qui s'est trompé 4 fois
     * n'est pas bloqué à sa prochaine connexion). Le compteur par IP, lui, n'est pas remis à zéro.
     * $wasGuest évite qu'un attaquant déjà connecté (avec SON compte) remette à zéro le compteur
     * d'un AUTRE compte en postant son e-mail sur /connexion.
     */
    public function after(Request $request, bool $wasGuest): void
    {
        if ($wasGuest && $this->isLogin($request) && Auth::check() && ($email = $this->email($request)) !== null) {
            $this->limiter->clear("login:account:$email", self::LOGIN_PER_ACCOUNT[1]);
        }
    }

    /** @return list<array{0: string, 1: string, 2: array{0: int, 1: int}}> [règle, identifiant, [max, fenêtre]] */
    private function limitsFor(Request $request): array
    {
        $ip = $request->ip();

        if ($this->isLogin($request)) {
            $limits = [['login:ip', $ip, self::LOGIN_PER_IP]];

            if (($email = $this->email($request)) !== null) {
                $limits[] = ['login:account', $email, self::LOGIN_PER_ACCOUNT];
            }

            return $limits;
        }

        if ($request->method === 'POST' && $request->path === '/inscription') {
            return [['register:ip', $ip, self::REGISTER_PER_IP]];
        }

        if (str_starts_with($request->path, '/api/')) {
            $limits = [['api:ip', $ip, self::API_PER_IP]];

            if (($userId = Auth::id()) !== null) {
                $limits[] = ['api:user', (string) $userId, self::API_PER_USER];
            }

            return $limits;
        }

        return [];
    }

    private function isLogin(Request $request): bool
    {
        return $request->method === 'POST' && $request->path === '/connexion';
    }

    /** E-mail normalisé (Admin@X.fr et admin@x.fr partagent le même compteur), null s'il est vide ou absurde. */
    private function email(Request $request): ?string
    {
        $email = mb_strtolower(trim($request->input('email') ?? ''));

        return $email !== '' && mb_strlen($email) <= 190 ? $email : null;
    }
}
