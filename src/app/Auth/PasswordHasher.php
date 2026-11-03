<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Hachage des mots de passe en Argon2id.
 *
 * Argon2id est lent et gourmand en mémoire exprès : un attaquant qui vole la
 * base ne peut tester que peu de mots de passe par seconde. Le sel est généré
 * et stocké dans le hash par password_hash() lui-même.
 */
final class PasswordHasher
{
    /** @var array{memory_cost: int, time_cost: int, threads: int} */
    private array $options;

    /**
     * @param array{memory_cost?: int, time_cost?: int, threads?: int} $options
     *        Par défaut ceux de PHP (64 Mio, 4 passes, 1 thread). On ne les baisse que dans les tests.
     */
    public function __construct(array $options = [])
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new \LogicException('Argon2id indisponible : PHP doit être compilé avec --with-password-argon2.');
        }

        $this->options = $options + [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
        ];
    }

    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, $this->options);
    }

    /** Comparaison en temps constant, faite par password_verify(). */
    public function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Vrai si le hash a été fait avec un autre algorithme (ex. un ancien bcrypt)
     * ou d'autres paramètres : on le recalcule au prochain login réussi.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }

    /**
     * À appeler quand l'email n'existe pas : on fait le même travail qu'une
     * vérification (hacher coûte autant que vérifier avec Argon2id). Sans ça,
     * « email inconnu » répond plus vite que « mauvais mot de passe », et on
     * peut deviner quels comptes existent rien qu'en chronométrant.
     */
    public function simulateVerify(#[\SensitiveParameter] string $password): void
    {
        $this->hash($password);
    }
}
