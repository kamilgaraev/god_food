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
      const logo = getComputedStyle(card.querySelector('img'));
      const second = card.nextElementSibling.getBoundingClientRect();
      const first = card.getBoundingClientRect();
      return {
        radius: selected.borderRadius,
        borderWidth: selected.borderTopWidth,
        labelDisplay: label.display,
        radioWidth: radio.width,
        radioHeight: radio.height,
        boxBackground: box.backgroundColor,
        logoDisplay: logo.display,
        gap: second.top - first.bottom,
      };
    });

    assert.ok(parseFloat(styles.radius) >= 13.5 && parseFloat(styles.radius) <= 15, `Unexpected card radius: ${styles.radius}`);
    assert.equal(styles.borderWidth, '1px');
    assert.equal(styles.labelDisplay, 'block');
    assert.ok(parseFloat(styles.radioWidth) >= 19.5 && parseFloat(styles.radioWidth) <= 21);
    assert.ok(parseFloat(styles.radioHeight) >= 19.5 && parseFloat(styles.radioHeight) <= 21);
    assert.equal(styles.boxBackground, 'rgba(0, 0, 0, 0)');
    assert.equal(styles.logoDisplay, 'none');
    assert.ok(styles.gap >= 10, `Payment cards need breathing room, received ${styles.gap}px`);

    for (const width of [320, 390, 620]) {
      await page.setViewportSize({ width, height: 700 });
      const layout = await page.evaluate(() => {
        const card = document.querySelector('.payment_method_yookassa');
        const labelElement = card.querySelector('label');
        const label = labelElement.getBoundingClientRect();
        const radio = card.querySelector('input').getBoundingClientRect();
        const textRange = document.createRange();
        textRange.selectNodeContents(labelElement.firstChild);
        const text = textRange.getBoundingClientRect();
        const box = card.querySelector('.payment_box').getBoundingClientRect();
        const codLabel = document.querySelector('.payment_method_cod label');
        const codRect = codLabel.getBoundingClientRect();
        const codLineHeight = parseFloat(getComputedStyle(codLabel).lineHeight);
        return {
          cardOverflow: card.scrollWidth - card.clientWidth,
          labelWidth: label.width,
          radioTextGap: text.left - radio.right,
          radioCenterDelta: Math.abs((radio.top + radio.height / 2) - (text.top + text.height / 2)),
          descriptionGap: box.top - label.bottom,
          codLines: codRect.height / codLineHeight,
        };
      });
      assert.ok(layout.cardOverflow <= 1, `Payment card overflows at ${width}px`);
      assert.ok(layout.labelWidth >= 200, `Payment label is squeezed at ${width}px`);
      assert.ok(layout.radioTextGap >= 8 && layout.radioTextGap <= 20, `Radio is detached from its label at ${width}px`);
      assert.ok(layout.radioCenterDelta <= 3, `Radio is not vertically aligned at ${width}px`);
      assert.ok(layout.descriptionGap >= 8, `Description overlaps the label at ${width}px`);
      assert.ok(layout.codLines <= 1.2, `COD label wraps at ${width}px`);
    }
  } finally {
    await browser.close();
  }
  console.log('Checkout payment-method cards verification passed.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
