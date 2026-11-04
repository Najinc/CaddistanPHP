<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\SafeRedirect;

final class SafeRedirectTest extends TestCase
{
    public function test_les_chemins_locaux_sont_acceptes(): void
    {
        foreach (['/', '/compte', '/commandes/12?onglet=details', '/caddies#avis'] as $path) {
            $this->assertSame($path, SafeRedirect::target($path));
        }
    }

    public function test_les_redirections_vers_un_autre_site_sont_refusees(): void
    {
        $attacks = [
            'https://evil.example',
            'http://evil.example/compte',
            '//evil.example',
            '/\\evil.example',
            '\\\\evil.example',
            "/\t/evil.example",
            "/\n/evil.example",
            'javascript:alert(1)',
            'evil.example',
            '',
        ];

        foreach ($attacks as $attack) {
            $this->assertSame('/', SafeRedirect::target($attack), 'accepté à tort : ' . json_encode($attack));
        }
    }

    public function test_un_tableau_injecte_est_refuse(): void
    {
        $this->assertSame('/compte', SafeRedirect::target(['/admin'], '/compte'));
        $this->assertSame('/compte', SafeRedirect::target(null, '/compte'));
    }
}
