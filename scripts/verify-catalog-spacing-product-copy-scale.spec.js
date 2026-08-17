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
    if (scope === 'all' || scope === 'hero') {
      const { context, page } = await openPage(browser, '/', 1115);
      const hero = await page.evaluate(() => {
        const rootSize = parseFloat(getComputedStyle(document.documentElement).fontSize);
        const trust = document.querySelector('.home-hero__trust').getBoundingClientRect();
        const lead = document.querySelector('.home-hero__lead').getBoundingClientRect();
        return {
          trustLeadGapRem: (lead.top - trust.bottom) / rootSize,
        };
      });

      assert.ok(hero.trustLeadGapRem <= 4.5, `1115px: hero trust and CTA groups are split by ${hero.trustLeadGapRem.toFixed(2)}rem of empty space`);
      await context.close();
    }

    for (const width of scope === 'all' || scope === 'spacing' ? [1440, 2560, 3200] : []) {
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

    for (const width of scope === 'all' || scope === 'typography' ? [390, 1440, 2560, 3200] : []) {
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
          summaryLineHeightRem: parseFloat(summary.lineHeight) / rootSize,
          copyLineHeightRem: parseFloat(copy.lineHeight) / rootSize,
          summaryFamily: summary.fontFamily,
          copyFamily: copy.fontFamily,
        };
      });

      assert.ok(typography.summaryRem >= 0.95, `${width}px: product accordion heading must scale from about 1rem, got ${typography.summaryRem.toFixed(3)}rem`);
      assert.ok(typography.copyRem >= 0.95, `${width}px: product accordion copy must scale from about 1rem, got ${typography.copyRem.toFixed(3)}rem`);
      assert.ok(typography.summaryLineHeightRem >= 1.45, `${width}px: product accordion heading line-height must remain readable`);
      assert.ok(typography.copyLineHeightRem >= 1.45, `${width}px: product accordion copy line-height must remain readable`);
      assert.match(typography.summaryFamily, /Montserrat/i, `${width}px: product accordion heading must use the readable Montserrat text face`);
      assert.match(typography.copyFamily, /Montserrat/i, `${width}px: product accordion copy must use the readable Montserrat text face`);

      if (width === 1440) {
        const hoverCases = [
          { selector: '.single_add_to_cart_button', background: 'rgb(226, 217, 210)', color: 'rgb(0, 0, 0)' },
          { selector: '.product-detail-marketplaces a:first-child', background: 'rgb(143, 24, 237)', color: 'rgb(255, 255, 255)' },
          { selector: '.product-detail-marketplaces a:last-child', background: 'rgb(0, 73, 204)', color: 'rgb(255, 255, 255)' },
          { selector: '.product-detail-favorite', background: 'rgb(255, 251, 247)', color: 'rgb(255, 133, 98)', borderColor: 'rgb(228, 228, 228)' },
        ];

        for (const expected of hoverCases) {
          const control = page.locator(`#commerce-modal ${expected.selector}`).first();
          await control.hover();
          await page.waitForTimeout(250);
          const actual = await control.evaluate((element) => {
            const style = getComputedStyle(element);
            return {
              background: style.backgroundColor,
              color: style.color,
              borderColor: style.borderColor,
              transform: style.transform,
            };
          });

          assert.equal(actual.background, expected.background, `${expected.selector}: hover background differs from the source product card`);
          assert.equal(actual.color, expected.color, `${expected.selector}: hover text/icon color differs from the source product card`);
          assert.equal(actual.transform, 'none', `${expected.selector}: source hover does not move the control`);
          if (expected.borderColor) assert.equal(actual.borderColor, expected.borderColor, `${expected.selector}: hover border differs from the source product card`);
        }
      }
      await context.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Homepage spacing, hero composition, and product-card interactions verified.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
