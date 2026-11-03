<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Session\CookiePolicy;

final class CookiePolicyTest extends TestCase
{
    public function test_par_defaut_httponly_secure_samesite_lax_et_prefixe_host(): void
    {
        $policy = new CookiePolicy();

        $this->assertSame('__Host-panier', $policy->name('panier'));
        $this->assertSame(
            ['expires' => 0, 'path' => '/', 'domain' => '', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'],
            $policy->options(),
        );
    }

    public function test_sans_https_pas_de_prefixe_host(): void
    {
        $this->assertSame('panier', (new CookiePolicy(secure: false))->name('panier'));
    }

    public function test_samesite_none_interdit(): void
    {
        $this->expectThrows(\InvalidArgumentException::class, fn () => new CookiePolicy(sameSite: 'None'));
    }

    public function test_lecture_d_un_cookie_avec_prefixe(): void
    {
        $policy = new CookiePolicy();

        $this->assertSame('abc', $policy->read(['__Host-panier' => 'abc'], 'panier'));
        $this->assertSame(null, $policy->read(['panier' => 'abc'], 'panier'), 'un cookie sans préfixe a pu être posé par un sous-domaine');
        $this->assertSame(null, $policy->read(['__Host-panier' => ['abc']], 'panier'));
    }
}
