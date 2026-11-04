<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Exception\InvalidSignatureException;
use App\Auth\Signing\FileUsedTokenStore;
use App\Auth\Signing\MagicLink;
use App\Auth\Signing\UrlSigner;
use Tests\Auth\Support\FakeUser;
use Tests\Auth\Support\InMemoryUserProvider;

final class FileUsedTokenStoreTest extends TestCase
{
    private string $directory;
    private int $now = 1_800_000_000;
    private FileUsedTokenStore $store;

    public function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/caddistan-used-tokens-' . bin2hex(random_bytes(4));
        $this->store = new FileUsedTokenStore($this->directory, fn (): int => $this->now);
    }

    public function test_un_jeton_ne_sert_qu_une_fois(): void
    {
        $this->assertTrue($this->store->markUsed('nonce-a', $this->now + 900));
        $this->assertFalse($this->store->markUsed('nonce-a', $this->now + 900));
        $this->assertTrue($this->store->markUsed('nonce-b', $this->now + 900));
    }

    public function test_le_nom_du_fichier_ne_contient_pas_le_jeton(): void
    {
        $this->store->markUsed('../../secret', $this->now + 900);

        $this->assertSame([hash('sha256', '../../secret')], array_map('basename', glob($this->directory . '/*')));
    }

    public function test_les_jetons_expires_sont_oublies(): void
    {
        $this->store->markUsed('ancien', $this->now + 900);
        $this->now += 901;
        $this->store->markUsed('nouveau', $this->now + 900);

        $this->assertSame([hash('sha256', 'nouveau')], array_map('basename', glob($this->directory . '/*')));
    }

    public function test_un_magic_link_reel_ne_sert_qu_une_fois(): void
    {
        $users = new InMemoryUserProvider();
        $alice = new FakeUser(1, 'alice@caddistan.test', '');
        $users->add($alice);
        $magic = new MagicLink(new UrlSigner('cle-de-test-de-32-octets-minimum!!', fn (): int => $this->now), $this->store, $users);

        $link = $magic->create($alice);
        $this->assertSame(1, $magic->consume($link)->getAuthId());

        $e = $this->expectThrows(InvalidSignatureException::class, fn () => $magic->consume($link));
        $this->assertSame('already_used', $e->reason);
    }
}
