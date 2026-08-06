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

      await page.locator('[data-product-modal-link]').first().click();
      await page.locator('#commerce-modal .product-detail-page').waitFor();
      await page.locator('#commerce-modal .single_add_to_cart_button').click();

      await page.locator('.floating-actions a').first().click();
      await page.locator('#commerce-modal[data-commerce-type="cart"].is-open').waitFor();
      assert.ok(await page.locator('.commerce-cart-product').count() >= 1, `${width}px: product was not added to cart`);
      assert.equal(
        await page.locator('#billing_first_name, #billing_phone, #billing_email, #billing_city').count(),
        4,
        `${width}px: required checkout fields are missing`,
      );
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width, `${width}px: horizontal overflow detected`);

      await page.locator('.commerce-cart-clear').click();
      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log(`Commerce flow verified at: ${widths.join(', ')}px`);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
