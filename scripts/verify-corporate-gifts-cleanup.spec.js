const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const template = path.join(
  root,
  'wp-content/themes/theobroma/template-parts/pages/corporate-gifts.php',
);
const stylesheet = fs.readFileSync(
  path.join(root, 'wp-content/themes/theobroma/style.css'),
  'utf8',
);

const phpBootstrap = `
function wc_get_products($args) { return array(); }
function esc_html($value) { return $value; }
function esc_url($value) { return $value; }
function theobroma_content($key) { return $key; }
function admin_url($path) { return $path; }
function wp_nonce_field($action, $name) {}
function theobroma_contact_antispam_fields() {}
include ${JSON.stringify(template.replaceAll('\\', '/'))};
`;

const markup = execFileSync('php', ['-r', phpBootstrap], { encoding: 'utf8' });

assert.doesNotMatch(
  markup,
  /Theobroma для бизнеса|Витрина|Индивидуальный дизайн|Сценарии|Условия заказа|Индивидуальный расчёт/,
  'Corporate gifts page must not render section eyebrow labels',
);

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(`
      <style>${stylesheet}</style>
      ${markup}
    `);

    const caseCards = page.locator('.corporate-gifts-cases article');
    assert.equal(await caseCards.count(), 3, 'Corporate gifts page must render three case cards');

    const borderWidths = await caseCards.evaluateAll(
      (elements) => elements.map((element) => {
        const styles = getComputedStyle(element);
        return [
          styles.borderTopWidth,
          styles.borderRightWidth,
          styles.borderBottomWidth,
          styles.borderLeftWidth,
        ];
      }),
    );

    assert.deepEqual(
      borderWidths,
      Array.from({ length: 3 }, () => ['0px', '0px', '0px', '0px']),
      'Corporate gift case cards must render without borders',
    );
  } finally {
    await browser.close();
  }

  console.log('Corporate gift cards have no borders or eyebrow labels');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
