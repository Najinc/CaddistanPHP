<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Exception\InvalidSignatureException;
use App\Auth\Signing\UrlSigner;

final class UrlSignerTest extends TestCase
{
    private const KEY = 'cle-de-test-de-32-octets-minimum!!';
    private const NOW = 1_800_000_000;

    private UrlSigner $signer;

    public function setUp(): void
    {
        $this->signer = new UrlSigner(self::KEY, fn (): int => self::NOW);
    }

    public function test_une_url_signee_est_acceptee(): void
    {
        $url = $this->signer->sign('/commandes/12/facture?format=pdf', 3600);

        $this->assertMatches('#\A/commandes/12/facture\?format=pdf&expires=1800003600&signature=[0-9a-f]{64}\z#', $url);
        $this->assertSame(['format' => 'pdf', 'expires' => '1800003600'], $this->signer->verify($url));
    }

    public function test_modifier_un_parametre_invalide_la_signature(): void
    {
        $url = $this->signer->sign('/commandes/12/facture?format=pdf', 3600);

        $this->assertRejected(str_replace('format=pdf', 'format=html', $url), 'invalid');
    }

    public function test_modifier_le_chemin_invalide_la_signature(): void
    {
        $url = $this->signer->sign('/commandes/12/facture', 3600);

        $this->assertRejected(str_replace('/12/', '/13/', $url), 'invalid');
    }

    public function test_repousser_l_expiration_invalide_la_signature(): void
    {
        $url = $this->signer->sign('/commandes/12/facture', 3600);

        $this->assertRejected(str_replace('expires=1800003600', 'expires=1900000000', $url), 'invalid');
    }

    public function test_ajouter_un_parametre_invalide_la_signature(): void
    {
        $url = $this->signer->sign('/commandes/12/facture', 3600);

        $this->assertRejected($url . '&admin=1', 'invalid');
    }

    public function test_parametre_en_double_refuse(): void
    {
        $url = $this->signer->sign('/commandes/12/facture?format=pdf', 3600);

        $this->assertRejected($url . '&format=html', 'invalid');
    }

    public function test_un_lien_expire_est_refuse(): void
    {
        $url = $this->signer->sign('/commandes/12/facture', 60);
        $later = new UrlSigner(self::KEY, fn (): int => self::NOW + 60);

        $this->assertRejected($url, 'expired', $later);
    }

    public function test_sans_signature_refuse(): void
    {
        $this->assertRejected('/commandes/12/facture', 'missing');
        $this->assertRejected('/commandes/12/facture?expires=1900000000', 'missing');
    }

    public function test_signature_d_une_autre_cle_refusee(): void
    {
        $other = new UrlSigner('une-autre-cle-tout-aussi-longue-!!!', fn (): int => self::NOW);

        $this->assertRejected($other->sign('/commandes/12/facture', 3600), 'invalid');
    }

    public function test_cle_trop_courte_refusee(): void
    {
        $this->expectThrows(\InvalidArgumentException::class, fn () => new UrlSigner('trop-courte'));
    }

    private function assertRejected(string $url, string $reason, ?UrlSigner $signer = null): void
    {
        $signer ??= $this->signer;
        $e = $this->expectThrows(InvalidSignatureException::class, fn () => $signer->verify($url));

        $this->assertSame($reason, $e->reason);
        $this->assertSame(403, $e->status);
        $this->assertSame('Lien invalide ou expiré.', $e->getMessage());
    }
}
