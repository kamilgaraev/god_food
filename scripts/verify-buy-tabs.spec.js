const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const templatePath = path.join(root, 'wp-content/themes/theobroma/template-parts/pages/buy.php');
const scriptPath = path.join(root, 'wp-content/themes/theobroma/assets/js/buy-tabs.js');

(async () => {
  const template = fs.readFileSync(templatePath, 'utf8');
  assert.match(template, /role="tablist"/, 'buy page exposes an accessible tab list');
  assert.match(template, /id="bulletcities2"[^>]*role="tabpanel"/, 'marketplace content is an in-page panel');
  assert.doesNotMatch(template, /buy-tabs[\s\S]{0,500}theobroma_page_url/, 'buy tabs do not navigate to another WordPress page');

  const tabScript = fs.readFileSync(scriptPath, 'utf8');
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    await page.setContent(`
      <nav class="buy-tabs" role="tablist">
        <button id="buy-tab-1" role="tab" aria-controls="bulletcities1" aria-selected="true">Бутики</button>
        <button id="buy-tab-2" role="tab" aria-controls="bulletcities2" aria-selected="false">Маркетплейсы</button>
        <button id="buy-tab-3" role="tab" aria-controls="bulletcities3" aria-selected="false">Вся Россия</button>
      </nav>
      <section id="bulletcities1" role="tabpanel" aria-labelledby="buy-tab-1">Бутик</section>
      <section id="bulletcities2" role="tabpanel" aria-labelledby="buy-tab-2" hidden>Ozon</section>
      <section id="bulletcities3" role="tabpanel" aria-labelledby="buy-tab-3" hidden>Партнёры</section>
    `);
    await page.addScriptTag({ content: tabScript });

    const pathnameBefore = new URL(page.url()).pathname;
    await page.getByRole('tab', { name: 'Маркетплейсы' }).click();
    assert.equal(new URL(page.url()).pathname, pathnameBefore, 'tab click keeps the current page');
    assert.equal(await page.locator('#bulletcities2').isVisible(), true, 'marketplace panel becomes visible');
    assert.equal(await page.locator('#bulletcities1').isVisible(), false, 'previous panel becomes hidden');
    assert.equal(await page.getByRole('tab', { name: 'Маркетплейсы' }).getAttribute('aria-selected'), 'true');

    await page.getByRole('tab', { name: 'Маркетплейсы' }).press('ArrowRight');
    assert.equal(await page.locator('#bulletcities3').isVisible(), true, 'keyboard navigation activates the next panel');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
