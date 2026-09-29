<?php
declare(strict_types=1);
require_once '/var/www/html/wp-load.php';

function require_polish(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$plan = json_decode(file_get_contents($argv[1] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$products = wc_get_products(['limit' => -1, 'status' => ['publish', 'draft', 'private']]);
require_polish(count($products) === (int) $plan['product_count'], 'Product count changed.');
foreach ($plan['updates'] as $row) {
    $product = wc_get_product($row['id']);
    require_polish($product && (float) $product->get_price() === (float) $row['price'], 'Incorrect selling price: ' . $row['id']);
    require_polish($product->get_status() === $row['status'], 'Product publication state changed.');
}
require_polish(\Theobroma\Commerce\Shipping\FreeShippingPolicy::customerCost(350, 2999.99) === 350.0, 'Shipping below 3000 must remain paid.');
require_polish(\Theobroma\Commerce\Shipping\FreeShippingPolicy::customerCost(350, 3000) === 0.0, 'Shipping from 3000 must be free.');
require_polish(theobroma_content('shipping_text') === 'Бесплатная доставка от 3000 рублей', 'Shipping announcement is stale.');
$originals = [];
foreach ($products as $product) {
    if ($product->get_status() !== 'publish') continue;
    foreach (array_unique(array_filter(array_merge([$product->get_image_id(), (int) $product->get_meta('_theobroma_product_detail_image_id')], $product->get_gallery_image_ids()))) as $id) {
        require_polish(is_file(wp_get_original_image_path($id) ?: ''), 'Missing original: ' . $id);
        $html = theobroma_product_detail_image($id, $product->get_name());
        require_polish(str_contains($html, 'data-product-original-image=') && str_contains($html, 'srcset='), 'Missing original/srcset: ' . $id);
        $originals[$id] = true;
    }
}
$new_image = theobroma_product_detail_image(341, 'Молочный шоколад');
require_polish(str_contains($new_image, '1121w') && !str_contains($new_image, '-560x745.png'), 'New photo still uses cropped low resolution.');
$faq = (new \Theobroma\Seo\WordPressDocumentResolver())->forPost(get_page_by_path('corporate-gifts'));
require_polish(count($faq->schema['mainEntity'] ?? []) === count(theobroma_corporate_questions()), 'FAQ schema does not match visible content.');
require_polish(theobroma_home_bundle_is_current(), 'Homepage CSS bundle is stale.');
$email = WC()->mailer()->get_emails()['WC_Email_Customer_New_Account'];
$args = ['email_heading' => $email->get_heading(), 'user_login' => 'Владислав', 'user_display_name' => 'Владислав', 'password_generated' => true, 'set_password_url' => home_url('/my-account/?action=set-password-preview'), 'additional_content' => '', 'email' => $email, 'blogname' => get_bloginfo('name'), 'sent_to_admin' => false, 'plain_text' => false];
$html = $email->style_inline(wc_get_template_html('emails/customer-new-account.php', $args));
require_polish(str_contains($html, 'Четыре ингредиента и ничего лишнего') && str_contains($html, 'Перейти в аккаунт') && str_contains($html, 'Выбрать шоколад') && str_contains($html, 'задайте пароль'), 'Welcome copy or actions missing.');
require_polish(str_contains($html, '/assets/images/logo.png'), 'Welcome email logo missing.');
file_put_contents('/tmp/theobroma-welcome-preview.html', $html);
$args['password_generated'] = false;
$plain = wc_get_template_html('emails/plain/customer-new-account.php', $args);
require_polish(str_contains($plain, 'тот, который вы указали при регистрации'), 'User-chosen password instructions missing.');
echo wp_json_encode(['products_verified' => count($plan['updates']), 'product_count' => count($products), 'original_images_verified' => count($originals), 'faq_questions' => count($faq->schema['mainEntity']), 'shipping' => '2999.99 paid / 3000 free', 'welcome_email' => 'HTML and plain text rendered, no email sent', 'home_bundle' => 'current'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
