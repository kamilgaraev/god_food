const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const script = fs.readFileSync(path.join(root, 'wp-content/plugins/theobroma-commerce/assets/js/checkout.js'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'wp-content/plugins/theobroma-commerce/assets/css/checkout-delivery.css'), 'utf8');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1100, height: 760 } });
    await page.setContent(`
      <style>${styles}</style>
      <div class="commerce-cart-checkout">
        <div class="woocommerce-billing-fields__field-wrapper">
        <p id="billing_first_name_field"><input id="billing_first_name"></p>
        <p id="billing_phone_field"><input id="billing_phone"></p>
        <p id="billing_email_field"><input id="billing_email"></p>
        <p id="billing_city_field"><input id="billing_city" value=""></p>
        <p id="billing_address_1_field" class="theobroma-delivery-address"><input id="billing_address_1"></p>
        <p id="billing_postcode_field" class="theobroma-delivery-address"><input id="billing_postcode"></p>
        <p id="billing_address_2_field" class="theobroma-delivery-address"><input id="billing_address_2"></p>
        </div>
        <table class="woocommerce-checkout-review-order-table">
          <tfoot><tr class="woocommerce-shipping-totals"><th>Доставка</th><td>
            <ul class="woocommerce-shipping-methods"><li><label>Ozon Доставка</label><button class="theobroma-delivery-open">Выбрать пункт или курьера</button></li></ul>
          </td></tr></tfoot>
        </table>
      </div>
      <button type="button" data-delivery-open="ozon">Выбрать доставку</button>
      <dialog class="theobroma-delivery-dialog" data-delivery-dialog aria-labelledby="delivery-title">
        <div class="theobroma-delivery-shell">
          <header class="theobroma-delivery-header">
            <div>
              <p class="theobroma-delivery-eyebrow" data-delivery-provider>Ozon Доставка</p>
              <h2 id="delivery-title">Как доставить заказ?</h2>
            </div>
            <button type="button" class="theobroma-delivery-close" data-delivery-close aria-label="Закрыть"><span aria-hidden="true"></span></button>
          </header>
          <div class="theobroma-delivery-search-control">
            <input type="text" data-delivery-search value="Космонавтов 42А">
            <button type="button" class="theobroma-delivery-search-clear" data-delivery-search-clear aria-label="Очистить поиск"><span aria-hidden="true"></span></button>
          </div>
          <span class="theobroma-delivery-suggestions" data-delivery-suggestions role="listbox" hidden></span>
          <div class="theobroma-delivery-grid">
            <div class="theobroma-delivery-list" data-delivery-list></div>
            <div class="theobroma-delivery-map" data-delivery-map hidden></div>
          </div>
          <section data-delivery-courier hidden>
            <input data-delivery-field="city" value="Старый город">
            <input data-delivery-field="postcode" value="000000">
            <input data-delivery-field="address" value="Старый адрес">
            <input data-delivery-field="address_2" value="Старый комментарий">
          </section>
          <p data-delivery-status></p>
          <footer class="theobroma-delivery-footer"><button type="button" class="button alt" data-delivery-confirm>Рассчитать и выбрать</button></footer>
        </div>
      </dialog>
      <script>
        window.deliveryRequests = [];
        window.theobromaDelivery = { pointsUrl: '/points', suggestionsUrl: '/suggestions', nonce: 'test' };
        window.TheobromaDeliveryCore = { filterPoints: (points) => points, canRenderMap: () => false };
        window.jQuery = function () { return { trigger: function () {}, on: function () {} }; };
        window.fetch = function (url) {
          window.deliveryRequests.push(url);
          var body = url.indexOf('/suggestions') === 0
            ? { configured: true, suggestions: [{
                label: 'проспект Космонавтов, 42А, Казань',
                viewport: {
                  left_bottom: { lat: 55.78, long: 49.19 },
                  right_top: { lat: 55.81, long: 49.22 }
                }
              }] }
            : { points: [] };
          return Promise.resolve({ ok: true, json: function () { return Promise.resolve(body); } });
        };
      </script>
      <script>${script}</script>
    `);

    assert.equal(await page.$eval('#billing_address_1_field', (node) => node.hidden), true, 'street stays hidden until city is entered');
    assert.equal(await page.$eval('#billing_postcode_field', (node) => node.hidden), true, 'postcode stays hidden until city is entered');
    assert.equal(await page.$eval('#billing_address_2_field', (node) => node.hidden), true, 'comment stays hidden until city is entered');
    await page.fill('#billing_city', 'Казань');
    await page.fill('#billing_address_1', 'Спартаковская улица, 1');
    assert.equal(await page.$eval('#billing_address_1_field', (node) => node.hidden), false, 'street appears after city is entered');
    assert.equal(await page.$eval('#billing_postcode_field', (node) => node.hidden), false, 'postcode appears after city is entered');
    assert.equal(await page.$eval('#billing_address_2_field', (node) => node.hidden), false, 'comment appears after city is entered');
    assert.equal(await page.$eval('.theobroma-delivery-methods', (node) => node.parentElement.classList.contains('woocommerce-billing-fields__field-wrapper')), true, 'delivery methods move inside the address form');
    assert.equal(await page.$eval('.woocommerce-checkout-review-order-table', (node) => node.hidden), true, 'empty WooCommerce shipping table is hidden after integration');
    assert.equal(await page.$eval('.theobroma-delivery-methods .theobroma-delivery-open', (node) => getComputedStyle(node).marginTop), '0px', 'integrated delivery action aligns with its provider');
    assert.equal(await page.$eval('.theobroma-delivery-methods label', (node) => getComputedStyle(node).paddingLeft), '0px', 'integrated provider label has no leftover card indentation');

    await page.evaluate(() => document.querySelector('[data-delivery-dialog]').showModal());
    await page.click('[data-delivery-close]');
    assert.equal(await page.$eval('[data-delivery-dialog]', (node) => node.open), false, 'close button must close the dialog');

    await page.evaluate(() => document.querySelector('[data-delivery-dialog]').showModal());
    await page.$eval('[data-delivery-dialog]', (node) => node.dispatchEvent(new MouseEvent('click', { bubbles: true })));
    assert.equal(await page.$eval('[data-delivery-dialog]', (node) => node.open), false, 'backdrop click must close the dialog');

    await page.evaluate(() => document.querySelector('[data-delivery-dialog]').showModal());
    await page.click('[data-delivery-search-clear]');
    assert.equal(await page.inputValue('[data-delivery-search]'), '', 'custom clear button must empty search');
    assert.equal(await page.$eval('[data-delivery-search]', (node) => document.activeElement === node), true, 'search keeps focus after clearing');

    await page.click('[data-delivery-close]');
    await page.evaluate(() => { window.deliveryRequests = []; });
    await page.click('[data-delivery-open="ozon"]');
    assert.equal(await page.inputValue('[data-delivery-search]'), 'Казань, Спартаковская улица, 1', 'pickup search reuses the checkout address');
    await page.waitForFunction(() => window.deliveryRequests.some((url) => url.indexOf('left_bottom_lat=55.78') !== -1));
    const viewportRequest = await page.evaluate(() => window.deliveryRequests.find((url) => url.indexOf('left_bottom_lat=55.78') !== -1));
    assert.ok(viewportRequest, 'checkout address automatically loads Ozon points for its viewport');

    const visual = await page.evaluate(() => {
      const eyebrow = getComputedStyle(document.querySelector('.theobroma-delivery-eyebrow'));
      const action = getComputedStyle(document.querySelector('[data-delivery-confirm]'));
      const clear = getComputedStyle(document.querySelector('[data-delivery-search-clear]'));
      const grid = document.querySelector('.theobroma-delivery-grid');
      const list = document.querySelector('[data-delivery-list]');
      return {
        textTransform: eyebrow.textTransform,
        letterSpacing: eyebrow.letterSpacing,
        actionBackground: action.backgroundColor,
        clearPosition: clear.position,
        listOnlyWidthDifference: Math.abs(grid.getBoundingClientRect().width - list.getBoundingClientRect().width),
      };
    });
    assert.equal(visual.textTransform, 'none');
    assert.ok(visual.letterSpacing === 'normal' || visual.letterSpacing === '0px');
    assert.equal(visual.actionBackground, 'rgb(113, 71, 39)');
    assert.equal(visual.clearPosition, 'absolute');
    assert.ok(visual.listOnlyWidthDifference <= 1, 'pickup list must use the full dialog width when the map is unavailable');
  } finally {
    await browser.close();
  }
  console.log('Delivery dialog interaction verification passed.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
