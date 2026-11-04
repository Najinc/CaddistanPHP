<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Mail\DevMailbox;
use App\Mail\Mail;

final class DevMailboxTest extends TestCase
{
    private string $directory;
    private DevMailbox $mailbox;

    public function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/caddistan-mails-' . bin2hex(random_bytes(4));
        $this->mailbox = new DevMailbox($this->directory);
    }

    public function test_le_dernier_mail_envoye_est_en_premier(): void
    {
        $this->mailbox->store(new Mail('alice@caddistan.test', 'Premier', 'Corps', sentAt: 1000.0));
        $this->mailbox->store(new Mail('bob@caddistan.test', 'Second', 'Corps', '/connexion/lien?x=1', 'Se connecter', 2000.0));

        $mails = $this->mailbox->latest();

        $this->assertSame(['Second', 'Premier'], array_map(fn (Mail $mail) => $mail->subject, $mails));
        $this->assertSame('/connexion/lien?x=1', $mails[0]->link);
        $this->assertSame(null, $mails[1]->link);
    }

    public function test_les_mails_sont_stockes_en_json(): void
    {
        $this->mailbox->store(new Mail('alice@caddistan.test', 'Sujet', 'Corps', sentAt: 1000.0));

        $files = glob($this->directory . '/*.json');
        $this->assertSame(1, count($files));
        $this->assertSame('Sujet', json_decode(file_get_contents($files[0]), true)['subject']);
    }

    public function test_seuls_les_50_derniers_sont_gardes(): void
    {
        for ($i = 1; $i <= 52; $i++) {
            $this->mailbox->store(new Mail('alice@caddistan.test', 'Mail ' . $i, 'Corps', sentAt: 1000.0 + $i));
        }

        $mails = $this->mailbox->latest();

        $this->assertSame(50, count($mails));
        $this->assertSame('Mail 52', $mails[0]->subject);
        $this->assertSame('Mail 3', $mails[49]->subject);
    }

    public function test_vider_la_boite(): void
    {
        $this->mailbox->store(new Mail('alice@caddistan.test', 'Sujet', 'Corps', sentAt: 1000.0));
        $this->mailbox->clear();

        $this->assertSame([], $this->mailbox->latest());
    }
}
