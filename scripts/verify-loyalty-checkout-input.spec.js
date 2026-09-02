const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = process.env.BASE_URL || 'http://localhost:8080';
const markup = (value) => `<form class="checkout"><section class="theobroma-loyalty-checkout">
  <input id="theobroma_bonus_amount" type="number" value="${value}">
  <button type="button" data-theobroma-bonus-apply>Применить</button>
  <p class="theobroma-loyalty-status"></p>
</section></form>`;

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    // Use the same real jQuery shipped by the local WordPress runtime.
    await page.setContent(markup('0'));
    await page.addScriptTag({ url: `${baseUrl}/wp-includes/js/jquery/jquery.min.js` });
    await page.evaluate(() => {
      window.theobromaLoyalty = { ajaxUrl: 'http://loyalty.test/bonus', nonce: 'test-nonce' };
      window.checkoutUpdates = 0;
      jQuery(document.body).on('update_checkout', () => { window.checkoutUpdates++; });
    });
    await page.addScriptTag({ path: path.resolve(__dirname, '../wp-content/plugins/theobroma-commerce/assets/js/loyalty-checkout.js') });

    await page.locator('#theobroma_bonus_amount').fill('200');
    await page.evaluate((html) => {
      jQuery('form.checkout').replaceWith(html);
      jQuery(document.body).trigger('updated_checkout');
    }, markup('0'));
    assert.equal(await page.locator('#theobroma_bonus_amount').inputValue(), '200', 'Checkout refresh must preserve an unapplied amount');

    await page.route('http://loyalty.test/bonus', async (route) => {
      const data = new URLSearchParams(route.request().postData());
      assert.equal(data.get('amount'), '200');
      await route.fulfill({ contentType: 'application/json', headers: { 'access-control-allow-origin': '*' }, body: JSON.stringify({ success: true, data: { accepted: '100.00', message: 'Бонусы применены.' } }) });
    });
    await page.locator('[data-theobroma-bonus-apply]').click();
    await page.waitForFunction(() => window.checkoutUpdates === 1);
    assert.equal(await page.locator('#theobroma_bonus_amount').inputValue(), '100.00');
    await page.evaluate((html) => {
      jQuery('form.checkout').replaceWith(html);
      jQuery(document.body).trigger('updated_checkout');
    }, markup('100.00'));
    assert.equal(await page.locator('#theobroma_bonus_amount').inputValue(), '100.00', 'Accepted server amount must replace the old draft');
    console.log('Loyalty input survives checkout refresh and accepts the server limit.');
  } finally {
    await browser.close();
  }
})().catch((error) => { console.error(error); process.exitCode = 1; });
