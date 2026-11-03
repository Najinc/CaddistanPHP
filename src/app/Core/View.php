<?php

namespace App\Core;

use RuntimeException;

/**
 * Moteur de vues PHP natif.
 * Les vues sont dans /views, rendues dans un layout (views/layouts/app.php par défaut).
 */
final class View
{
    private const VIEWS_PATH = __DIR__ . '/../../views/';

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

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
