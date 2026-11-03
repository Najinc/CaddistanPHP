<?php

namespace App\Services;

use RuntimeException;

/**
 * Enregistrement des images produits : upload depuis le poste ou import depuis une URL.
 * Les fichiers sont stockés dans public/uploads/products/.
 */
final class ProductImageService
{
    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/products/';
    private const PUBLIC_PATH = '/uploads/products/';

    /** @param array $file entrée de $_FILES */
    public function storeUpload(array $file): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Échec de l\'upload (code ' . $file['error'] . ').');
        }

        $filename = $file['name'];

        if (!move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $filename)) {
            throw new RuntimeException('Impossible d\'enregistrer le fichier.');
        }

        return self::PUBLIC_PATH . $filename;
    }

    /** Télécharge une image distante et l'enregistre localement. */
    public function storeFromUrl(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $content = curl_exec($ch);

        if ($content === false) {
            throw new RuntimeException('Impossible de récupérer l\'image distante : ' . curl_error($ch));
        }

        $filename = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'image';
        file_put_contents(self::UPLOAD_DIR . $filename, $content);

        return self::PUBLIC_PATH . $filename;
    }
}
