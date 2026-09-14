const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, webkit } = require('playwright');

const root = path.resolve(__dirname, '..');
const baseUrl = (process.env.THEOBROMA_BASE_URL || process.env.BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const template = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/template-parts/pages/buy.php'), 'utf8');
const content = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/inc/buy-partners.php'), 'utf8');
const adminScript = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/assets/js/buy-admin.js'), 'utf8');
const adminStyle = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/assets/css/buy-admin.css'), 'utf8');

assert.match(template, /theobroma_buy_get_entries\('theobroma_boutique'\)/);
assert.match(template, /theobroma_buy_get_entries\('theobroma_partner'\)/);
assert.match(content, /register_post_type\('theobroma_boutique'/);
assert.match(content, /register_post_type\('theobroma_partner'/);
assert.match(content, /theobroma_buy_content_migrated_v1/);
assert.match(adminScript, /wp\.media/);
assert.match(adminStyle, /theobroma-buy-editor/);

const viewportCases = [
  { width: 320, height: 800 },
  { width: 768, height: 1024 },
  { width: 1440, height: 900 },
];

async function verifyBrowser(browserType, browserName) {
  const browser = await browserType.launch({ headless: true });
  try {
    for (const viewport of viewportCases) {
      const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
      const page = await context.newPage();
      const response = await page.goto(`${baseUrl}/buy/`, { waitUntil: 'networkidle' });
      assert.ok(response && response.ok(), `${browserName} ${viewport.width}px: /buy/ must respond successfully`);
      await page.evaluate(async () => document.fonts?.ready);

      const state = await page.evaluate(() => {
        const tabs = [...document.querySelectorAll('.buy-tabs [role="tab"]')];
        const panels = tabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls')));
        const cards = [...document.querySelectorAll('.buy-location,.buy-partner-card')];
        return {
          tabCount: tabs.length,
          panelsLinked: panels.every((panel, index) => panel && panel.getAttribute('aria-labelledby') === tabs[index].id),
          selectedCount: tabs.filter((tab) => tab.getAttribute('aria-selected') === 'true').length,
          firstPanelVisible: Boolean(panels[0] && !panels[0].hidden),
          firstPanelHasContent: Boolean(document.querySelector('#bulletcities1 .buy-location, #bulletcities1 .buy-empty-state')),
          secondPanelHasContent: Boolean(document.querySelector('#bulletcities3 .buy-partner-card, #bulletcities3 .buy-empty-state')),
          horizontalOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
          cardsInViewport: cards.every((card) => {
            const rect = card.getBoundingClientRect();
            return rect.left >= -1 && rect.right <= window.innerWidth + 1;
          }),
        };
      });

      assert.equal(state.tabCount, 2, `${browserName} ${viewport.width}px: two where-to-buy tabs`);
      assert.equal(state.panelsLinked, true, `${browserName} ${viewport.width}px: tabs and panels are linked`);
      assert.equal(state.selectedCount, 1, `${browserName} ${viewport.width}px: one tab is selected`);
      assert.equal(state.firstPanelVisible, true, `${browserName} ${viewport.width}px: boutique panel starts visible`);
      assert.equal(state.firstPanelHasContent, true, `${browserName} ${viewport.width}px: boutique panel has managed content or empty state`);
      assert.equal(state.secondPanelHasContent, true, `${browserName} ${viewport.width}px: partner panel has managed content or empty state`);
      assert.ok(state.horizontalOverflow <= 1, `${browserName} ${viewport.width}px: no horizontal overflow`);
      assert.equal(state.cardsInViewport, true, `${browserName} ${viewport.width}px: cards stay inside viewport`);

      await page.getByRole('tab', { name: 'Вся Россия' }).click();
      await page.waitForFunction(() => !document.querySelector('#bulletcities3')?.hidden);
      assert.equal(await page.locator('#bulletcities1').isVisible(), false, `${browserName} ${viewport.width}px: boutique panel hides after tab switch`);
      assert.equal(await page.getByRole('tab', { name: 'Вся Россия' }).getAttribute('aria-selected'), 'true');

      await page.getByRole('tab', { name: 'Бутики' }).click();
      await page.waitForFunction(() => !document.querySelector('#bulletcities1')?.hidden);
      assert.equal(await page.locator('#bulletcities3').isVisible(), false, `${browserName} ${viewport.width}px: partner panel hides after return`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
}

(async () => {
  await verifyBrowser(chromium, 'Chromium');
  await verifyBrowser(webkit, 'WebKit');
  console.log(`Managed where-to-buy content verified at ${baseUrl} for Chromium and WebKit`);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
