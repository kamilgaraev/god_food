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
      { width: 600, height: 900 },
      { width: 601, height: 900 },
    ]) {
      const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/catalog/', { waitUntil: 'networkidle' });

      const metrics = await page.evaluate(() => {
        const products = [...document.querySelectorAll('ul.products li.product')].map((product) => {
          const box = product.getBoundingClientRect();
          const image = product.querySelector('img').getBoundingClientRect();
          const button = product.querySelector('.button').getBoundingClientRect();
          const copyElements = ['.woocommerce-loop-product__title', '.catalog-product-description', '.price']
            .map((selector) => product.querySelector(selector));
          const hasCompleteCopy = copyElements.every(Boolean);
          const copyBottom = hasCompleteCopy
            ? Math.max(...copyElements.map((element) => element.getBoundingClientRect().bottom))
            : Number.POSITIVE_INFINITY;
          return {
            x: box.x,
            y: box.y,
            width: box.width,
            height: box.height,
            imageWidth: image.width,
            hasCompleteCopy,
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
          count: row.length,
          left: Math.min(...row.map((filter) => filter.left)),
          right: Math.max(...row.map((filter) => filter.right)),
        }));
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        const productGrid = document.querySelector('ul.products').getBoundingClientRect();
        return {
          products,
          productGridWidth: productGrid.width,
          filters,
          filterRows,
          footerY: footer.y,
          viewportWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
        };
      });

      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${viewport.width}px: horizontal overflow`);
      assert.equal(metrics.filters.length, 5, `${viewport.width}px: all catalog filters must be present`);
      if (viewport.width <= 600) {
        assert.deepEqual(metrics.filterRows.map((row) => row.count), [2, 2, 1], `${viewport.width}px: catalog filters must use a 2+2+1 layout`);
        assert.ok(metrics.products.every((product) => product.hasCompleteCopy), `${viewport.width}px: catalog product copy is incomplete`);
        assert.ok(metrics.products.every((product) => product.copyButtonGap >= 8), `${viewport.width}px: product copy overlaps the add-to-cart button`);
      }
      assert.ok(metrics.filters.every((filter) => filter.left >= -1 && filter.right <= viewport.width + 1), `${viewport.width}px: catalog filter is clipped`);
      assert.ok(metrics.filterRows.every((row) => Math.abs((row.left + row.right) / 2 - viewport.width / 2) <= 2), `${viewport.width}px: catalog filter row is not centered`);
      assert.equal(metrics.products.length, 6, `${viewport.width}px: all catalog products must remain visible`);
      assert.ok(metrics.products.every((product) => product.x >= 0 && product.x + product.width <= viewport.width), `${viewport.width}px: product is clipped`);
      if (viewport.width <= 600) {
        assert.ok(metrics.products.slice(1).every((product, index) => product.y > metrics.products[index].y), `${viewport.width}px: catalog must show one product per row`);
        assert.ok(metrics.products.every((product) => product.width >= metrics.productGridWidth - 2), `${viewport.width}px: catalog product must fill its row`);
      } else {
        assert.ok(Math.abs(metrics.products[0].y - metrics.products[1].y) < 2, `${viewport.width}px: first row is misaligned`);
        assert.ok(metrics.products[2].y > metrics.products[0].y, `${viewport.width}px: second row does not follow the first`);
        assert.ok(metrics.products[4].y > metrics.products[2].y, `${viewport.width}px: third row does not follow the second`);
      }
      assert.ok(metrics.products.every((product) => Math.abs(product.imageWidth - product.width + (viewport.width <= 600 ? 20 : 0)) < 2), `${viewport.width}px: product image sizing changed`);
      assert.ok(metrics.footerY > metrics.products[5].y + metrics.products[5].height, `${viewport.width}px: footer overlaps products`);
      await context.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Responsive catalog verified at 339, 390, 430, 597, 600, 601 and 768px');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
