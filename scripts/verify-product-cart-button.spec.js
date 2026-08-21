const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

const commerceScript = path.resolve(__dirname, '../wp-content/themes/theobroma/assets/js/commerce-modals.js');
const productCardStyles = path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage();
    await page.setContent(`
      <div id="commerce-modal" hidden aria-hidden="true">
        <div class="commerce-modal-panel">
          <button class="commerce-modal-close" type="button">Закрыть</button>
          <div class="commerce-modal-status"></div>
          <div class="commerce-modal-content"><form class="checkout theobroma-checkout-anchor"></form></div>
        </div>
      </div>
      <ul class="products">
        <li class="product home-product-card">
          <div class="home-product-card__purchase">
            <span class="home-product-card__price">772₽</span>
            <a
              class="home-product-card__button product_type_simple add_to_cart_button ajax_add_to_cart"
              href="/?add-to-cart=42"
              data-product_id="42"
            >В корзину</a>
          </div>
        </li>
      </ul>
    `);
    await page.addStyleTag({ path: productCardStyles });

    await page.evaluate(() => {
      const handlers = new Map();
      const requests = [];

      window.theobromaCommerce = {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        wcAjaxUrl: '/?wc-ajax=%%endpoint%%',
        nonce: 'test-nonce',
        cartUrl: '/cart/',
        checkoutUrl: '/checkout/',
        shopUrl: '/catalog/',
        wishlistIds: [],
      };
      window.__commerceRequests = requests;
      window.jQuery = (target) => ({
        jquery: 'test-double',
        get: (index) => (index === 0 ? target : undefined),
        on: (eventName, callback) => {
          const event = eventName.split('.')[0];
          handlers.set(event, [...(handlers.get(event) || []), callback]);
        },
        trigger: (eventName, args = []) => {
          const event = eventName.split('.')[0];
          for (const callback of handlers.get(event) || []) callback({ type: event }, ...args);
        },
      });
      window.fetch = async (input, options = {}) => {
        const url = String(input);
        requests.push({ url, body: String(options.body || '') });
        if (url.includes('/product/')) {
          return { ok: true, text: async () => '<main class="product-detail-page"></main>' };
        }
        return {
          ok: true,
          json: async () => ({ success: true, data: { html: '<p class="commerce-cart-product">Товар</p>', count: 1 } }),
        };
      };
    });

    await page.addScriptTag({ path: commerceScript });
    await page.evaluate(() => {
      document.addEventListener('click', (event) => {
        const button = event.target.closest('.add_to_cart_button');
        if (!button) return;
        event.preventDefault();
        button.insertAdjacentHTML('afterend', '<a href="/cart/" class="added_to_cart wc-forward">Просмотр корзины</a>');
        window.jQuery(document.body).trigger('added_to_cart', [{}, 'cart-hash', window.jQuery(button)]);
      });
    });

    await page.locator('.home-product-card__button').click();
    await page.locator('#commerce-modal[data-commerce-type="cart"].is-open').waitFor();
    await page.locator('.commerce-cart-product').waitFor();
    assert.equal(
      await page.locator('.home-product-card__purchase > .added_to_cart.wc-forward').isVisible(),
      false,
      'WooCommerce view-cart link must stay hidden when the cart opens automatically.',
    );

    const requests = await page.evaluate(() => window.__commerceRequests);
    assert.equal(requests.some(({ url }) => url.includes('/product/')), false, 'Add-to-cart click must not open the product modal.');
    assert.equal(
      requests.some(({ body }) => body.includes('action=theobroma_cart_modal')),
      true,
      'Cart modal must open after the product is added.',
    );
  } finally {
    await browser.close();
  }

  console.log('Product add-to-cart routing verified.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
