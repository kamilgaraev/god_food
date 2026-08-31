const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const cssPath = path.join(root, 'wp-content/themes/theobroma/style.css');
const homeCssPath = path.join(root, 'wp-content/themes/theobroma/assets/css/home-redesign.css');
const scriptPath = path.join(root, 'wp-content/themes/theobroma/assets/js/site-header.js');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of [390, 768, 1440, 2295]) {
      const page = await browser.newPage({
        viewport: { width, height: 900 },
        reducedMotion: 'no-preference',
      });
      await page.setContent(`
        <!doctype html>
        <header class="site-header">
          <div class="shipping"></div>
          <nav class="nav">
            <div class="nav-links"></div>
            <a class="brand">Theobroma</a>
            <div class="nav-links"></div>
          </nav>
          <div class="floating-actions"></div>
        </header>
        <main style="height:3000px"></main>
      `);
      await page.addStyleTag({ path: cssPath });
      await page.addStyleTag({ path: homeCssPath });
      await page.addScriptTag({ path: scriptPath });

      const nav = page.locator('.nav');
      const header = page.locator('.site-header');
      const samples = [];
      for (let y = 0; y <= 160; y += 1) {
        await page.evaluate((scrollTop) => window.scrollTo(0, scrollTop), y);
        await page.waitForTimeout(8);
        samples.push(await nav.evaluate((element) => element.getBoundingClientRect().top));
      }
      await page.waitForTimeout(500);

      const result = await nav.evaluate((element) => ({
        finalScrollY: scrollY,
        scrollHeight: document.documentElement.scrollHeight,
        finalPosition: getComputedStyle(element).position,
        finalTop: element.getBoundingClientRect().top,
        shippingBottom: document.querySelector('.shipping').getBoundingClientRect().bottom,
      }));
      result.header = await header.evaluate((element) => ({
        position: getComputedStyle(element).position,
        top: element.getBoundingClientRect().top,
      }));
      result.maxFrameJump = Math.max(...samples.slice(1).map((top, index) => Math.abs(top - samples[index])));

      assert.equal(result.header.position, 'sticky', `${width}px: the complete header must use one stable sticky layer`);
      assert.ok(Math.abs(result.header.top) <= 1, `${width}px: sticky header settled at ${result.header.top}px instead of the viewport top`);
      assert.equal(result.finalPosition, 'absolute', `${width}px: navigation must stay inside the sticky header`);
      assert.ok(Math.abs(result.finalTop - result.shippingBottom) <= 1, `${width}px: navigation detached from the free-shipping banner`);
      assert.ok(
        result.maxFrameJump <= 2,
        `${width}px: navigation jumps ${result.maxFrameJump.toFixed(2)}px while becoming sticky`,
      );
      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Sticky header follows the scroll without a position jump');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
