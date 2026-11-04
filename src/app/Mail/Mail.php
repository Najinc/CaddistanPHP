<?php

declare(strict_types=1);

namespace App\Mail;

final readonly class Mail
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
        public ?string $link = null,
        public ?string $linkLabel = null,
        public float $sentAt = 0.0,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['to'] ?? ''),
            (string) ($data['subject'] ?? ''),
            (string) ($data['body'] ?? ''),
            isset($data['link']) ? (string) $data['link'] : null,
            isset($data['linkLabel']) ? (string) $data['linkLabel'] : null,
            (float) ($data['sentAt'] ?? 0),
        );
    }
}
