const assert = require('node:assert/strict');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright');

const themeRoot = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma');
const templatePath = path.join(themeRoot, 'template-parts', 'commerce', 'cart-modal.php');

const php = `
define('ABSPATH', __DIR__);
class WC_Product {
    public function exists() { return true; }
    public function get_permalink() { return '/product/cacao/'; }
    public function get_image($size, $attributes) { return '<img src="cacao.jpg" alt="Какао">'; }
    public function get_name() { return 'Какао'; }
    public function get_id() { return 1; }
}
class CartStub {
    public function get_cart() { return ['item' => ['data' => new WC_Product(), 'quantity' => 1]]; }
    public function get_product_subtotal($product, $quantity) { return '567 руб.'; }
    public function get_cart_subtotal() { return '567 руб.'; }
}
class WooStub { public $cart; public function __construct() { $this->cart = new CartStub(); } }
function WC() { static $woo; return $woo ?: ($woo = new WooStub()); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function wp_kses_post($value) { return $value; }
function is_user_logged_in() { return true; }
function wc_get_page_permalink($page) { return '/' . $page . '/'; }
function theobroma_frontend_product_title($name, $id) { return $name; }
function theobroma_page_url($title) { return '/delivery/'; }
function do_shortcode($shortcode) { return ''; }
require ${JSON.stringify(templatePath)};
`;

const rendered = spawnSync('php', ['-r', php], { encoding: 'utf8' });
assert.equal(rendered.status, 0, rendered.stderr || 'Unable to render the cart template');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    await page.setContent(rendered.stdout);
    await page.addStyleTag({ path: path.join(themeRoot, 'style.css') });

    assert.equal(
      await page.locator('.commerce-cart-header h2').textContent(),
      'ВАШ ЗАКАЗ',
      'The order panel must show the uppercase “ВАШ ЗАКАЗ” heading',
    );

    for (const selector of [
      '.commerce-cart-header h2',
      '.commerce-cart-product h3',
      '.commerce-cart-quantity',
      '.commerce-cart-price',
      '.commerce-cart-subtotal',
      '.commerce-cart-notes',
      '.commerce-cart-checkout > h3',
    ]) {
      const fontFamily = await page.locator(selector).evaluate((element) => getComputedStyle(element).fontFamily);
      assert.match(fontFamily, /^Montserrat\b/i, `${selector} must use Montserrat, received ${fontFamily}`);
    }

    console.log('Cart order heading and Montserrat typography verified.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
