<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Exception\InvalidSignatureException;
use App\Auth\Signing\InMemoryUsedTokenStore;
use App\Auth\Signing\MagicLink;
use App\Auth\Signing\UrlSigner;
use Tests\Auth\Support\FakeUser;
use Tests\Auth\Support\InMemoryUserProvider;

final class MagicLinkTest extends TestCase
{
    private const KEY = 'cle-de-test-de-32-octets-minimum!!';

    private int $now = 1_800_000_000;
    private InMemoryUserProvider $users;
    private InMemoryUsedTokenStore $store;
    private UrlSigner $signer;
    private MagicLink $magic;
    private FakeUser $alice;

    public function setUp(): void
    {
        $this->users = new InMemoryUserProvider();
        $this->alice = new FakeUser(1, 'alice@caddistan.test', '');
        $this->users->add($this->alice);
        $this->store = new InMemoryUsedTokenStore();
        $this->signer = new UrlSigner(self::KEY, fn (): int => $this->now);
        $this->magic = new MagicLink($this->signer, $this->store, $this->users);
    }

    public function test_le_lien_connecte_le_bon_utilisateur(): void
    {
        $link = $this->magic->create($this->alice);

        $this->assertTrue(str_starts_with($link, '/connexion/lien?user=1&nonce='));
        $this->assertSame(1, $this->magic->consume($link)->getAuthId());
    }

    public function test_le_lien_ne_sert_qu_une_fois(): void
    {
        $link = $this->magic->create($this->alice);
        $this->magic->consume($link);

        $e = $this->expectThrows(InvalidSignatureException::class, fn () => $this->magic->consume($link));
        $this->assertSame('already_used', $e->reason);
    }

    public function test_le_lien_expire_apres_15_minutes(): void
    {
        $link = $this->magic->create($this->alice);
        $this->now += 900;

        $e = $this->expectThrows(InvalidSignatureException::class, fn () => $this->magic->consume($link));
        $this->assertSame('expired', $e->reason);
    }

    public function test_changer_l_utilisateur_dans_le_lien_est_refuse(): void
    {
        $this->users->add(new FakeUser(2, 'admin@caddistan.test', '', 'admin'));
        $link = $this->magic->create($this->alice);

        $e = $this->expectThrows(InvalidSignatureException::class, fn () => $this->magic->consume(str_replace('user=1', 'user=2', $link)));
        $this->assertSame('invalid', $e->reason);
    }

    public function test_une_url_signee_pour_une_autre_page_ne_connecte_pas(): void
    {
        // Même paramètres, signature valide, mais pour un autre chemin.
        $other = $this->signer->sign('/commandes/12/facture?user=1&nonce=' . str_repeat('a', 32), 900);

        $e = $this->expectThrows(InvalidSignatureException::class, fn () => $this->magic->consume($other));
        $this->assertSame('invalid', $e->reason);
    }

    public function test_compte_supprime_entre_temps_refuse(): void
    {
        $link = $this->magic->create($this->alice);
        $this->users->delete(1);

        $this->expectThrows(InvalidSignatureException::class, fn () => $this->magic->consume($link));
    }
}
