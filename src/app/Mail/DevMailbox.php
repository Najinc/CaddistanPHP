<?php

declare(strict_types=1);

namespace App\Mail;

final class DevMailbox
{
    private const KEEP = 50;

    public function __construct(private readonly string $directory = __DIR__ . '/../../storage/mails')
    {
    }

    public function store(Mail $mail): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        $file = sprintf('%s/%.6F-%s.json', $this->directory, $mail->sentAt, bin2hex(random_bytes(4)));
        $json = json_encode($mail->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($file, $json, LOCK_EX);

        foreach (array_slice($this->files(), self::KEEP) as $old) {
            @unlink($old);
        }
    }

    public function latest(): array
    {
        $mails = [];
        foreach ($this->files() as $file) {
            $data = json_decode((string) @file_get_contents($file), true);
            if (is_array($data)) {
                $mails[] = Mail::fromArray($data);
            }
        }

        return $mails;
    }

    public function clear(): void
    {
        foreach ($this->files() as $file) {
            @unlink($file);
        }
    }

    private function files(): array
    {
        $files = glob($this->directory . '/*.json') ?: [];
        rsort($files, SORT_STRING);

        return $files;
    }
}
