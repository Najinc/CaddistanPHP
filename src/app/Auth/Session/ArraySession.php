<?php

declare(strict_types=1);

namespace App\Auth\Session;

/**
 * Session en mémoire, pour les tests unitaires (session_start() n'est pas
 * utilisable dans un script en ligne de commande qui affiche déjà du texte).
 */
final class ArraySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];
    private string $id;

    public function __construct()
    {
        $this->id = self::newId();
    }

    public function start(): void
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->id = self::newId();
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->id = self::newId();
    }

    public function expiredDueToInactivity(): bool
    {
        return false;
    }

    private static function newId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
