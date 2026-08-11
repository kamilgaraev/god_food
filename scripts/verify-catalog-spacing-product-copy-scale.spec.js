const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const baseUrl = (process.env.THEOBROMA_URL || 'http://localhost:8080').replace(/\/$/, '');
const scope = process.env.THEOBROMA_CHECK_SCOPE || 'all';

async function openPage(browser, path, width) {
  const context = await browser.newContext({
    viewport: { width, height: width <= 600 ? 932 : 1200 },
    reducedMotion: 'reduce',
  });
  const page = await context.newPage();

  if (new URL(baseUrl).port !== '8080') {
    await page.route('http://localhost:8080/**', async (route) => {
      const target = new URL(route.request().url());
      const local = new URL(baseUrl);
      target.protocol = local.protocol;
      target.hostname = local.hostname;
      target.port = local.port;
      await route.continue({ url: target.href });
    });
  }

  await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle', timeout: 45_000 });
  await page.evaluate(() => document.fonts.ready);
  return { context, page };
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of scope === 'typography' ? [] : [1440, 2560, 3200]) {
      const { context, page } = await openPage(browser, '/', width);
      const metrics = await page.evaluate(() => {
        const catalog = document.querySelector('.home-catalog').getBoundingClientRect();
        const grid = document.querySelector('.home-product-grid').getBoundingClientRect();
        return {
          gapRem: (catalog.bottom - grid.bottom) / parseFloat(getComputedStyle(document.documentElement).fontSize),
        };
      });

      assert.ok(metrics.gapRem >= 3, `${width}px: catalog needs at least 3rem before the cacao section, got ${metrics.gapRem.toFixed(2)}rem`);
      await context.close();
    }

    for (const width of scope === 'spacing' ? [] : [390, 1440, 2560, 3200]) {
      const { context, page } = await openPage(browser, '/', width);
      await page.locator('[data-product-modal-link]').first().click();
      await page.locator('#commerce-modal .product-detail-accordions details[open] > div').waitFor();

      const typography = await page.evaluate(() => {
        const rootSize = parseFloat(getComputedStyle(document.documentElement).fontSize);
        const summary = getComputedStyle(document.querySelector('#commerce-modal .product-detail-accordions summary'));
        const copyContainer = document.querySelector('#commerce-modal .product-detail-accordions details[open] > div');
        const copy = getComputedStyle(copyContainer.querySelector('p, span, li') || copyContainer);
        return {
          summaryRem: parseFloat(summary.fontSize) / rootSize,
          copyRem: parseFloat(copy.fontSize) / rootSize,
        };
      });

      assert.ok(typography.summaryRem >= 0.95, `${width}px: product accordion heading must scale from about 1rem, got ${typography.summaryRem.toFixed(3)}rem`);
      assert.ok(typography.copyRem >= 0.95, `${width}px: product accordion copy must scale from about 1rem, got ${typography.copyRem.toFixed(3)}rem`);
      await context.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Catalog spacing and product accordion typography verified.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
