<?php

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Response;

/**
 * Téléchargement des fiches techniques stockées hors du dossier public.
 */
final class DocumentController extends Controller
{
    private const DOCUMENTS_DIR = __DIR__ . '/../../storage/documents/';

    public function download(string $filename): Response
    {
        $path = self::DOCUMENTS_DIR . $filename;

        if (!is_file($path)) {
            throw HttpException::notFound('Document introuvable.');
        }

        return Response::download($path, basename($path), 'text/plain; charset=UTF-8');
    }
}
