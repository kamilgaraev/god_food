<?php
/** Read-only integration test: docker compose exec -T wordpress php /opt/theobroma-scripts/verify-corporate-form-integration.php */
require '/var/www/html/wp-load.php';

function corporate_check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

$settings = new \Theobroma\ContactForms\Settings();
$definition = $settings->defaults('corporate-test@example.test');
$definition['corporate']['fields']['phone'] = array('enabled' => false, 'required' => false);
$definition['corporate']['fields']['email'] = array('enabled' => true, 'required' => true);
$definition['corporate']['custom_fields'][0]['required'] = true;
add_filter('pre_option_theobroma_contact_forms_settings', static fn() => $definition);
$catalog = '';
add_filter('pre_option_theobroma_content_settings', static function () use (&$catalog) { return array('corporate_catalog_url' => $catalog); });
$render = static function (): string {
    ob_start();
    include get_template_directory() . '/template-parts/pages/corporate-gifts.php';
    return (string) ob_get_clean();
};
$html = $render();
corporate_check(str_contains($html, 'data-cg-request="Каталог PDF"'), 'Empty PDF URL must offer a catalog request');
corporate_check(!str_contains($html, 'name="phone"'), 'Disabled phone must disappear');
corporate_check(str_contains($html, 'type="email" name="email"'), 'Enabled email must render');
corporate_check(str_contains($html, 'name="form_id" value="corporate"'), 'Submission must route to corporate plugin definition');
$catalog = 'https://example.test/corporate.pdf';
$html = $render();
corporate_check(str_contains($html, 'href="https://example.test/corporate.pdf" target="_blank" rel="noopener">Скачать каталог PDF'), 'Configured PDF URL must render safely');
corporate_check(!str_contains($html, 'data-cg-request="Каталог PDF"'), 'PDF URL replaces request CTA');
$request = array('name' => '', 'phone' => '', 'email' => 'buyer@example.test', 'consent' => '1', 'started_at' => time() - 10, 'honeypot' => '', 'custom' => array('company' => 'Example Ltd', 'gift' => 'Знакомство'));
corporate_check(theobroma_standard_contact_request_is_valid($request, 'corporate', time()), 'Configured email-only corporate request must validate');
corporate_check(theobroma_standard_contact_request_recipient('corporate', 'fallback@example.test') === 'corporate-test@example.test', 'Recipient must come from corporate settings');
corporate_check(in_array('Набор: Знакомство', theobroma_standard_contact_request_lines('corporate', $request), true), 'Gift must be saved in submission details');
$request['custom']['company'] = '';
corporate_check(!theobroma_standard_contact_request_is_valid($request, 'corporate', time()), 'Required custom fields must be validated');
echo "Corporate form integration and PDF settings verified without changing the database.\n";
