<?php

declare(strict_types=1);

namespace Tests\Robustness;

use App\Core\Response;
use App\Core\SecurityHeaders;
use App\Vite;

final class SecurityHeadersTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        SecurityHeaders::reset();
    }

    public function test_les_cinq_en_tetes_sont_sur_toutes_les_reponses(): void
    {
        $responses = [
            'html'          => Response::html('<p>ok</p>'),
            'json'          => Response::json(['ok' => true]),
            'redirection'   => Response::redirect('/connexion'),
            'erreur'        => Response::html('erreur', 500),
        ];

        foreach ($responses as $type => $response) {
            $headers = $response->headers();
            $this->assertSame('max-age=31536000; includeSubDomains', $headers['Strict-Transport-Security'] ?? null, "HSTS ($type)");
            $this->assertSame('DENY', $headers['X-Frame-Options'] ?? null, "X-Frame-Options ($type)");
            $this->assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null, "nosniff ($type)");
            $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy'] ?? null, "Referrer-Policy ($type)");
            $this->assertTrue(isset($headers['Content-Security-Policy']), "CSP ($type)");
        }
    }

    public function test_la_csp_est_stricte(): void
    {
        $csp = $this->directives(SecurityHeaders::csp());

        $this->assertSame("'self'", $csp['default-src']);
        $this->assertSame("'nonce-" . SecurityHeaders::nonce() . "' 'strict-dynamic'", $csp['script-src']);
        $this->assertSame("'none'", $csp['frame-ancestors']);
        $this->assertSame("'none'", $csp['object-src']);
        $this->assertSame("'none'", $csp['base-uri']);
        $this->assertSame("'self'", $csp['form-action']);

        $all = implode(' ', $csp);
        $this->assertNotContains('unsafe-inline', $all, 'pas de script ou de style inline sans nonce');
        $this->assertNotContains('unsafe-eval', $all);
        $this->assertNotContains('*', $all, 'aucun joker');
    }

    public function test_le_nonce_est_aleatoire_et_change_a_chaque_requete(): void
    {
        $nonce = SecurityHeaders::nonce();

        $this->assertSame(16, strlen((string) base64_decode($nonce, true)), '128 bits');
        $this->assertSame($nonce, SecurityHeaders::nonce(), 'le même pendant toute la requête (en-tête et balises)');

        SecurityHeaders::reset();
        $this->assertFalse($nonce === SecurityHeaders::nonce(), 'nouvelle requête => nouveau nonce');
    }

    public function test_chaque_script_vite_porte_le_nonce_de_l_en_tete(): void
    {
        try {
            $tags = Vite::vite('resources/main.ts');
        } catch (\RuntimeException $e) {
            throw new Skipped('assets Vite non buildés : ' . $e->getMessage());
        }

        $nonce = 'nonce="' . SecurityHeaders::nonce() . '"';
        preg_match_all('/<(script|link rel="modulepreload")\b[^>]*>/', $tags, $matches);

        $this->assertTrue($matches[0] !== [], 'au moins un <script>');
        foreach ($matches[0] as $tag) {
            $this->assertContains($nonce, $tag, 'balise sans nonce : ' . $tag);
        }
        $this->assertContains('<meta property="csp-nonce" ' . $nonce . '>', $tags, 'meta lue par le client Vite');
        $this->assertContains("'nonce-" . SecurityHeaders::nonce() . "'", SecurityHeaders::csp());
    }

    public function test_une_reponse_peut_durcir_un_en_tete(): void
    {
        $response = Response::html('fichier')->withHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        $this->assertSame("default-src 'none'; sandbox", $response->headers()['Content-Security-Policy']);
        $this->assertSame('DENY', $response->headers()['X-Frame-Options'], 'les autres restent en place');
    }

    /** @return array<string, string> "script-src 'x' 'y'" => ['script-src' => "'x' 'y'"] */
    private function directives(string $csp): array
    {
        $result = [];
        foreach (explode(';', $csp) as $directive) {
            [$name, $value] = explode(' ', trim($directive), 2) + [1 => ''];
            $result[$name] = $value;
        }

        return $result;
    }
}
