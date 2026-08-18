const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const viewport of [
      { width: 597, height: 898 },
      { width: 339, height: 898 },
      { width: 390, height: 844 },
      { width: 430, height: 932 },
      { width: 768, height: 1024 },
    ]) {
      const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/catalog/', { waitUntil: 'networkidle' });

      const metrics = await page.evaluate(() => {
        const products = [...document.querySelectorAll('ul.products li.product')].map((product) => {
          const box = product.getBoundingClientRect();
          const image = product.querySelector('img').getBoundingClientRect();
          const button = product.querySelector('.button').getBoundingClientRect();
          const copyBottom = Math.max(
            ...['.woocommerce-loop-product__title', '.catalog-product-description', '.price']
              .map((selector) => product.querySelector(selector)?.getBoundingClientRect().bottom || 0),
          );
          return {
            x: box.x,
            y: box.y,
            width: box.width,
            height: box.height,
            imageWidth: image.width,
            copyButtonGap: button.top - copyBottom,
          };
        });
        const filters = [...document.querySelectorAll('.catalog-filters a')].map((filter) => {
          const box = filter.getBoundingClientRect();
          return { left: box.left, right: box.right, top: Math.round(box.top) };
        });
        const rowsByTop = filters.reduce((rows, filter) => {
          const row = rows.get(filter.top) || [];
          row.push(filter);
          rows.set(filter.top, row);
          return rows;
        }, new Map());
        const filterRows = [...rowsByTop.values()].map((row) => ({
          left: Math.min(...row.map((filter) => filter.left)),
          right: Math.max(...row.map((filter) => filter.right)),
        }));
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        return {
          products,
          filters,
          filterRows,
          footerY: footer.y,
          viewportWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
        };
      });

      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${viewport.width}px: horizontal overflow`);
      if (viewport.width <= 600) {
        assert.ok(metrics.products.every((product) => product.copyButtonGap >= 8), `${viewport.width}px: product copy overlaps the add-to-cart button`);
      }
      assert.ok(metrics.filters.every((filter) => filter.left >= -1 && filter.right <= viewport.width + 1), `${viewport.width}px: catalog filter is clipped`);
      assert.ok(metrics.filterRows.every((row) => Math.abs((row.left + row.right) / 2 - viewport.width / 2) <= 2), `${viewport.width}px: catalog filter row is not centered`);
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

  console.log('Responsive catalog verified at 339, 390, 430, 597 and 768px');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
