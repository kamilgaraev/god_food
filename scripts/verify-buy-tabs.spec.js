const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const templatePath = path.join(root, 'wp-content/themes/theobroma/template-parts/pages/buy.php');
const scriptPath = path.join(root, 'wp-content/themes/theobroma/assets/js/buy-tabs.js');
const stylesheetPath = path.join(root, 'wp-content/themes/theobroma/style.css');
const contentPath = path.join(root, 'wp-content/themes/theobroma/inc/buy-partners.php');

(async () => {
  const template = fs.readFileSync(templatePath, 'utf8');
  assert.match(template, /role="tablist"/, 'buy page exposes an accessible tab list');
  assert.doesNotMatch(template, /bulletcities2|Маркетплейсы|ozon\.ru|wildberries\.ru/iu, 'buy page does not advertise marketplaces');
  assert.doesNotMatch(template, /buy-tabs[\s\S]{0,500}theobroma_page_url/, 'buy tabs do not navigate to another WordPress page');
  assert.equal((template.match(/role="tab"/g) || []).length, 2, 'buy page renders only boutiques and all-Russia tabs');
  assert.match(template, /theobroma_buy_get_entries\('theobroma_boutique'\)/, 'boutiques are loaded from managed content');
  assert.match(template, /theobroma_buy_get_entries\('theobroma_partner'\)/, 'partners are loaded from managed content');
  assert.match(template, /buy-partner-logo/, 'the Russia partner loop renders partner logos');
  assert.doesNotMatch(template, /ashanti\.png|jagannath\.png|white-clouds\.png/, 'partner filenames are no longer hardcoded in the template');

  const logoFiles = ['ashanti.png', 'jagannath.png', 'white-clouds.png', 'vidzhai.png', 'green-cardamon.png', 'sattva.png', 'delikateska.png', 'naturalista.png', 'ukrop.png', 'kunzhut.png', 'mishkin-gostinets.png'];
  const content = fs.readFileSync(contentPath, 'utf8');
  logoFiles.forEach((logoFile) => {
    assert.match(content, new RegExp(logoFile.replace('.', '\\.') ), `${logoFile} is included in the one-time migration`);
    assert.equal(fs.existsSync(path.join(root, 'wp-content/themes/theobroma/assets/images/partners', logoFile)), true, `${logoFile} exists locally`);
  });

  const tabScript = fs.readFileSync(scriptPath, 'utf8');
  const stylesheet = fs.readFileSync(stylesheetPath, 'utf8');
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    await page.setContent(`
      <style>${stylesheet}</style>
      <nav class="buy-tabs" role="tablist">
        <button id="buy-tab-1" role="tab" aria-controls="bulletcities1" aria-selected="true">Бутики</button>
        <button id="buy-tab-3" role="tab" aria-controls="bulletcities3" aria-selected="false" tabindex="-1">Вся Россия</button>
      </nav>
      <section id="bulletcities1" role="tabpanel" aria-labelledby="buy-tab-1">Бутик</section>
      <section id="bulletcities3" role="tabpanel" aria-labelledby="buy-tab-3" hidden>Партнёры</section>
    `);
    await page.addScriptTag({ content: tabScript });

    const tabWidths = await page.locator('.buy-tabs').evaluate((tabList) => ({
      list: tabList.getBoundingClientRect().width,
      tabs: Array.from(tabList.children, (tab) => tab.getBoundingClientRect().width),
    }));
    tabWidths.tabs.forEach((width) => {
      assert.ok(Math.abs(width - tabWidths.list / 2) <= 1, 'two where-to-buy tabs must split the tab list evenly');
    });

    await page.getByRole('tab', { name: 'Бутики' }).press('ArrowRight');
    await page.waitForFunction(() => !document.querySelector('#bulletcities3')?.hidden);
    assert.equal(await page.locator('#bulletcities3').isVisible(), true, 'keyboard navigation activates the all-Russia panel');
    assert.equal(await page.locator('#bulletcities1').isVisible(), false, 'keyboard navigation hides the previous panel');
    assert.equal(await page.getByRole('tab', { name: 'Вся Россия' }).getAttribute('aria-selected'), 'true');

    await page.getByRole('tab', { name: 'Вся Россия' }).press('ArrowLeft');
    await page.waitForFunction(() => !document.querySelector('#bulletcities1')?.hidden);
    assert.equal(await page.locator('#bulletcities1').isVisible(), true, 'keyboard navigation returns to boutiques');
  } finally {
    await browser.close();
  }

  console.log('Where-to-buy tabs verified without marketplaces');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
