const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.resolve(__dirname, '../wp-content/themes/theobroma/style.css'),
  'utf8',
);

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(`
      <style>${stylesheet}</style>
      <section class="corporate-gifts-minimum">
        <a class="button" href="#corporate-request">Получить расчёт</a>
      </section>
    `);

    const backgroundColor = await page.locator('.corporate-gifts-minimum .button').evaluate(
      (element) => getComputedStyle(element).backgroundColor,
    );

    assert.equal(
      backgroundColor,
      'rgb(176, 144, 61)',
      'Corporate minimum CTA must use the shared gold button background',
    );
  } finally {
    await browser.close();
  }

  console.log('Corporate minimum CTA uses the shared gold button style');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
