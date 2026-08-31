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
      <input id="billing_city" value="Казань">
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
          <div data-delivery-list></div>
          <p data-delivery-status></p>
          <footer class="theobroma-delivery-footer"><button type="button" class="button alt" data-delivery-confirm>Рассчитать и выбрать</button></footer>
        </div>
      </dialog>
      <script>
        window.deliveryRequests = [];
        window.theobromaDelivery = { pointsUrl: '/points', suggestionsUrl: '/suggestions', nonce: 'test' };
        window.TheobromaDeliveryCore = { filterPoints: (points) => points, canRenderMap: () => false };
        window.jQuery = function () { return { trigger: function () {} }; };
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
    await page.click('[data-delivery-open="ozon"]');
    await page.fill('[data-delivery-search]', 'Космонавтов 42А');
    await page.waitForSelector('[data-delivery-suggestions] button');
    await page.click('[data-delivery-suggestions] button');
    const viewportRequest = await page.evaluate(() => window.deliveryRequests.find((url) => url.indexOf('left_bottom_lat=55.78') !== -1));
    assert.ok(viewportRequest, 'selected address suggestion must load Ozon points for its viewport');

    const visual = await page.evaluate(() => {
      const eyebrow = getComputedStyle(document.querySelector('.theobroma-delivery-eyebrow'));
      const action = getComputedStyle(document.querySelector('[data-delivery-confirm]'));
      const clear = getComputedStyle(document.querySelector('[data-delivery-search-clear]'));
      return {
        textTransform: eyebrow.textTransform,
        letterSpacing: eyebrow.letterSpacing,
        actionBackground: action.backgroundColor,
        clearPosition: clear.position,
      };
    });
    assert.equal(visual.textTransform, 'none');
    assert.ok(visual.letterSpacing === 'normal' || visual.letterSpacing === '0px');
    assert.equal(visual.actionBackground, 'rgb(113, 71, 39)');
    assert.equal(visual.clearPosition, 'absolute');
  } finally {
    await browser.close();
  }
  console.log('Delivery dialog interaction verification passed.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
