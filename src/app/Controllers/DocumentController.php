<?php

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Response;

/**
 * Téléchargement des fiches techniques stockées hors du dossier public.
 */
final class DocumentController extends Controller
{
    private const DOCUMENTS_DIR = __DIR__ . '/../../storage/documents';

    public function download(string $filename): Response
    {
        $path = self::safePath($filename) ?? throw HttpException::notFound('Document introuvable.');

        return Response::download($path, basename($path), 'text/plain; charset=UTF-8');
    }

    private static function safePath(string $filename): ?string
    {
        if (str_contains($filename, "\0")) {
            return null;
        }

        $name = basename($filename);

        $dir = realpath(self::DOCUMENTS_DIR);
        $path = realpath(self::DOCUMENTS_DIR . DIRECTORY_SEPARATOR . $name);

        if ($dir === false || $path === false || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return null;
        }

        return $path;
    }
}
