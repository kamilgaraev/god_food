const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const path = require('node:path');
const { chromium } = require('playwright');

const themeIndex = path.resolve(
  __dirname,
  '../wp-content/themes/theobroma/index.php',
).replaceAll('\\', '/');

const bootstrap = `
class WC_Product {}
function get_header() {}
function get_footer() {}
function wc_get_page_permalink() { return '/catalog/'; }
function theobroma_page_url($title) { return '/' . rawurlencode($title) . '/'; }
function theobroma_homepage_products() { return []; }
function theobroma_home_cacao_groups() { return []; }
function theobroma_cacao_profiles() { return []; }
function is_front_page() { return true; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function wp_kses_post($value) { return (string) $value; }
function get_template_part() {}
function theobroma_content($key) { return $key; }
function get_template_directory_uri() { return '/theme'; }
function get_posts() { return []; }
include '${themeIndex.replaceAll("'", "\\'")}';
`;

const rendered = spawnSync('php', ['-r', bootstrap], {
  cwd: path.resolve(__dirname, '..'),
  encoding: 'utf8',
});

assert.equal(rendered.status, 0, rendered.stderr || 'Homepage template must render');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
    await page.setContent(rendered.stdout);

    assert.equal(
      await page.locator('.home-catalog__footer').count(),
      0,
      'Homepage catalog must not render the redundant mobile footer CTA',
    );
  } finally {
    await browser.close();
  }

  console.log('Homepage catalog omits the redundant mobile footer CTA');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
