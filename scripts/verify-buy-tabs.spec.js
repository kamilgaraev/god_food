const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const templatePath = path.join(root, 'wp-content/themes/theobroma/template-parts/pages/buy.php');
const scriptPath = path.join(root, 'wp-content/themes/theobroma/assets/js/buy-tabs.js');
const marketplacePath = path.join(root, 'wp-content/themes/theobroma/template-parts/pages/marketplace.php');
const homePath = path.join(root, 'wp-content/themes/theobroma/index.php');
const expectedOzonUrl = 'https://www.ozon.ru/seller/theobroma-pishcha-bogov/produkty-pitaniya-9200/?miniapp=seller_60476';
const expectedWildberriesUrl = 'https://www.wildberries.ru/seller/260547';

(async () => {
  const template = fs.readFileSync(templatePath, 'utf8');
  assert.match(template, /role="tablist"/, 'buy page exposes an accessible tab list');
  assert.match(template, /id="bulletcities2"[^>]*role="tabpanel"/, 'marketplace content is an in-page panel');
  assert.doesNotMatch(template, /buy-tabs[\s\S]{0,500}theobroma_page_url/, 'buy tabs do not navigate to another WordPress page');
  const logoFiles = ['ozon.png', 'wildberries.png', 'ashanti.png', 'jagannath.png', 'white-clouds.png', 'vidzhai.png', 'green-cardamon.png', 'sattva.png', 'delikateska.png', 'naturalista.png', 'ukrop.png', 'kunzhut.png', 'mishkin-gostinets.png'];
  assert.equal((template.match(/class="buy-partner-logo"/g) || []).length, 3, 'marketplace cards and the Russia card loop render logo images');
  logoFiles.forEach((logoFile) => {
    assert.match(template, new RegExp(logoFile.replace('.', '\\.')), `${logoFile} is wired into the buy page`);
    assert.equal(fs.existsSync(path.join(root, 'wp-content/themes/theobroma/assets/images/partners', logoFile)), true, `${logoFile} exists locally`);
  });
  assert.match(template, new RegExp(expectedOzonUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), 'buy tab uses the approved Ozon seller URL');
  assert.match(template, new RegExp(expectedWildberriesUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), 'buy tab uses the approved Wildberries seller URL');
  for (const sourcePath of [marketplacePath, homePath]) {
    const source = fs.readFileSync(sourcePath, 'utf8');
    assert.match(source, new RegExp(expectedOzonUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), `${path.basename(sourcePath)} uses the approved Ozon seller URL`);
    assert.match(source, new RegExp(expectedWildberriesUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), `${path.basename(sourcePath)} uses the approved Wildberries seller URL`);
  }

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
