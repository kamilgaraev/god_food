<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests;

use Theobroma\Commerce\Infrastructure\MailTransport;

final class MailTransportTest extends TestCase
{
    public function testConfiguresSmtpAndSenderFromEnvironmentSettings(): void
    {
        $transport = new MailTransport([
            'host' => 'mailpit',
            'port' => '1025',
            'username' => '',
            'password' => '',
            'encryption' => '',
            'from_address' => 'shop@example.test',
            'from_name' => 'Theobroma QA',
        ]);
        $mailer = new FakeMailer();

        $transport->configure($mailer);

        $this->assertTrue($transport->enabled());
        $this->assertTrue($mailer->smtp);
        $this->assertSame('mailpit', $mailer->Host);
        $this->assertSame(1025, $mailer->Port);
        $this->assertSame(false, $mailer->SMTPAuth);
        $this->assertSame('', $mailer->SMTPSecure);
        $this->assertSame('shop@example.test', $transport->fromAddress('wordpress@example.test'));
        $this->assertSame('Theobroma QA', $transport->fromName('WordPress'));
    }

    public function testLeavesMailerUntouchedWithoutAnSmtpHost(): void
    {
        $transport = new MailTransport(['host' => '']);
        $mailer = new FakeMailer();

        $transport->configure($mailer);

        $this->assertSame(false, $transport->enabled());
        $this->assertSame(false, $mailer->smtp);
        $this->assertSame('wordpress@example.test', $transport->fromAddress('wordpress@example.test'));
        $this->assertSame('WordPress', $transport->fromName('WordPress'));
    }
}

final class FakeMailer
{
    public bool $smtp = false;
    public string $Host = '';
    public int $Port = 0;
    public bool $SMTPAuth = false;
    public string $Username = '';
    public string $Password = '';
    public string $SMTPSecure = '';

    public function isSMTP(): void
    {
        $this->smtp = true;
    }
}
