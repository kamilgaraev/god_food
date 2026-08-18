const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const commerceScript = path.resolve(__dirname, '../wp-content/themes/theobroma/assets/js/commerce-modals.js');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage();
    await page.setContent(`
      <div class="floating-actions">
        <a class="header-where" href="#where-to-buy">Где купить</a>
        <a class="header-cart" href="#cart" data-commerce-cart-open>
          <span class="cart-count">0</span>
        </a>
      </div>
      <div id="commerce-modal" hidden aria-hidden="true">
        <div class="commerce-modal-panel">
          <button class="commerce-modal-close" type="button">Закрыть</button>
          <div class="commerce-modal-status"></div>
          <div class="commerce-modal-content"><form class="checkout theobroma-checkout-anchor"></form></div>
        </div>
      </div>
    `);
    await page.evaluate(() => {
      window.theobromaCommerce = {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: 'test-nonce',
        cartUrl: '#cart',
        shopUrl: '#catalog',
        wishlistIds: [],
      };
      window.__cartRequests = 0;
      window.fetch = async () => {
        window.__cartRequests += 1;
        return {
          ok: true,
          json: async () => ({ success: true, data: { html: '<p>Корзина</p>', count: 0 } }),
        };
      };
    });
    await page.addScriptTag({ path: commerceScript });

    await page.locator('.header-where').click();

    assert.equal(new URL(page.url()).hash, '#where-to-buy', 'Where-to-buy action must follow its link.');
    assert.equal(await page.locator('#commerce-modal').isHidden(), true, 'Where-to-buy action must not open the cart modal.');
    assert.equal(await page.evaluate(() => window.__cartRequests), 0, 'Where-to-buy action must not request cart content.');
  } finally {
    await browser.close();
  }

  console.log('Header action routing verified.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
