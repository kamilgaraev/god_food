const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const url = process.env.THEOBROMA_URL || 'http://localhost:8080/kak-vybrat-nastoyashchiy-shokolad-dlya-rebenka/';
const baseUrl = new URL('/', url).href;
const widths = (process.env.THEOBROMA_WIDTHS || '390,768,1440').split(',').map(Number);
const themeCss = process.env.THEOBROMA_THEME_CSS;

async function useThemeCss(page) {
  if (!themeCss) return;
  await page.route(/\/wp-content\/themes\/theobroma\/style\.css(?:\?.*)?$/, (route) => route.fulfill({
    contentType: 'text/css; charset=utf-8',
    body: fs.readFileSync(themeCss),
  }));
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of widths) {
      const catalog = await browser.newPage({ viewport: { width, height: 1000 } });
      await useThemeCss(catalog);
      await catalog.goto(new URL('/catalog/', baseUrl).href, { waitUntil: 'networkidle' });
      assert.equal(
        await catalog.locator('.catalog-page ul.products li.product > a.add_to_cart_button:visible').count(),
        6,
        `${width}px: catalog add-to-cart buttons are hidden`,
      );
      await catalog.close();

      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      await useThemeCss(page);
      await page.goto(url, { waitUntil: 'networkidle' });

      await page.locator('[data-product-modal-link]').first().click();
      await page.locator('#commerce-modal .product-detail-page').waitFor();
      const productAccordions = page.locator('#commerce-modal .product-detail-accordions details');
      assert.equal(await productAccordions.count(), 2, `${width}px: product accordions are missing`);
      assert.equal(await productAccordions.nth(0).getAttribute('open'), '', `${width}px: product description must be open by default`);
      assert.equal(await productAccordions.nth(1).getAttribute('open'), null, `${width}px: product benefit must be closed by default`);
      if (width >= 1200) {
        assert.equal(
          await page.locator('#commerce-modal').evaluate((element) => getComputedStyle(element).backgroundColor),
          'rgba(74, 74, 74, 0.8)',
          `${width}px: product modal backdrop must match the source overlay`,
        );
      }
      if (width === 768) {
        const panelBox = await page.locator('#commerce-modal .commerce-modal-panel').boundingBox();
        const imageBox = await page.locator('#commerce-modal .product-detail-image').boundingBox();
        assert.equal(Math.round(panelBox.width), 640, '768px: product panel width differs from source');
        assert.deepEqual(
          { width: Math.round(imageBox.width), height: Math.round(imageBox.height) },
          { width: 600, height: 798 },
          '768px: product image geometry differs from source',
        );
      }
      await page.locator('#commerce-modal .single_add_to_cart_button').click();

      await page.locator('#commerce-modal[data-commerce-type="cart"].is-open').waitFor();
      await page.locator('.commerce-cart-product').first().waitFor();
      assert.ok(await page.locator('.commerce-cart-product').count() >= 1, `${width}px: product was not added to cart`);
      assert.equal(
        await page.locator('#billing_first_name, #billing_phone, #billing_email, #billing_city').count(),
        4,
        `${width}px: required checkout fields are missing`,
      );
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width, `${width}px: horizontal overflow detected`);

      await page.locator('.commerce-cart-remove').first().click();
      await page.locator('.commerce-cart--empty').waitFor();
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
