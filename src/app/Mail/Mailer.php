<?php

declare(strict_types=1);

namespace App\Mail;

final class Mailer
{
    private static ?DevMailbox $mailbox = null;

    public static function enabled(): bool
    {
        return env('APP_ENV', 'production') !== 'production';
    }

    public static function send(string $to, string $subject, string $body, ?string $link = null, ?string $linkLabel = null): void
    {
        if (self::enabled()) {
            self::mailbox()->store(new Mail($to, $subject, $body, $link, $linkLabel, microtime(true)));
        }
    }

    public static function mailbox(): DevMailbox
    {
        return self::$mailbox ??= new DevMailbox();
    }
}
