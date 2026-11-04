<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Controllers\DocumentController;
use App\Core\HttpException;
use App\Core\Request;

final class PathTraversalTest extends TestCase
{
    private DocumentController $documents;

    public function setUp(): void
    {
        $this->documents = new DocumentController(new Request('GET', '/documents', [], [], [], []));
    }

    public function test_une_fiche_technique_existante_est_telechargee(): void
    {
        $this->assertSame(200, $this->documents->download('caddy-classic-100l.txt')->status());
    }

    public function test_sortir_du_dossier_des_documents_repond_404(): void
    {
        $attacks = [
            '../../composer.json',
            '..\\..\\composer.json',
            '../documents/../../composer.json',
            '/etc/passwd',
            '..',
            '.',
            '',
            "caddy-classic-100l.txt\0.pdf",
        ];

        foreach ($attacks as $attack) {
            $e = $this->expectThrows(HttpException::class, fn () => $this->documents->download($attack));
            $this->assertSame(404, $e->status, 'accepté à tort : ' . json_encode($attack));
        }
    }

    public function test_seul_le_nom_du_fichier_est_garde(): void
    {
        // Le chemin est ignoré : on retombe sur une vraie fiche du dossier, donc rien ne fuit.
        $this->assertSame(200, $this->documents->download('../../caddy-classic-100l.txt')->status());
    }
}
