const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const css = fs.readFileSync(path.join(__dirname, '../wp-content/themes/theobroma/style.css'), 'utf8');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 620, height: 700 } });
    await page.setContent(`
      <style>${css}</style>
      <div class="commerce-cart-checkout">
        <div id="payment" class="woocommerce-checkout-payment">
          <ul class="wc_payment_methods payment_methods methods">
            <li class="wc_payment_method payment_method_yookassa">
              <input id="payment_method_yookassa" type="radio" name="payment_method" checked>
              <label for="payment_method_yookassa">Онлайн-оплата <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="ЮKassa"></label>
              <div class="payment_box payment_method_yookassa"><p>Банковской картой или другим способом</p></div>
            </li>
            <li class="wc_payment_method payment_method_cod">
              <input id="payment_method_cod" type="radio" name="payment_method">
              <label for="payment_method_cod">Оплата при получении</label>
            </li>
          </ul>
        </div>
      </div>
    `);

    const styles = await page.evaluate(() => {
      const card = document.querySelector('.payment_method_yookassa');
      const selected = getComputedStyle(card);
      const label = getComputedStyle(card.querySelector('label'));
      const radio = getComputedStyle(card.querySelector('input'));
      const box = getComputedStyle(card.querySelector('.payment_box'));
      const second = card.nextElementSibling.getBoundingClientRect();
      const first = card.getBoundingClientRect();
      return {
        radius: selected.borderRadius,
        borderWidth: selected.borderTopWidth,
        labelDisplay: label.display,
        radioWidth: radio.width,
        radioHeight: radio.height,
        boxBackground: box.backgroundColor,
        gap: second.top - first.bottom,
      };
    });

    assert.ok(parseFloat(styles.radius) >= 13.5 && parseFloat(styles.radius) <= 15, `Unexpected card radius: ${styles.radius}`);
    assert.equal(styles.borderWidth, '1px');
    assert.equal(styles.labelDisplay, 'flex');
    assert.ok(parseFloat(styles.radioWidth) >= 19.5 && parseFloat(styles.radioWidth) <= 21);
    assert.ok(parseFloat(styles.radioHeight) >= 19.5 && parseFloat(styles.radioHeight) <= 21);
    assert.equal(styles.boxBackground, 'rgba(0, 0, 0, 0)');
    assert.ok(styles.gap >= 10, `Payment cards need breathing room, received ${styles.gap}px`);
  } finally {
    await browser.close();
  }
  console.log('Checkout payment-method cards verification passed.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
