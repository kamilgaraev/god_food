const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 430, 768]) {
      const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : (width === 430 ? 932 : 1024) }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/catalog/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const products = [...document.querySelectorAll('ul.products li.product')].map((product) => {
          const rect = product.getBoundingClientRect();
          const image = product.querySelector('img').getBoundingClientRect();
          return { x: rect.x, y: rect.y + scrollY, width: rect.width, height: rect.height, imageWidth: image.width, imageHeight: image.height };
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
      if (width === 390 || width === 430) {
        const expected = width === 390
          ? { rows: [473, 944.9375, 1416.875], cardWidth: 185, cardHeights: [411.9375, 411.9375, 411.9375], imageWidth: 165, imageHeight: 206.25, footerY: 1888.8125, height: 3203 }
          : { rows: [521, 999.75, 1456.8125], cardWidth: 205, cardHeights: [418.75, 397.0625, 418.75], imageWidth: 185, imageHeight: 231.25, footerY: 1935.5625, height: 3384 };
        metrics.products.forEach((product, index) => {
          const row = Math.floor(index / 2);
          closeEnough(product.x, index % 2 === 0 ? 10 : 10 + expected.cardWidth, 1, `${width}px product ${index + 1} x`);
          closeEnough(product.y, expected.rows[row], 1, `${width}px product ${index + 1} y`);
          closeEnough(product.width, expected.cardWidth, 1, `${width}px product ${index + 1} width`);
          closeEnough(product.height, expected.cardHeights[row], 1, `${width}px product ${index + 1} height`);
          closeEnough(product.imageWidth, expected.imageWidth, 1, `${width}px product ${index + 1} image width`);
          closeEnough(product.imageHeight, expected.imageHeight, 1, `${width}px product ${index + 1} image height`);
        });
        closeEnough(metrics.footerY, expected.footerY, 1, `${width}px footer boundary`);
        closeEnough(metrics.height, expected.height, 2, `${width}px document height`);
      }
      if (width === 768) {
        const expectedRows = [467, 1040.921875, 1614.84375];
        metrics.products.forEach((product, index) => {
          closeEnough(product.x, index % 2 === 0 ? 84 : 404, 1, `768px product ${index + 1} x`);
          closeEnough(product.y, expectedRows[Math.floor(index / 2)], 2, `768px product ${index + 1} y`);
          closeEnough(product.width, 280, 1, `768px product ${index + 1} width`);
          closeEnough(product.height, 529, 1, `768px product ${index + 1} height`);
          closeEnough(product.imageWidth, 280, 1, `768px product ${index + 1} image width`);
          closeEnough(product.imageHeight, 350, 1, `768px product ${index + 1} image height`);
        });
        closeEnough(metrics.footerY, 2240.453125, 2, '768px footer boundary');
        closeEnough(metrics.height, 3039, 2, '768px document height');
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
