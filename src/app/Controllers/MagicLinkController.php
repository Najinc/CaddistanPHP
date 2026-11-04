<?php

namespace App\Controllers;

use App\Auth\SafeRedirect;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\Session;
use App\Core\TooManyRequestsException;
use App\Mail\Mailer;
use App\Repositories\UserRepository;

final class MagicLinkController extends Controller
{
    public const SENT_MESSAGE = 'Si un compte existe pour cette adresse, un lien de connexion vient de lui être envoyé.';

    private const LIMITS = ['ip' => 10, 'email' => 3];
    private const WINDOW = 900;

    public function create(): Response
    {
        $this->ensureMailEnabled();

        return $this->view('auth/magic-link-request', ['title' => 'Connexion sans mot de passe']);
    }

    public function send(): Response
    {
        $this->ensureMailEnabled();

        $v = $this->validator();
        $email = $v->email('email', "L'e-mail");
        $v->validate();
        $email = mb_strtolower(trim((string) $email));

        $this->throttle(['ip' => $this->request->ip(), 'email' => $email]);

        $user = (new UserRepository())->findByEmail($email);
        if ($user !== null) {
            Mailer::send(
                $user['email'],
                'Votre lien de connexion Caddistan',
                "Bonjour {$user['first_name']},\n\nCe lien vous connecte à votre compte Caddistan. Il est valable 15 minutes et ne fonctionne qu'une seule fois.\n\nSi vous n'avez rien demandé, ignorez ce message.",
                Auth::magicLinkFor($user),
                'Se connecter',
            );
        }

        Session::flash('success', self::SENT_MESSAGE);

        return $this->redirect('/connexion/sans-mot-de-passe');
    }

    public function show(): Response
    {
        $this->ensureMailEnabled();
        Auth::signer()->verify($this->currentUrl());

        return $this->view('auth/magic-link', ['title' => 'Connexion', 'action' => $this->currentUrl()]);
    }

    public function login(): Response
    {
        $this->ensureMailEnabled();
        Auth::loginWithMagicLink($this->currentUrl());

        Session::flash('success', 'Vous êtes connecté, ' . Auth::user()['first_name'] . ' !');
        $target = SafeRedirect::target(Session::get('intended'), '/compte');
        Session::forget('intended');

        return $this->redirect($target);
    }

    private function ensureMailEnabled(): void
    {
        if (!Mailer::enabled()) {
            throw HttpException::notFound();
        }
    }

    private function currentUrl(): string
    {
        return (string) ($_SERVER['REQUEST_URI'] ?? '');
    }

    private function throttle(array $identifiers): void
    {
        $limiter = new RateLimiter(new Cache());

        foreach ($identifiers as $type => $identifier) {
            $rule = 'magic_link:' . $type;
            $result = $limiter->hit($rule . ':' . $identifier, self::LIMITS[$type], self::WINDOW);

            if ($result->allowed) {
                continue;
            }

            if ($result->justExceeded()) {
                Logger::security('rate_limit.exceeded', ['rule' => $rule, 'identifier' => $identifier]);
            }

            throw new TooManyRequestsException($result->retryAfter);
        }
    }
}
