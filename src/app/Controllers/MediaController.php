<?php

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Response;
use App\Services\ProductImageService;

/**
 * Sert les images uploadées, stockées hors du webroot (storage/uploads/products/).
 * Le serveur web ne peut pas les atteindre directement : tout passe par ici.
 */
final class MediaController extends Controller
{
    public function product(string $filename): Response
    {
        // Seuls les noms générés par l'upload sont acceptés : 32 caractères hexa + extension autorisée
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename, $m)) {
            throw HttpException::notFound();
        }

        $path = ProductImageService::STORAGE_DIR . $filename;
        if (!is_file($path)) {
            throw HttpException::notFound();
        }

        // Content-Type issu de la liste blanche (jamais deviné)
        $mime = array_search($m[1], ProductImageService::ALLOWED_TYPES, true);

        return new Response((string) file_get_contents($path), 200, ['Content-Type' => $mime]);
    }
}
