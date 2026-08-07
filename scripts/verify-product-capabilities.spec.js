const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

function fixture(action) {
  return execFileSync('docker', [
    'compose', 'exec', '-T', 'wordpress', 'php',
    '/opt/theobroma-scripts/product-capability-fixture.php', action,
  ], { cwd: __dirname + '/..', encoding: 'utf8' }).trim();
}

(async () => {
  const data = JSON.parse(fixture('create'));
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 768, 1440]) {
      const context = await browser.newContext({ viewport: { width, height: width === 390 ? 932 : 1000 } });
      const page = await context.newPage();
      const errors = [];
      const addToCartResponses = [];
      page.on('pageerror', (error) => errors.push(error.message));
      page.on('response', async (response) => {
        if (response.url().includes('wc-ajax=add_to_cart')) {
          addToCartResponses.push({ status: response.status(), body: await response.text().catch(() => '') });
        }
      });
      await page.goto(data.url, { waitUntil: 'networkidle', timeout: 60_000 });
      await page.locator('#commerce-modal[data-commerce-type="product"].is-open .product-detail-page').waitFor();

      const thumbnails = page.locator('[data-product-gallery-image]');
      assert.equal(await thumbnails.count(), 9, `${width}px product gallery must render all nine images`);
      const select = page.locator('form.variations_form select').first();
      assert.equal(await select.count(), 1, `${width}px variable product selector is missing`);
      const choices = await select.locator('option').evaluateAll((options) => options
        .filter((option) => option.value !== '')
        .map((option) => option.textContent.trim()));
      assert.deepEqual(choices, ['100g', '200g']);

      const originalImage = await page.locator('[data-product-main-image]').getAttribute('src');
      await select.selectOption({ label: '200g' });
      await page.locator('.single_variation_wrap .woocommerce-variation-price').filter({ hasText: '200' }).waitFor();
      const variationImage = await page.locator('[data-product-main-image]').getAttribute('src');
      assert.ok(variationImage && variationImage !== originalImage, `${width}px variation did not switch to its own image`);
      const geometry = await page.evaluate(() => Object.fromEntries([
        ['buy', '.product-detail-buy'],
        ['form', 'form.variations_form'],
        ['button', '.single_add_to_cart_button'],
        ['accordions', '.product-detail-accordions'],
      ].map(([key, selector]) => {
        const rect = document.querySelector(selector)?.getBoundingClientRect();
        return [key, rect ? { x: rect.x, y: rect.y, width: rect.width, height: rect.height } : null];
      })));
      console.log(`${width}px gallery and variation selection passed ${JSON.stringify(geometry)}`);
      await page.locator('.single_add_to_cart_button:not(.disabled)').click();
      try {
        await page.locator('#commerce-modal[data-commerce-type="cart"].is-open .commerce-cart-product').waitFor({ timeout: 10_000 });
      } catch {
        const diagnostics = await page.evaluate(() => ({
          url: location.href,
          modalType: document.querySelector('#commerce-modal')?.dataset.commerceType || '',
          variationId: document.querySelector('input.variation_id')?.value || '',
          cartCount: document.querySelector('.cart-count')?.textContent?.trim() || '',
          button: document.querySelector('.single_add_to_cart_button')?.outerHTML || '',
        }));
        throw new Error(`${width}px variable product was not added to cart: ${JSON.stringify({ diagnostics, addToCartResponses })}`);
      }
      assert.deepEqual(errors, [], `${width}px browser errors: ${errors.join('; ')}`);
      await context.close();
    }
    console.log('Nine-image gallery and variable product checkout passed at 390, 768 and 1440px');
  } finally {
    await browser.close();
    fixture('cleanup');
  }
})().catch((error) => {
  try { fixture('cleanup'); } catch {}
  console.error(error);
  process.exitCode = 1;
});
