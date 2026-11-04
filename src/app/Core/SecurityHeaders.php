<?php

declare(strict_types=1);

namespace App\Core;

use App\Vite;

/**
 * En-têtes de sécurité envoyés avec CHAQUE réponse PHP (HTML, JSON, redirection, téléchargement).
 * Appliqués dans Response::send() : un contrôleur ne peut pas les oublier.
 *
 * Les fichiers statiques (/dist, /images) ne passent pas par PHP : Caddy leur met les mêmes valeurs
 * par défaut (docker/caddy/Caddyfile), sans écraser celles envoyées ici.
 */
final class SecurityHeaders
{
    /** 1 an. Pas de "preload" : il engage le domaine auprès des navigateurs, difficile à annuler. */
    public const HSTS = 'max-age=31536000; includeSubDomains';

    /**
     * L'URL complète reste envoyée vers notre propre site (Controller::back() lit le Referer),
     * mais un site tiers ne reçoit que l'origine : jamais le chemin ni la query string (ids, tokens…).
     */
    public const REFERRER_POLICY = 'strict-origin-when-cross-origin';

    private static ?string $nonce = null;

    /**
     * Nonce CSP de la requête : 128 bits aléatoires, nouveau à chaque requête.
     * Un <script> injecté (XSS) ne le connaît pas, il ne s'exécute donc pas.
     */
    public static function nonce(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    /** Pour les tests : simule une nouvelle requête. */
    public static function reset(): void
    {
        self::$nonce = null;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'Strict-Transport-Security' => self::HSTS,
            'Content-Security-Policy'   => self::csp(),
            'X-Frame-Options'           => 'DENY',
            'X-Content-Type-Options'    => 'nosniff',
            'Referrer-Policy'           => self::REFERRER_POLICY,
        ];
    }

    public static function csp(): string
    {
        $nonce = "'nonce-" . self::nonce() . "'";

        $directives = [
            'default-src'     => "'self'",
            // 'strict-dynamic' : seuls les scripts portant le nonce s'exécutent, et les modules qu'ils
            // importent héritent de la confiance. 'self' seul ne suffit pas : il autoriserait n'importe quel
            // fichier .js du site (ex : un upload mal filtré). Pas de 'unsafe-inline', pas de 'unsafe-eval'.
            'script-src'      => "$nonce 'strict-dynamic'",
            // Le nonce sert au serveur Vite en dev, qui injecte le CSS dans des <style> (lu dans <meta property="csp-nonce">).
            'style-src'       => "'self' $nonce",
            'img-src'         => "'self' data:",
            'font-src'        => "'self'",
            'connect-src'     => "'self'",
            'object-src'      => "'none'",
            // Une balise <base> injectée pourrait rediriger tous les scripts relatifs vers un autre domaine.
            'base-uri'        => "'none'",
            // Un formulaire injecté ne peut pas envoyer les données saisies (mot de passe…) ailleurs.
            'form-action'     => "'self'",
            // Personne ne peut afficher le site dans une iframe (clickjacking). Remplace X-Frame-Options,
            // gardé pour les anciens navigateurs.
            'frame-ancestors' => "'none'",
        ];

        if (Vite::isDev()) {
            // HMR : le client Vite ouvre un WebSocket vers le même domaine (wss:// n'est pas couvert par 'self' partout).
            $directives['connect-src'] .= ' wss://' . self::host();
        }

        $policy = [];
        foreach ($directives as $name => $value) {
            $policy[] = "$name $value";
        }

        return implode('; ', $policy);
    }

    /** Domaine du site, lu dans APP_URL (jamais dans l'en-tête Host, qui vient du client). */
    private static function host(): string
    {
        return (string) parse_url((string) env('APP_URL', ''), PHP_URL_HOST);
    }
}
