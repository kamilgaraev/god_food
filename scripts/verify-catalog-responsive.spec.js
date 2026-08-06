const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 430]) {
      const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : 932 }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/catalog/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const products = [...document.querySelectorAll('ul.products li.product')].map((product) => {
          const rect = product.getBoundingClientRect();
          const image = product.querySelector('img').getBoundingClientRect();
          return { x: rect.x, y: rect.y + scrollY, width: rect.width, imageWidth: image.width, imageHeight: image.height };
        });
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        return {
          height: document.documentElement.scrollHeight,
          scrollWidth: document.documentElement.scrollWidth,
          products,
          footerY: footer.y + scrollY,
        };
      });
      assert.equal(metrics.scrollWidth, width, `${width}px: horizontal overflow`);
      assert.equal(metrics.products.length, 6, `${width}px: all catalog products must remain visible`);
      assert.ok(metrics.products.every((product) => product.x >= 0 && product.x + product.width <= width), `${width}px: product is clipped`);
      if (width === 430) {
        closeEnough(metrics.products[0].x, 20, 1, '430px first product x');
        closeEnough(metrics.products[0].y, 521, 2, '430px first product y');
        closeEnough(metrics.products[0].imageWidth, 185, 1, '430px product image width');
        closeEnough(metrics.products[0].imageHeight, 231.25, 1, '430px product image height');
        closeEnough(metrics.footerY, 1935.5625, 2, '430px footer boundary');
        closeEnough(metrics.height, 3384, 2, '430px document height');
      }
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
