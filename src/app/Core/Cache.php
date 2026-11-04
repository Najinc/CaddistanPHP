<?php

namespace App\Core;

/**
 * Cache clé / valeur sur disque, partagé entre tous les workers PHP-FPM (storage/cache/data/, hors webroot).
 *
 *   $cache->remember('categories', 600, fn () => $repo->all());
 *   $hits = $cache->increment('ratelimit:login:1.2.3.4', ttl: 900);   // atomique (flock)
 *
 * Choix de sécurité :
 *  - Les valeurs sont stockées en JSON, jamais avec serialize() : un unserialize() sur un fichier modifié
 *    permettrait d'instancier des objets arbitraires (injection d'objet PHP).
 *  - Le nom de fichier est le SHA-256 de la clé : une clé qui contient un e-mail ou « ../ » ne peut ni
 *    sortir du dossier ni révéler la donnée en listant le répertoire.
 *  - Ne jamais y mettre une donnée propre à un utilisateur sous une clé partagée (fuite entre comptes).
 */
final class Cache
{
    public function __construct(
        private readonly string $directory = __DIR__ . '/../../storage/cache/data',
        private readonly ?\Closure $clock = null,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->read($this->path($key));

        return $entry === null ? $default : $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->ensureDirectory();
        file_put_contents($this->path($key), $this->encode($value, $ttl), LOCK_EX);
    }

    /** Renvoie la valeur en cache, ou la calcule, la stocke et la renvoie. */
    public function remember(string $key, int $ttl, callable $compute): mixed
    {
        $entry = $this->read($this->path($key));

        if ($entry !== null) {
            return $entry['value'];
        }

        $value = $compute();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function forget(string $key): void
    {
        @unlink($this->path($key));
    }

    /**
     * Incrémente un compteur de façon atomique et renvoie la nouvelle valeur.
     * Le verrou exclusif (flock) garantit que 50 requêtes simultanées donnent bien 50 : sans lui,
     * deux workers liraient « 4 » en même temps et écriraient tous les deux « 5 » (contournement du rate limit).
     * Le TTL n'est fixé qu'à la création du compteur (fenêtre fixe : il ne glisse pas à chaque hit).
     */
    public function increment(string $key, int $ttl): int
    {
        $this->ensureDirectory();
        $handle = fopen($this->path($key), 'c+');

        if ($handle === false) {
            throw new \RuntimeException('Cache : impossible d\'ouvrir le compteur.');
        }

        try {
            flock($handle, LOCK_EX);

            $entry = $this->decode((string) stream_get_contents($handle));
            $count = $entry !== null ? (int) $entry['value'] + 1 : 1;
            $expiresAt = $entry['expires_at'] ?? $this->now() + $ttl;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode(['expires_at' => $expiresAt, 'value' => $count]));
            fflush($handle);

            return $count;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Supprime les entrées expirées (appelé de temps en temps pour ne pas accumuler de fichiers). */
    public function prune(): int
    {
        $deleted = 0;

        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            if ($this->read($file) === null && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }

    /** @return array{expires_at: int, value: mixed}|null  null si absent, illisible ou expiré */
    private function read(string $file): ?array
    {
        $raw = @file_get_contents($file);

        return $raw === false ? null : $this->decode($raw);
    }

    private function decode(string $raw): ?array
    {
        $entry = json_decode($raw, true);

        if (!is_array($entry) || !is_int($entry['expires_at'] ?? null) || !array_key_exists('value', $entry)) {
            return null;
        }

        return $entry['expires_at'] > $this->now() ? $entry : null;
    }

    private function encode(mixed $value, int $ttl): string
    {
        return json_encode(['expires_at' => $this->now() + $ttl, 'value' => $value], JSON_THROW_ON_ERROR);
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
