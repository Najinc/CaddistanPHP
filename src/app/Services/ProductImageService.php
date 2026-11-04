<?php

namespace App\Services;

use finfo;
use RuntimeException;

/**
 * Enregistrement des images produits : upload depuis le poste ou import depuis une URL.
 * Les fichiers sont stockés dans public/uploads/products/.
 */
final class ProductImageService
{
    /** Taille maximale d'une image : 2 Mo. */
    public const MAX_SIZE = 2 * 1024 * 1024;

    /** Liste blanche : type MIME réel => extension qu'on donnera au fichier. */
    public const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/products/';
    private const PUBLIC_PATH = '/uploads/products/';

    /**
     * Upload sécurisé d'une image.
     * On ne fait JAMAIS confiance à ce que le client annonce : ni au nom du fichier, ni à son extension,
     * ni à $_FILES['type'] (tous choisis librement par le client).
     *
     * @param array $file entrée de $_FILES
     */
    public function storeUpload(array $file): string
    {
        $error = $file['error'] ?? null;
        $tmp   = $file['tmp_name'] ?? null;

        // 1. Taille max : refusée par PHP (php.ini) ou par notre limite
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('Image trop lourde (2 Mo maximum).');
        }
        if ($error !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Échec de l\'upload.');
        }
        if (filesize($tmp) > self::MAX_SIZE) {
            throw new RuntimeException('Image trop lourde (2 Mo maximum).');
        }

        // 2. Type MIME réel, lu dans le CONTENU du fichier (signature / « magic bytes ») avec finfo
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);

        // 3. Liste blanche jpg / png / webp
        $extension = self::ALLOWED_TYPES[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Format refusé : seuls les fichiers JPG, PNG et WebP sont acceptés.');
        }

        // 4. Nom aléatoire (32 caractères hexa) + extension déduite du type réel, jamais du nom envoyé
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        if (!move_uploaded_file($tmp, self::UPLOAD_DIR . $filename)) {
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
