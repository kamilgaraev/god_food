const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const base = process.env.THEOBROMA_URL || 'https://theobroma.one/';
const output = path.resolve('output/checkout-coupon');

async function waitForStatus(page, expected) {
  await page.waitForFunction(message => document.querySelector('#commerce-modal [data-coupon-status]')?.textContent.trim() === message,
    expected, { timeout: 30000 });
}

async function testWidth(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 900 }, isMobile: width <= 600, hasTouch: width <= 600 });
  try {
    await page.addInitScript(() => localStorage.setItem('theobroma_cookie_notice_accepted', '1'));
    const response = await page.goto(new URL('/?add-to-cart=337', base).href, { waitUntil: 'domcontentloaded', timeout: 60000 });
    assert.equal(response.status(), 200);
    await page.locator('[data-commerce-cart-open]').first().click();
    const modal = page.locator('#commerce-modal');
    const form = modal.locator('.commerce-cart-checkout form.checkout');
    const coupon = modal.locator('.commerce-coupon');
    await coupon.waitFor({ state: 'attached', timeout: 30000 });
    assert.equal(await coupon.count(), 1);
    const initialStyle = await coupon.evaluate(element => ({
      media: document.querySelector('#theobroma-checkout-steps-css')?.media,
      border: getComputedStyle(element).borderTopWidth,
    }));
    assert.equal(initialStyle.media, 'all', `${width}: checkout stylesheet remains deferred`);
    assert.equal(initialStyle.border, '1px', `${width}: checkout modal opened before styles loaded`);
    assert(await coupon.locator('[data-coupon-input]').getAttribute('placeholder'));
    assert(await coupon.evaluate(element => element.closest('#order_review') !== null), 'Coupon is outside payment step');
    assert.equal(await form.getAttribute('data-checkout-step'), '0');

    // Keep delivery integrations untouched: expose the payment pane for the
    // coupon interaction, without submitting an order or selecting shipping.
    const reveal = () => form.locator('#order_review').evaluate(element => { element.hidden = false; });
    await reveal();
    const input = coupon.locator('[data-coupon-input]');
    await input.fill('NOT-A-REAL-COUPON');
    await input.press('Enter');
    await page.waitForFunction(() => document.querySelector('#commerce-modal [data-coupon-status]')?.classList.contains('is-error'));
    assert.equal(await coupon.locator('[data-coupon-remove]').count(), 0);

    const apply = async (code, expectedDiscount) => {
      await reveal();
      await input.fill(code);
      await input.press('Enter');
      await waitForStatus(page, 'Промокод применён.');
      assert.equal(await coupon.locator('[data-coupon-remove]').count(), 1);
      const label = await coupon.locator('.commerce-coupon__discount').innerText();
      const amount = Number(label.replace(/[^\d,\.]/g, '').replace(',', '.'));
      assert(Math.abs(amount - expectedDiscount) < 0.02, `${code}: unexpected discount ${label}`);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${width}: horizontal overflow`);
      await reveal();
      const style = await coupon.evaluate(element => {
        const link = document.querySelector('link[href*="checkout-steps.css"]');
        return { media: link?.media, loaded: Boolean(link?.sheet), border: getComputedStyle(element).borderTopWidth };
      });
      console.log(`${width}: checkout style ${JSON.stringify(style)}`);
      assert.equal(style.border, '1px', `${width}: checkout coupon styles were not applied`);
      await coupon.screenshot({ path: path.join(output, `coupon-${code.toLowerCase()}-${width}.png`) });
      console.log(`${width}: ${code} applied, discount ${label}`);
    };
    const remove = async () => {
      await reveal();
      await coupon.locator('[data-coupon-remove]').click();
      await waitForStatus(page, 'Промокод удалён.');
      assert.equal(await coupon.locator('[data-coupon-remove]').count(), 0);
    };

    // The store displays roubles without fractional digits: 29.9 rounds to 30.
    await apply('THEO-TEST-10', 30);
    await remove();
    await apply('THEO-TEST-100', 100);
    await remove();
    assert.equal(await coupon.locator('[data-coupon-remove]').count(), 0);
    console.log(`${width}: invalid code, apply, remove, and payment-pane layout passed`);
  } finally {
    await page.close();
  }
}

(async () => {
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH,
    args: ['--no-proxy-server', '--disable-http2'] });
  try {
    for (const width of (process.env.COUPON_WIDTHS || '390,1280').split(',').map(Number)) await testWidth(browser, width);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
