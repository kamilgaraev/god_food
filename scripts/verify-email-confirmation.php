<?php
// Run with wp eval-file; all created customers are isolated and removed in finally.
use Theobroma\Commerce\Accounts\EmailVerification;
if (!defined('WP_CLI') || !WP_CLI) exit;
$assert = static function ($condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$verification = new EmailVerification();
$ids = [];
$mails = [];
$failMail = false;
$force = static fn () => true;
$mail = static function ($result, $args) use (&$mails, &$failMail) {
    if (!str_contains((string) $args['to'], '@example.test')) throw new RuntimeException('Unexpected email recipient');
    $mails[] = $args;
    return !$failMail;
};
add_filter('theobroma_email_verification_enabled', $force);
add_filter('pre_wp_mail', $mail, 10, 2);
try {
    $email = 'codex-confirm-' . bin2hex(random_bytes(6)) . '@example.test';
    $password = wp_generate_password(24);
    $id = wc_create_new_customer($email, '', $password);
    $assert(!is_wp_error($id), 'Customer creation failed');
    $ids[] = $id;
    $assert($verification->pending($id), 'New account must await confirmation');
    $assert($verification->registrationAuth(true, $id) === false, 'Registration must not log in');
    $assert(is_wp_error(wp_authenticate($email, $password)), 'Pending login must be blocked through WordPress');
    $assert(!$verification->authCookies(true, 0, 0, $id, 'auth', 'test'), 'Pending auth cookies must be blocked');
    $verificationMails = array_values(array_filter($mails, static fn ($m) => str_contains($m['subject'], 'Подтвердите почту')));
    $assert(count($verificationMails) === 1, 'Expected confirmation email');
    preg_match('/confirmation-token=([a-f0-9]{64})/', $verificationMails[0]['message'], $match);
    $token = $match[1] ?? '';
    $record = get_user_meta($id, '_theobroma_email_token', true);
    $assert($record['hash'] !== $token && $record['hash'] === hash('sha256', $token), 'Only a token hash should be stored');
    $assert(!$verification->send($id), 'Resend cooldown missing');
    $assert(!$verification->confirm($id, str_repeat('0', 64)), 'Wrong token accepted');
    $record['expires'] = time() - 1;
    update_user_meta($id, '_theobroma_email_token', $record);
    $assert(!$verification->confirm($id, $token), 'Expired token accepted');
    update_user_meta($id, '_theobroma_email_attempt', time() - 61);
    $assert($verification->send($id), 'Resend failed');
    $assert(!$verification->confirm($id, $token), 'Old token accepted after resend');
    preg_match('/confirmation-token=([a-f0-9]{64})/', end($mails)['message'], $match);
    $token = $match[1];
    $assert($verification->confirm($id, $token), 'Valid token rejected');
    $assert(!$verification->confirm($id, $token), 'Token replay accepted');
    $assert(!$verification->pending($id), 'Account still pending');
    $assert(wp_authenticate($email, $password) instanceof WP_User, 'Verified user blocked through WordPress');
    $assert($verification->authCookies(true, 0, 0, $id, 'auth', 'test'), 'Verified cookies blocked');
    // Existing accounts without pending metadata keep their current login behaviour.
    $assert($verification->registrationAuth(true, $id), 'Existing user registration filter changed');
    update_user_meta($id, '_theobroma_email_pending', '1');
    update_user_meta($id, '_theobroma_email_attempt', time() - 61);
    $failMail = true;
    $assert(!$verification->send($id), 'Mail failure should be reported');
    $assert($verification->pending($id), 'Mail failure bypassed confirmation');
    $assert(get_user_meta($id, '_theobroma_email_sent', true) === '', 'Mail failure stored as success');
    echo "PASS email: registration, hashed token, login/cookie guards, expiry, resend, replay, activation, mail failure\n";
} finally {
    remove_filter('pre_wp_mail', $mail, 10);
    remove_filter('theobroma_email_verification_enabled', $force);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($ids as $id) wp_delete_user($id);
    wc_clear_notices();
}
