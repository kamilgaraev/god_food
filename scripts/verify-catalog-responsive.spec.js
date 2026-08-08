const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const viewport of [{ width: 390, height: 844 }, { width: 430, height: 932 }, { width: 768, height: 1024 }]) {
      const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/catalog/', { waitUntil: 'networkidle' });

      const metrics = await page.evaluate(() => {
        const products = [...document.querySelectorAll('ul.products li.product')].map((product) => {
          const box = product.getBoundingClientRect();
          const image = product.querySelector('img').getBoundingClientRect();
          return { x: box.x, y: box.y, width: box.width, height: box.height, imageWidth: image.width };
        });
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        return {
          products,
          footerY: footer.y,
          viewportWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
        };
      });

      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${viewport.width}px: horizontal overflow`);
      assert.equal(metrics.products.length, 6, `${viewport.width}px: all catalog products must remain visible`);
      assert.ok(metrics.products.every((product) => product.x >= 0 && product.x + product.width <= viewport.width), `${viewport.width}px: product is clipped`);
      assert.ok(Math.abs(metrics.products[0].y - metrics.products[1].y) < 2, `${viewport.width}px: first row is misaligned`);
      assert.ok(metrics.products[2].y > metrics.products[0].y, `${viewport.width}px: second row does not follow the first`);
      assert.ok(metrics.products[4].y > metrics.products[2].y, `${viewport.width}px: third row does not follow the second`);
      assert.ok(metrics.products.every((product) => Math.abs(product.imageWidth - product.width + (viewport.width < 600 ? 20 : 0)) < 2), `${viewport.width}px: product image sizing changed`);
      assert.ok(metrics.footerY > metrics.products[5].y + metrics.products[5].height, `${viewport.width}px: footer overlaps products`);
      await context.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Responsive catalog verified at 390, 430 and 768px');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
