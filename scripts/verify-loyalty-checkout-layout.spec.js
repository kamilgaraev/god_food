// Run against a disposable WooCommerce fixture with a funded customer and a product.
// BASE_URL, TEST_USER, TEST_PASSWORD, TEST_PRODUCT_ID and TEST_PAGE_ID identify that fixture.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

(async () => {
  const base = process.env.BASE_URL || 'http://localhost:8080';
  const { TEST_USER, TEST_PASSWORD, TEST_PRODUCT_ID, TEST_PAGE_ID } = process.env;
  assert.ok(TEST_USER && TEST_PASSWORD && TEST_PRODUCT_ID && TEST_PAGE_ID, 'Disposable fixture settings are required');
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    await page.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
    // Exercise this worktree's CSS with the real WooCommerce markup and stylesheet cascade.
    await page.route('**/themes/theobroma/style.css*', route => route.fulfill({
      contentType: 'text/css', body: fs.readFileSync(path.join(__dirname, '../wp-content/themes/theobroma/style.css'), 'utf8')
    }));
    await page.goto(`${base}/wp-login.php`);
    await page.request.post(`${base}/wp-login.php`, { form: {
      log: TEST_USER, pwd: TEST_PASSWORD, 'wp-submit': 'Log In', testcookie: '1'
    }});
    await page.goto(`${base}/?page_id=${TEST_PAGE_ID}`);
    const config = await page.evaluate(() => window.theobromaCommerce);
    await page.request.post(config.ajaxUrl, { form: { action: 'theobroma_cart_update', nonce: config.nonce, clear: '1' } });
    await page.goto(`${base}/?page_id=${TEST_PAGE_ID}&add-to-cart=${TEST_PRODUCT_ID}`);
    const checkoutUpdated = page.waitForResponse(response => response.url().includes('wc-ajax=update_order_review') && response.ok());
    await page.locator('[data-commerce-cart-open]:visible').first().click();
    await checkoutUpdated;
    await page.locator('.commerce-cart-checkout .blockUI').waitFor({ state: 'hidden' });
    await page.locator('#theobroma_bonus_amount').waitFor();
    assert.ok(await page.locator('#payment .wc_payment_method').count(), 'Fixture must offer payment methods');
    await page.locator('#payment_method_cod').check();
    await page.waitForFunction(() => !window.jQuery('#payment .payment_box').is(':animated'));
    fs.mkdirSync('output/playwright', { recursive: true });
    for (const width of [541, 390, 320, 900, 1280]) {
      await page.setViewportSize({ width, height: 1000 });
      const metrics = await page.evaluate(() => {
        const rect = selector => document.querySelector(selector).getBoundingClientRect().toJSON();
        const panel = document.querySelector('.theobroma-loyalty-checkout');
        const button = panel.querySelector('button');
        const methods = document.querySelector('#payment .wc_payment_methods');
        return {
          panel: rect('.theobroma-loyalty-checkout'), control: rect('.theobroma-loyalty-control'),
          input: rect('#theobroma_bonus_amount'), button: rect('[data-theobroma-bonus-apply]'),
          status: rect('.theobroma-loyalty-status'), methods: methods.getBoundingClientRect().toJSON(),
          lastMethod: methods.lastElementChild.getBoundingClientRect().toJSON(),
          padding: parseFloat(getComputedStyle(panel).paddingRight),
          background: getComputedStyle(button).backgroundColor,
          radius: parseFloat(getComputedStyle(button).borderRadius),
          rem: parseFloat(getComputedStyle(document.documentElement).fontSize)
        };
      });
      console.log(width, JSON.stringify(metrics));
      // Catch compounded payment margins and the button overflowing the panel's padding.
      const gapBelow = metrics.panel.top - metrics.methods.bottom;
      const gapAbove = metrics.methods.bottom - metrics.lastMethod.bottom - 1; // divider border
      assert.ok(gapBelow >= metrics.rem && gapBelow <= metrics.rem * 1.6, 'Payment divider needs a compact, nonzero gap');
      assert.ok(Math.abs(gapAbove - gapBelow) <= 1.5, 'Payment divider spacing must be balanced');
      assert.ok(metrics.button.right <= metrics.panel.right - metrics.padding + 1, 'Button overflows the panel padding');
      assert.ok(Math.abs(metrics.input.height - metrics.button.height) <= 1, 'Input and button heights differ');
      assert.ok(metrics.button.height >= 44, 'Apply button must remain touch-sized');
      assert.ok(metrics.radius >= 12, 'WooCommerce must not replace the rounded button');
      assert.notEqual(metrics.background, 'rgb(233, 230, 237)', 'WooCommerce default button overrides the theme');
      assert.ok(metrics.status.height <= 1, 'Empty status must not leave a blank row');
      assert.ok(metrics.control.width <= metrics.panel.width - metrics.padding * 2 + 1, 'Controls overflow the card');
      await page.locator('#payment').screenshot({ path: `output/playwright/loyalty-layout-${width}.png` });
    }
    await page.locator('#theobroma_bonus_amount').focus();
    await page.keyboard.press('Tab');
    assert.notEqual(await page.locator('[data-theobroma-bonus-apply]').evaluate(el => getComputedStyle(el).outlineStyle), 'none', 'Keyboard focus must be visible');
    await page.locator('.theobroma-loyalty-status').evaluate(el => { el.textContent = 'Проверьте сумму списания'; el.classList.add('is-error'); });
    assert.ok(await page.locator('.theobroma-loyalty-status').isVisible(), 'Nonempty status must remain visible');
    console.log('Loyalty layout checks passed.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
