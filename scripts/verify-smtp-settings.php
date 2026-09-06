<?php
// Isolated settings/transport regression: no messages or network requests.
declare(strict_types=1);
require __DIR__ . '/../wp-content/plugins/theobroma-commerce/src/Admin/Settings.php';
require __DIR__ . '/../wp-content/plugins/theobroma-commerce/src/Infrastructure/MailTransport.php';
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['smtp_test_options'] ?? $default; }
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$settings = new Theobroma\Commerce\Admin\Settings();
$input = ['smtp_enabled'=>'yes','smtp_host'=>'smtp.example.test','smtp_port'=>465,'smtp_encryption'=>'ssl','smtp_username'=>'shop','smtp_password'=>' secret with spaces ','smtp_from_address'=>'shop@example.test'];
$saved = $settings->sanitize($input);
check($saved['smtp_enabled'] === 'yes', 'Valid SMTP must enable');
$input['smtp_password'] = '';
check($settings->sanitize($input, $saved)['smtp_password'] === ' secret with spaces ', 'Blank password must preserve exact saved secret');
check($settings->sanitize(array_merge($input, ['smtp_host'=>'https://bad']))['smtp_enabled'] === 'no', 'Invalid host must not enable');
check($settings->sanitize(array_merge($input, ['smtp_from_address'=>'bad']))['smtp_enabled'] === 'no', 'Invalid sender must not enable');
putenv('THEOBROMA_SMTP_HOST=mailpit');
putenv('THEOBROMA_SMTP_PORT=1025');
$GLOBALS['smtp_test_options'] = $saved;
$mailer = new class {
    public $Host, $Port, $Username, $Password, $SMTPAuth, $SMTPSecure;
    public bool $SMTPAutoTLS = true;
    public function isSMTP(): void {}
};
Theobroma\Commerce\Infrastructure\MailTransport::fromEnvironment()->configure($mailer);
check($mailer->Host === 'smtp.example.test' && $mailer->Port === 465 && $mailer->SMTPSecure === 'ssl' && $mailer->SMTPAuth, 'Admin SMTP must override Mailpit');
check($mailer->Password === ' secret with spaces ', 'Transport must preserve secret');
$GLOBALS['smtp_test_options']['smtp_enabled'] = 'no';
Theobroma\Commerce\Infrastructure\MailTransport::fromEnvironment()->configure($mailer);
check($mailer->Host === 'mailpit' && $mailer->Port === 1025, 'Disabled admin SMTP must preserve server fallback');
echo "PASS SMTP: validation, secret preservation, admin priority and environment fallback\n";
