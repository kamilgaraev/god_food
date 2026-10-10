const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const theme = path.join(root, 'wp-content/themes/theobroma');
const output = path.join(root, 'output/checkout-card-style');

(async () => {
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH });
  try {
    for (const width of [320, 390, 541, 900]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      await page.setContent(`
        <div class="commerce-cart-checkout" style="max-width:540px;margin:24px auto;padding:0 16px">
          <form class="checkout checkout-wizard">
            <div id="order_review">
              <section class="commerce-coupon">
                <label class="commerce-coupon__label" for="coupon">Промокод</label>
                <div class="commerce-coupon__controls"><input id="coupon" data-coupon-input placeholder="Введите промокод"><button type="button" data-coupon-apply>Применить</button></div>
                <div class="commerce-coupon__applied"><span>Промокод <strong>theo-test-100</strong></span><span class="commerce-coupon__discount">−100р.</span><button>Удалить</button></div>
              </section>
              <div id="payment" class="woocommerce-checkout-payment">
                <h3 class="theobroma-payment-heading">Оплата</h3>
                <ul class="wc_payment_methods"><li class="wc_payment_method">
                  <input id="online" type="radio" name="payment" checked><label for="online">Онлайн-оплата</label>
                  <div class="payment_box"><p>Банковской картой или другим способом</p></div>
                </li></ul>
                <section class="theobroma-loyalty-checkout">
                  <h3>Использовать бонусы</h3>
                  <p class="theobroma-loyalty-balance">Доступно: <strong>119р.</strong>. Можно списать до <strong>119р.</strong>.</p>
                  <div class="theobroma-loyalty-control"><label for="bonus">Сколько бонусов списать</label><input id="bonus" type="number" value="0.00"><button type="button" class="button">Применить</button></div>
                </section>
              </div>
            </div>
          </form>
        </div>
      `);
      await page.addStyleTag({ path: path.join(theme, 'style.css') });
      await page.addStyleTag({ path: path.join(theme, 'assets/css/checkout-steps.css') });
      await page.waitForFunction(() => getComputedStyle(document.querySelector('[data-coupon-apply]')).backgroundColor ===
        getComputedStyle(document.querySelector('.theobroma-loyalty-control button')).backgroundColor);
      const style = await page.evaluate(() => {
        const css = selector => getComputedStyle(document.querySelector(selector));
        const rect = selector => document.querySelector(selector).getBoundingClientRect();
        const coupon = css('.commerce-coupon');
        const paymentTitle = css('.theobroma-payment-heading');
        const methods = css('.wc_payment_methods');
        const loyalty = css('.theobroma-loyalty-checkout');
        const loyaltyTitle = css('.theobroma-loyalty-checkout h3');
        const couponButton = css('[data-coupon-apply]');
        const loyaltyButton = css('.theobroma-loyalty-control button');
        const couponControls = rect('.commerce-coupon__controls');
        const loyaltyControls = rect('.theobroma-loyalty-control');
        return {
          backgrounds:[coupon.backgroundColor,paymentTitle.backgroundColor,methods.backgroundColor,loyalty.backgroundColor],
          borders:[coupon.borderTopColor,paymentTitle.borderTopColor,methods.borderBottomColor,loyalty.borderTopColor],
          titles:[css('.commerce-coupon__label').font, paymentTitle.font, loyaltyTitle.font],
          buttonColors:[couponButton.backgroundColor,loyaltyButton.backgroundColor],
          buttonRadii:[couponButton.borderRadius,loyaltyButton.borderRadius],
          buttonHeights:[rect('[data-coupon-apply]').height,rect('.theobroma-loyalty-control button').height],
          headingRadius:paymentTitle.borderTopLeftRadius,
          methodsRadius:methods.borderBottomLeftRadius,
          controlsStacked:[rect('[data-coupon-apply]').top > rect('#coupon').top + 4,
            rect('.theobroma-loyalty-control button').top > rect('#bonus').top + 4],
          overflow:document.documentElement.scrollWidth - innerWidth,
          cardRight:rect('.commerce-coupon').right,
          loyaltyRight:rect('.theobroma-loyalty-checkout').right,
          viewport:innerWidth,
        };
      });
      assert.equal(new Set(style.backgrounds).size, 1, `${width}px: card backgrounds differ`);
      assert.equal(new Set(style.borders).size, 1, `${width}px: card borders differ`);
      assert.equal(new Set(style.titles).size, 1, `${width}px: card headings differ`);
      assert.equal(new Set(style.buttonColors).size, 1, `${width}px: apply button colors differ`);
      assert.equal(new Set(style.buttonRadii).size, 1, `${width}px: apply button radii differ`);
      assert.ok(style.buttonHeights.every(height => height >= 48), `${width}px: apply button is too short`);
      assert.equal(style.headingRadius, '14px');
      assert.equal(style.methodsRadius, '14px');
      assert.deepEqual(style.controlsStacked, width <= 390 ? [true,true] : [false,false],
        `${width}px: control layouts differ`);
      assert.ok(style.overflow <= 1 && style.cardRight <= width && style.loyaltyRight <= width,
        `${width}px: checkout cards overflow the viewport`);
      await page.locator('.commerce-cart-checkout').screenshot({ path: path.join(output, `cards-${width}.png`) });
      await page.close();
    }
  } finally { await browser.close(); }
  console.log('Checkout payment cards share one visual system at 320, 390, 541 and 900px.');
})().catch(error => { console.error(error); process.exitCode = 1; });
