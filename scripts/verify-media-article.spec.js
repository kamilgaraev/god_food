const { chromium } = require('playwright');
const assert = require('node:assert/strict');

const url = process.env.THEOBROMA_URL || 'http://localhost:8080/kak-vybrat-nastoyashchiy-shokolad-dlya-rebenka/';
const widths = [390, 430, 768, 1440];
const mobileSharedMetrics = {
  390: { title: { x: 20, y: 70, width: 350, height: 103.3125 }, image: { x: 20, y: 188.3125, width: 350, height: 437.5 }, copy: { x: 20, y: 645.8125, width: 350, height: 180.34375 }, sourceY: 842.15625 },
  430: { title: { x: 20, y: 70, width: 390, height: 68.875 }, image: { x: 20, y: 153.875, width: 390, height: 487.5 }, copy: { x: 20, y: 661.375, width: 390, height: 160.75 }, sourceY: 838.125 },
};
const tabletSourceMetrics = {
  title: { x: 64, y: 95, width: 640, height: 78.71875 },
  image: { x: 64, y: 203.71875, width: 640, height: 800 },
  copy: { x: 64, y: 1033.71875, width: 640 },
  sourceY: 1189.0625,
};

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      await page.goto(url, { waitUntil: 'networkidle' });

      assert.equal(await page.locator('.media-article-products').count(), 1, `${width}px: related products section is missing`);
      assert.equal(await page.locator('.media-article-products [data-product-modal-link]').count(), 9, `${width}px: every related product must have image, title and purchase modal links`);
      assert.equal(await page.locator('.media-article-related').count(), 1, `${width}px: related articles section is missing`);
      assert.equal(await page.locator('.media-article-related article').count(), 3, `${width}px: three related articles are required`);
      assert.equal(await page.evaluate(() => [...document.querySelectorAll('.media-article-related a[href]')]
        .some((link) => new URL(link.href).pathname === location.pathname)), false, `${width}px: current article must not be recommended`);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width, `${width}px: horizontal overflow detected`);
      assert.equal(await page.evaluate(() => {
        const source = document.querySelector('.media-article-source');
        const products = document.querySelector('.media-article-products');
        return Boolean(source.compareDocumentPosition(products) & Node.DOCUMENT_POSITION_FOLLOWING);
      }), true, `${width}px: source link and date must precede related products`);

      if (mobileSharedMetrics[width]) {
        const metrics = await page.evaluate(() => {
          const rect = (selector) => {
            const box = document.querySelector(selector).getBoundingClientRect();
            return { x: box.x, y: box.y + scrollY, width: box.width, height: box.height };
          };
          return { title: rect('.media-article h1'), image: rect('.media-article figure img'), copy: rect('.media-article-copy'), sourceY: rect('.media-article-source').y };
        });
        const expected = mobileSharedMetrics[width];
        for (const part of ['title', 'image', 'copy']) {
          for (const [metric, target] of Object.entries(expected[part])) closeEnough(metrics[part][metric], target, 1, `${width}px ${part} ${metric}`);
        }
        closeEnough(metrics.sourceY, expected.sourceY, 1, `${width}px source link y`);
      }
      if (width === 768) {
        const metrics = await page.evaluate(() => {
          const rect = (selector) => {
            const box = document.querySelector(selector).getBoundingClientRect();
            return { x: box.x, y: box.y + scrollY, width: box.width, height: box.height };
          };
          return { title: rect('.media-article h1'), image: rect('.media-article figure img'), copy: rect('.media-article-copy'), sourceY: rect('.media-article-source').y };
        });
        for (const part of ['title', 'image', 'copy']) {
          for (const [metric, target] of Object.entries(tabletSourceMetrics[part])) closeEnough(metrics[part][metric], target, 1, `768px ${part} ${metric}`);
        }
        closeEnough(metrics.sourceY, tabletSourceMetrics.sourceY, 1, '768px source link y');
      }

      await page.locator('.media-article-products [data-product-modal-link]').first().click();
      await page.locator('#commerce-modal.is-open').waitFor();
      await page.locator('#commerce-modal .product-detail-page').waitFor();
      assert.equal(await page.locator('#commerce-modal .product-detail-page').count(), 1, `${width}px: product modal did not render`);
      const closeControl = width <= 600 ? '.commerce-modal-back' : '.commerce-modal-close';
      await page.locator(closeControl).click();
      await page.locator('#commerce-modal').waitFor({ state: 'hidden' });
      assert.equal(await page.locator('#commerce-modal').getAttribute('hidden'), '', `${width}px: product modal did not close`);

      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log(`Media article verified at: ${widths.join(', ')}px`);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
