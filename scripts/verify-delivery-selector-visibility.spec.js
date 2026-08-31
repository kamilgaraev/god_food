const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const themeCss = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/style.css'), 'utf8');
const deliveryCss = fs.readFileSync(path.join(root, 'wp-content/plugins/theobroma-commerce/assets/css/checkout-delivery.css'), 'utf8');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 620, height: 900 } });
    await page.setContent(`
      <style>${themeCss}</style>
      <style>${deliveryCss}</style>
      <div class="commerce-cart-checkout">
        <table class="shop_table woocommerce-checkout-review-order-table">
          <thead><tr><th>Товар</th></tr></thead>
          <tbody><tr class="cart_item"><td>Шоколад</td></tr></tbody>
          <tfoot>
            <tr class="cart-subtotal"><th>Подытог</th><td>1000 ₽</td></tr>
            <tr class="woocommerce-shipping-totals shipping">
              <th>Доставка</th>
              <td><ul class="woocommerce-shipping-methods"><li><input type="radio" checked><label>Ozon Доставка — выбрать способ</label><button class="theobroma-delivery-open">Выбрать доставку</button></li></ul></td>
            </tr>
            <tr class="order-total"><th>Итого</th><td>1000 ₽</td></tr>
          </tfoot>
        </table>
      </div>
    `);

    const visibility = await page.evaluate(() => {
      const row = document.querySelector('.woocommerce-shipping-totals');
      const card = document.querySelector('.woocommerce-shipping-methods li');
      const button = document.querySelector('.theobroma-delivery-open');
      return {
        table: getComputedStyle(document.querySelector('.shop_table')).display,
        header: getComputedStyle(document.querySelector('thead')).display,
        cartItemVisible: document.querySelector('.cart_item').getClientRects().length > 0,
        subtotal: getComputedStyle(document.querySelector('.cart-subtotal')).display,
        shipping: getComputedStyle(row).display,
        headingBorder: getComputedStyle(row.querySelector('th')).borderTopWidth,
        cardRadius: parseFloat(getComputedStyle(card).borderRadius),
        cardBorder: getComputedStyle(card).borderTopWidth,
        buttonRadius: parseFloat(getComputedStyle(button).borderRadius),
        buttonDecoration: getComputedStyle(button).textDecorationLine,
        buttonVisible: button.getClientRects().length > 0,
      };
    });

    assert.equal(visibility.table, 'table');
    assert.equal(visibility.header, 'none');
    assert.equal(visibility.cartItemVisible, false);
    assert.equal(visibility.subtotal, 'none');
    assert.equal(visibility.shipping, 'table-row');
    assert.equal(visibility.headingBorder, '0px');
    assert.ok(visibility.cardRadius >= 12);
    assert.equal(visibility.cardBorder, '1px');
    assert.ok(visibility.buttonRadius >= 8);
    assert.equal(visibility.buttonDecoration, 'none');
    assert.equal(visibility.buttonVisible, true);
  } finally {
    await browser.close();
  }
  console.log('Delivery selector visibility verification passed.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
