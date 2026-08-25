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
      <style>.corporate-gifts-minimum .button { transition: none !important; }</style>
      <section class="corporate-gifts-minimum">
        <a class="button" href="#corporate-request">Получить расчёт</a>
      </section>
    `);

    const button = page.locator('.corporate-gifts-minimum .button');
    const defaultState = await button.evaluate((element) => {
      const style = getComputedStyle(element);
      return { color: style.color, backgroundColor: style.backgroundColor };
    });

    assert.deepEqual(defaultState, {
      color: 'rgb(176, 144, 61)',
      backgroundColor: 'rgb(255, 255, 255)',
    }, 'Corporate minimum CTA must contrast with the gold section');

    await button.hover();

    const hoverBackground = await button.evaluate(
      (element) => getComputedStyle(element).backgroundColor,
    );
    assert.equal(hoverBackground, 'rgb(243, 235, 228)', 'Corporate minimum CTA must have a visible hover state');
  } finally {
    await browser.close();
  }

  console.log('Corporate minimum CTA contrasts with its gold section');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
