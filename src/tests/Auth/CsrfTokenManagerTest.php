<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Csrf\CsrfTokenManager;
use App\Auth\Exception\CsrfTokenMismatchException;
use Tests\Auth\Support\ArraySession;

final class CsrfTokenManagerTest extends TestCase
{
    private CsrfTokenManager $csrf;

    public function setUp(): void
    {
        $this->csrf = new CsrfTokenManager(new ArraySession());
    }

    public function test_le_token_fait_32_octets_aleatoires_et_reste_stable_dans_la_session(): void
    {
        $token = $this->csrf->token();

        $this->assertMatches('/\A[0-9a-f]{64}\z/', $token);
        $this->assertSame($token, $this->csrf->token());
    }

    public function test_get_head_options_passent_sans_token(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS', 'get'] as $method) {
            $this->csrf->verify($method, null);
        }
        $this->assertTrue(true);
    }

    public function test_post_put_patch_delete_sans_token_sont_refuses(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'post', 'PROPFIND'] as $method) {
            $this->expectThrows(CsrfTokenMismatchException::class, fn () => $this->csrf->verify($method, null));
        }
    }

    public function test_token_faux_vide_ou_tableau_refuse_en_403(): void
    {
        $this->csrf->token();

        foreach (['faux', '', [$this->csrf->token()], 123] as $submitted) {
            $e = $this->expectThrows(CsrfTokenMismatchException::class, fn () => $this->csrf->verify('POST', $submitted));
            $this->assertSame(403, $e->status);
        }
    }

    public function test_bon_token_accepte(): void
    {
        $this->csrf->verify('DELETE', $this->csrf->token());
        $this->assertTrue(true);
    }

    public function test_sans_token_en_session_tout_post_est_refuse(): void
    {
        $this->expectThrows(CsrfTokenMismatchException::class, fn () => $this->csrf->verify('POST', str_repeat('a', 64)));
    }

    public function test_regenerer_invalide_l_ancien_token(): void
    {
        $old = $this->csrf->token();
        $this->csrf->regenerate();

        $this->expectThrows(CsrfTokenMismatchException::class, fn () => $this->csrf->verify('POST', $old));
        $this->csrf->verify('POST', $this->csrf->token());
    }

    public function test_champ_cache(): void
    {
        $this->assertSame('<input type="hidden" name="_csrf" value="' . $this->csrf->token() . '">', $this->csrf->field());
    }
}
