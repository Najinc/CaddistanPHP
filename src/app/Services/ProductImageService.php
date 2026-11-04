<?php

namespace App\Services;

use finfo;
use RuntimeException;

/**
 * Enregistrement des images produits : upload depuis le poste ou import depuis une URL.
 */
final class ProductImageService
{
    /** Upload depuis le poste : stocké HORS du webroot (storage/), servi uniquement par MediaController. */
    public const STORAGE_DIR = __DIR__ . '/../../storage/uploads/products/';
    private const MEDIA_URL = '/media/produits/';

    /** Taille maximale d'une image : 2 Mo. */
    public const MAX_SIZE = 2 * 1024 * 1024;

    /** Liste blanche : type MIME réel => extension qu'on donnera au fichier. */
    public const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Import par URL (non concerné par l'upload, cf. partie anti-SSRF). */
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

        return $this->processAndStoreFile($tmp);
    }

    public function storeFromUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['scheme'], $parsedUrl['host'])) {
            throw new RuntimeException('URL invalide.');
        }

        $scheme = strtolower($parsedUrl['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Schéma non autorisé : seuls HTTP et HTTPS sont acceptés.');
        }

        $host = $parsedUrl['host'];

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = dns_get_record($host, DNS_A + DNS_AAAA);
            if (empty($resolved)) {
                throw new RuntimeException('Impossible de résoudre le nom de domaine de l\'URL distante.');
            }
            foreach ($resolved as $record) {
                if (isset($record['ip'])) $ips[] = $record['ip'];
                if (isset($record['ipv6'])) $ips[] = $record['ipv6'];
            }
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Adresse IP interdite (réseau privé ou réservé).');
            }

            if (str_starts_with($ip, '169.254.') || str_starts_with($ip, 'fe80:')) {
                throw new RuntimeException('Adresse IP link-local / métadonnées cloud interdite.');
            }
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_MAXFILESIZE    => self::MAX_SIZE,
        ]);

        $content = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($content === false || $httpCode !== 200) {
            throw new RuntimeException('Impossible de récupérer l\'image distante (HTTP ' . $httpCode . ') : ' . $curlError);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'img_url_');
        if ($tmpFile === false || file_put_contents($tmpFile, $content) === false) {
            throw new RuntimeException('Erreur lors du traitement du fichier temporaire.');
        }

        try {
            return $this->processAndStoreFile($tmpFile);
        } finally {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * Traitement mutualisé : Vérification MIME (finfo), taille, renommage sécurisé et stockage.
     */
    private function processAndStoreFile(string $filePath): string
    {
        if (filesize($filePath) > self::MAX_SIZE) {
            throw new RuntimeException('Image trop lourde (2 Mo maximum).');
        }

        // Type MIME réel via les « magic bytes »
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);

        $extension = self::ALLOWED_TYPES[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Format refusé : seuls les fichiers JPG, PNG et WebP sont acceptés.');
        }

        // 4. Nom aléatoire (32 caractères hexa) + extension déduite du type réel, jamais du nom envoyé
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        if (!is_dir(self::STORAGE_DIR)) {
            mkdir(self::STORAGE_DIR, 0775, true);
        }

        // Si c'est un fichier temporaire issu d'un upload ou d'un téléchargement
        $moved = is_uploaded_file($filePath)
            ? move_uploaded_file($filePath, self::STORAGE_DIR . $filename)
            : rename($filePath, self::STORAGE_DIR . $filename);

        if (!$moved) {
            throw new RuntimeException('Impossible d\'enregistrer le fichier.');
        }

        return self::MEDIA_URL . $filename;
    }
}