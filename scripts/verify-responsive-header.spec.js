const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const url = process.env.THEOBROMA_URL || 'http://localhost:8080/';
const cssFile = process.env.THEOBROMA_CSS_FILE || '';
const widths = [1440, 1920, 2048, 2560, 3840];
const compactDesktopWidths = [1200, 1221, 1280, 1319, 1320];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1200 } });
      await page.goto(url, { waitUntil: 'domcontentloaded' });
      await page.locator('.site-header').waitFor({ state: 'attached' });
      await page.evaluate(() => document.fonts.ready);
      if (cssFile) {
        await page.addStyleTag({ content: fs.readFileSync(cssFile, 'utf8') });
      }

      const metrics = await page.locator('.site-header').evaluate((header) => {
        const shipping = header.querySelector('.shipping');
        const icon = shipping.querySelector('img');
        const text = shipping.querySelector('span');
        const nav = header.querySelector('.nav');
        const floating = document.querySelector('.floating-actions');

        return {
          headerHeight: header.getBoundingClientRect().height,
          shippingHeight: shipping.getBoundingClientRect().height,
          iconWidth: icon.getBoundingClientRect().width,
          textFontSize: parseFloat(getComputedStyle(text).fontSize),
          navTop: nav.getBoundingClientRect().top,
          floatingTop: floating.getBoundingClientRect().top,
          viewportWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
        };
      });

      assert.ok(metrics.headerHeight <= 51.1, `${width}px: header is ${metrics.headerHeight}px tall`);
      assert.ok(metrics.shippingHeight <= 51.1, `${width}px: shipping strip is ${metrics.shippingHeight}px tall`);
      assert.ok(metrics.iconWidth <= 30.8, `${width}px: shipping icon is ${metrics.iconWidth}px wide`);
      assert.ok(metrics.textFontSize <= 18.9, `${width}px: shipping text is ${metrics.textFontSize}px`);
      assert.ok(metrics.navTop <= 51.1, `${width}px: navigation begins at ${metrics.navTop}px`);
      assert.ok(metrics.floatingTop <= 74.1, `${width}px: floating actions begin at ${metrics.floatingTop}px`);
      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${width}px: horizontal overflow detected`);

      await page.close();
    }

    for (const width of compactDesktopWidths) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      await page.goto(url, { waitUntil: 'domcontentloaded' });
      await page.locator('.site-header').waitFor({ state: 'attached' });
      await page.evaluate(() => document.fonts.ready);
      if (cssFile) {
        await page.addStyleTag({ content: fs.readFileSync(cssFile, 'utf8') });
      }

      const gaps = await page.evaluate(() => {
        const groups = document.querySelectorAll('.nav-links');
        const leftAnchor = groups[0].querySelector('a:last-child').getBoundingClientRect();
        const brand = document.querySelector('.brand').getBoundingClientRect();
        const rightAnchor = groups[1].querySelector('a:first-child').getBoundingClientRect();
        return {
          left: brand.left - leftAnchor.right,
          right: rightAnchor.left - brand.right,
          floatingTop: document.querySelector('.floating-actions').getBoundingClientRect().top,
        };
      });

      assert.ok(gaps.left >= 8, `${width}px: left navigation overlaps logo (${gaps.left}px gap)`);
      assert.ok(gaps.right >= 8, `${width}px: right navigation overlaps logo (${gaps.right}px gap)`);
      if (width <= 1319) {
        assert.ok(Math.abs(gaps.floatingTop - 140) <= 1, `${width}px: floating actions start at ${gaps.floatingTop}px instead of 140px`);
      }
      await page.close();
    }

    const mobile = await browser.newPage({ viewport: { width: 430, height: 932 } });
    await mobile.goto(url, { waitUntil: 'domcontentloaded' });
    await mobile.locator('.site-header').waitFor({ state: 'attached' });
    await mobile.evaluate(() => document.fonts.ready);
    if (cssFile) {
      await mobile.addStyleTag({ content: fs.readFileSync(cssFile, 'utf8') });
    }
    await mobile.waitForTimeout(3000);
    assert.equal(
      await mobile.locator('.hero h1').evaluate((title) => getComputedStyle(title).visibility),
      'visible',
      '430px: hero title remains hidden after critical fonts settle',
    );
    const mobileMetrics = await mobile.evaluate(() => {
      const rect = (selector) => {
        const box = document.querySelector(selector).getBoundingClientRect();
        return { x: box.x, y: box.y, width: box.width, height: box.height };
      };

      return {
        brand: rect('.brand'),
        account: rect('.floating-actions a:nth-child(3)'),
        accountIcon: rect('.floating-actions a:nth-child(3) img'),
        cart: rect('.floating-actions a:nth-child(1)'),
        cartIcon: rect('.floating-actions a:nth-child(1) img'),
        cartCount: rect('.floating-actions a:nth-child(1) span'),
        favorite: rect('.floating-actions a:nth-child(2)'),
        favoriteIcon: rect('.floating-actions a:nth-child(2) img'),
        favoriteCount: rect('.floating-actions a:nth-child(2) span'),
        menu: rect('.menu-toggle'),
        heroTitle: rect('.hero h1'),
        heroLead: rect('.hero p'),
        heroButton: rect('.hero .button'),
      };
    });
    const expectedMobile = {
      brand: { x: 20.16, y: 56.16, width: 134.39, height: 55.98 },
      account: { x: 178.75, y: 60.19, width: 40.31, height: 47.03 },
      accountIcon: { x: 189.5, y: 74.97, width: 18.81, height: 18.81 },
      cart: { x: 225.78, y: 60.19, width: 64.5, height: 47.03 },
      cartIcon: { x: 232.5, y: 73.63, width: 24.19, height: 20.45 },
      cartCount: { x: 262.08, y: 77.66, width: 17.75, height: 13.44 },
      favorite: { x: 297.02, y: 60.19, width: 64.5, height: 47.03 },
      favoriteIcon: { x: 303.73, y: 73.63, width: 24.19, height: 20.45 },
      favoriteCount: { x: 333.3, y: 77.66, width: 17.75, height: 13.44 },
      menu: { x: 368.25, y: 53.47, width: 59.13, height: 59.13 },
      heroTitle: { x: 33.94, y: 156.95, width: 362.13, height: 137.1 },
      heroLead: { x: 76.56, y: 310.17, width: 278.2, height: 53.75 },
      heroButton: { x: 97.41, y: 390.81, width: 235.19, height: 48.38 },
    };
    for (const [name, expected] of Object.entries(expectedMobile)) {
      for (const [metric, target] of Object.entries(expected)) {
        const actual = mobileMetrics[name][metric];
        assert.ok(
          Math.abs(actual - target) <= (name.startsWith('hero') ? 2 : 1),
          `430px: ${name} ${metric} is ${actual}px, expected ${target}px`,
        );
      }
    }
    await mobile.close();
  } finally {
    await browser.close();
  }

  console.log(`Responsive header verified at: ${widths.join(', ')}px`);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
