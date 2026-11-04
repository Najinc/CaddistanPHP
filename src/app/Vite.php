<?php

namespace App;

use App\Core\SecurityHeaders;
use RuntimeException;

/**
 * Classe inspirée du fonctionnement de Laravel
 * Permet de gérer le hot reload et les scripts buildés par Vite.
 * 
 * La classe vérifie les fichiers suivants :
 *  - public/hot (url du serveur de dev)
 *  - public/dist/.vite/manifest.json
 *      Ce fichier point vers les ressources dans public/dist/assets/*.{js,css}
 * 
 * Sortie après un npm run build :
 * <link rel="stylesheet" href="/dist/assets/main-Cv8k2n_S.css">
 * <script type="module" nonce="…" src="/dist/assets/main-CbXgiLKm.js"></script>
 *
 * Sortie en hot reload :
 * <script type="module" src="https://secuweb.local/@vite/client"></script>
 * <script type="module" src="https://secuweb.local/resources/main.ts"></script>
 * 
 */
final class Vite
{
    private const BASE_URL   = '/dist/';

    /**
     * Génère les tags HTML script et style JS et CSS 
     *
     * Chaque <script> et <link rel="modulepreload"> porte le nonce CSP de la requête (sinon la CSP le bloque).
     * La balise <meta property="csp-nonce"> est lue par Vite, qui ajoute le nonce aux balises qu'il crée
     * lui-même (<style> du CSS en dev, préchargement des modules).
     */
    public static function vite(string $entry): string
    {   
        $nonce = self::nonceAttribute();
        $meta  = '<meta property="csp-nonce"' . $nonce . '>' . "\n";

        if (self::isDev())
            return $meta . self::devTags($entry, $nonce);
        
        return $meta . self::buildTags($entry, $nonce);
    }

    /** ' nonce="..."' (base64 : seulement A-Z a-z 0-9 + / =, échappé quand même). */
    private static function nonceAttribute(): string
    {
        return ' nonce="' . htmlspecialchars(SecurityHeaders::nonce(), ENT_QUOTES) . '"';
    }

    private static function getHotFilePath(): string
    {
        return dirname(__DIR__) . '/public/hot';
    }

    public static function isDev(): bool
    {
        if (getenv('APP_ENV') === 'production') {
            return false;
        }

        return file_exists(self::getHotFilePath());
    }

    private static function getDevServerURL(): string
    {
        $url = trim((string) file_get_contents(self::getHotFilePath()));

        if ($url === '')
            throw new RuntimeException('Fichier hot vide.');

        return rtrim($url, '/');
    }

    private static function devTags(string $entry, string $nonce): string
    {
        $server = htmlspecialchars(self::getDevServerURL(), ENT_QUOTES);
        $entry  = htmlspecialchars(ltrim($entry, '/'), ENT_QUOTES);

        return <<<HTML
            <script type="module"{$nonce} src="{$server}/@vite/client"></script>
            <script type="module"{$nonce} src="{$server}/{$entry}"></script>
            HTML;
    }

    private static function buildTags(string $entry, string $nonce): string
    {
        $manifest = self::getManifestJSON();

        if (!isset($manifest[$entry]))
            throw new RuntimeException("Entrée \"$entry\" absente du manifest Vite.");

        $chunk = $manifest[$entry];
        $tags  = [];

        foreach ($chunk['css'] ?? [] as $css) {
            $tags[] = '<link rel="stylesheet" href="' . self::BASE_URL . $css . '">';
        }

        foreach ($chunk['imports'] ?? [] as $import) {
            $tags[] = '<link rel="modulepreload"' . $nonce . ' href="' . self::BASE_URL . $manifest[$import]['file'] . '">';
            foreach ($manifest[$import]['css'] ?? [] as $css) {
                $tags[] = '<link rel="stylesheet" href="' . self::BASE_URL . $css . '">';
            }
        }

        $tags[] = '<script type="module"' . $nonce . ' src="' . self::BASE_URL . $chunk['file'] . '"></script>';
        return implode("\n", $tags);
    }

    private static function getManifestJSON(): array
    {
        $path = dirname(__DIR__) . '/public/dist/.vite/manifest.json';

        if (!file_exists($path)) {
            throw new RuntimeException("Manifest vite absent. Pensez à exécuter npm run build.");
        }

        return json_decode(file_get_contents($path), true);
    }
}
