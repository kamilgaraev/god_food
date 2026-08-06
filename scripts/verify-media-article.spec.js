const { chromium } = require('playwright');
const assert = require('node:assert/strict');

const url = process.env.THEOBROMA_URL || 'http://localhost:8080/kak-vybrat-nastoyashchiy-shokolad-dlya-rebenka/';
const widths = [390, 768, 1440];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      await page.goto(url, { waitUntil: 'networkidle' });

      assert.equal(await page.locator('.media-article-products').count(), 1, `${width}px: related products section is missing`);
      assert.equal(await page.locator('.media-article-products [data-product-modal-link]').count(), 9, `${width}px: every related product must have image, title and purchase modal links`);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width, `${width}px: horizontal overflow detected`);

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
