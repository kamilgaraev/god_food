<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Infrastructure;

final class MailTransport
{
    /** @var array<string, string> */
    private array $settings;

    /** @param array<string, string> $settings */
    public function __construct(array $settings)
    {
        $this->settings = array_merge([
            'host' => '',
            'port' => '587',
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
            'from_address' => '',
            'from_name' => '',
        ], $settings);
    }

    public static function fromEnvironment(): self
    {
        $options = function_exists('get_option') ? (array) get_option('theobroma_commerce_settings', []) : [];
        if (($options['smtp_enabled'] ?? 'no') === 'yes' && !empty($options['smtp_host'])) {
            $settings = [];
            foreach (['host', 'port', 'username', 'password', 'encryption', 'from_address', 'from_name'] as $key) {
                $settings[$key] = (string) ($options['smtp_' . $key] ?? '');
            }
            return new self($settings);
        }
        $read = static fn (string $name): string => trim((string) getenv($name));
        return new self([
            'host' => $read('THEOBROMA_SMTP_HOST'),
            'port' => $read('THEOBROMA_SMTP_PORT'),
            'username' => $read('THEOBROMA_SMTP_USERNAME'),
            'password' => $read('THEOBROMA_SMTP_PASSWORD'),
            'encryption' => strtolower($read('THEOBROMA_SMTP_ENCRYPTION')),
            'from_address' => $read('THEOBROMA_MAIL_FROM'),
            'from_name' => $read('THEOBROMA_MAIL_FROM_NAME'),
        ]);
    }

    public function register(): void
    {
        if (!$this->enabled()) {
            return;
        }

        add_action('phpmailer_init', [$this, 'configure']);
        add_filter('wp_mail_from', [$this, 'fromAddress']);
        add_filter('wp_mail_from_name', [$this, 'fromName']);
        add_filter('woocommerce_email_from_address', [$this, 'fromAddress']);
        add_filter('woocommerce_email_from_name', [$this, 'fromName']);
    }

    public function enabled(): bool
    {
        return $this->settings['host'] !== '';
    }

    public function configure(object $mailer): void
    {
        if (!$this->enabled()) {
            return;
        }

        $mailer->isSMTP();
        $mailer->Host = $this->settings['host'];
        $mailer->Port = max(1, min(65535, (int) ($this->settings['port'] ?: '587')));
        $mailer->Username = $this->settings['username'];
        $mailer->Password = $this->settings['password'];
        $mailer->SMTPAuth = $mailer->Username !== '';
        $mailer->SMTPSecure = in_array($this->settings['encryption'], ['ssl', 'tls'], true)
            ? $this->settings['encryption']
            : '';
        if ($mailer->SMTPSecure === '' && property_exists($mailer, 'SMTPAutoTLS')) {
            $mailer->SMTPAutoTLS = false;
        }
    }

    public function fromAddress(string $current): string
    {
        return filter_var($this->settings['from_address'], FILTER_VALIDATE_EMAIL)
            ? $this->settings['from_address']
            : $current;
    }

    public function fromName(string $current): string
    {
        return $this->settings['from_name'] !== '' ? $this->settings['from_name'] : $current;
    }
}
