<?php

namespace App\Core;

use RuntimeException;

/**
 * Moteur de vues PHP natif, avec échappement PAR DÉFAUT (comme Blade / Twig).
 * Les vues sont dans /views, rendues dans un layout (views/layouts/app.php par défaut).
 *
 * Syntaxe dans les vues :
 *   {{ $produit['name'] }}     => échappé : <?= e($produit['name']) ?>
 *   {!! partial('header') !!}  => brut, À DEMANDER EXPLICITEMENT (HTML de confiance uniquement)
 *   <?= ... ?>                 => interdit : la compilation échoue (impossible d'oublier l'échappement)
 *
 * Les vues sont « compilées » en PHP classique dans storage/cache/views/ puis exécutées.
 */
final class View
{
    private const VIEWS_PATH = __DIR__ . '/../../views/';
    private const CACHE_PATH = __DIR__ . '/../../storage/cache/views/';

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::renderFile($template, $data);

        if ($layout === null) {
            return $content;
        }

        return self::renderFile($layout, [...$data, 'content' => $content]);
    }

    /** Rendu d'un fragment (utilisé par la fonction partial()). */
    public static function renderFile(string $template, array $data = []): string
    {
        $file = self::VIEWS_PATH . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("Vue \"$template\" introuvable.");
        }

        $compiled = self::compile($file);

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $compiled;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /** Traduit {{ }} / {!! !!} en PHP et met le résultat en cache (recompilé si la vue a changé). */
    private static function compile(string $file): string
    {
        $compiled = self::CACHE_PATH . md5($file) . '.php';

        if (is_file($compiled) && filemtime($compiled) >= filemtime($file)) {
            return $compiled;
        }

        $source = (string) file_get_contents($file);

        if (str_contains($source, '<?=')) {
            throw new RuntimeException("Vue « $file » : <?= ?> est interdit, utilisez {{ }} (échappé) ou {!! !!} (brut).");
        }

        $php = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?= $1 ?>', $source);
        $php = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?= e($1) ?>', $php);

        if (!is_dir(self::CACHE_PATH)) {
            mkdir(self::CACHE_PATH, 0775, true);
        }
        file_put_contents($compiled, $php, LOCK_EX);

        return $compiled;
    }
}
