const { chromium } = require('playwright');
const assert = require('node:assert/strict');

const url = process.env.THEOBROMA_URL || 'http://localhost:8080/';
const widths = [1440, 1920, 2048, 2560, 3840];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1200 } });
      await page.goto(url, { waitUntil: 'networkidle' });

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

    const mobile = await browser.newPage({ viewport: { width: 430, height: 932 } });
    await mobile.goto(url, { waitUntil: 'networkidle' });
    const accountAction = await mobile.locator('.floating-actions a:nth-child(3)').evaluate((action) => action.getBoundingClientRect().x);
    assert.ok(accountAction <= 185, `430px: account action starts too far right (${accountAction}px)`);
    await mobile.close();
  } finally {
    await browser.close();
  }

  console.log(`Responsive header verified at: ${widths.join(', ')}px`);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
