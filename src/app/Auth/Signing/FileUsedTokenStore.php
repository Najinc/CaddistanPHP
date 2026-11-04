<?php

declare(strict_types=1);

namespace App\Auth\Signing;

final class FileUsedTokenStore implements UsedTokenStoreInterface
{
    public function __construct(
        private readonly string $directory = __DIR__ . '/../../../storage/used-tokens',
        private readonly ?\Closure $clock = null,
    ) {
    }

    public function markUsed(string $token, int $expiresAt): bool
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        $this->forgetExpired();

        $handle = @fopen($this->directory . '/' . hash('sha256', $token), 'x');
        if ($handle === false) {
            return false;
        }

        fwrite($handle, (string) $expiresAt);
        fclose($handle);

        return true;
    }

    private function forgetExpired(): void
    {
        $now = $this->clock !== null ? ($this->clock)() : time();

        foreach (glob($this->directory . '/*') ?: [] as $file) {
            $expiresAt = @file_get_contents($file);
            if ($expiresAt !== false && $expiresAt !== '' && (int) $expiresAt < $now) {
                @unlink($file);
            }
        }
    }
}
