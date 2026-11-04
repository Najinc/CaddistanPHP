<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\PasswordHasher;

final class PasswordHasherTest extends TestCase
{
    private const FAST = ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1];

    public function test_le_hash_est_en_argon2id_et_sale(): void
    {
        $hasher = new PasswordHasher(self::FAST);

        $a = $hasher->hash('caddy-secret');
        $b = $hasher->hash('caddy-secret');

        $this->assertTrue(str_starts_with($a, '$argon2id$'), 'algorithme Argon2id attendu');
        $this->assertNotSame($a, $b, 'deux hash du même mot de passe doivent différer (sel aléatoire)');
    }

    public function test_verifie_le_bon_mot_de_passe_et_refuse_les_autres(): void
    {
        $hasher = new PasswordHasher(self::FAST);
        $hash = $hasher->hash('caddy-secret');

        $this->assertTrue($hasher->verify('caddy-secret', $hash));
        $this->assertFalse($hasher->verify('caddy-secreT', $hash));
        $this->assertFalse($hasher->verify('', $hash));
    }

    public function test_needs_rehash_detecte_un_ancien_bcrypt(): void
    {
        $hasher = new PasswordHasher(self::FAST);
        $legacy = password_hash('caddy-secret', PASSWORD_BCRYPT, ['cost' => 4]);

        $this->assertTrue($hasher->needsRehash($legacy));
    }

    public function test_needs_rehash_detecte_des_parametres_plus_faibles(): void
    {
        $weak = (new PasswordHasher(self::FAST))->hash('caddy-secret');
        $stronger = new PasswordHasher(['memory_cost' => 2048, 'time_cost' => 2, 'threads' => 1]);

        $this->assertTrue($stronger->needsRehash($weak));
        $this->assertFalse($stronger->needsRehash($stronger->hash('caddy-secret')));
    }

    public function test_les_parametres_par_defaut_sont_ceux_de_php(): void
    {
        $hash = (new PasswordHasher())->hash('caddy-secret');

        $this->assertTrue(str_contains($hash, 'm=' . PASSWORD_ARGON2_DEFAULT_MEMORY_COST . ',t=' . PASSWORD_ARGON2_DEFAULT_TIME_COST));
    }
}
