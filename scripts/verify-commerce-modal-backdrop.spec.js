const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const themeRoot = path.join(root, 'wp-content', 'themes', 'theobroma');
const commerceScript = fs.readFileSync(path.join(themeRoot, 'assets', 'js', 'commerce-modals.js'), 'utf8');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({ viewport: { width: 1932, height: 1080 } });
    await page.setContent(`<!doctype html><html><body>
      <div class="commerce-modal" id="commerce-modal" hidden aria-hidden="true">
        <div class="commerce-modal-backdrop" data-commerce-close></div>
        <section class="commerce-modal-panel" role="dialog" aria-modal="true" aria-label="Ваш заказ">
          <button class="commerce-modal-close" type="button" data-commerce-close aria-label="Закрыть"></button>
          <div class="commerce-modal-status" role="status" hidden></div>
          <div class="commerce-modal-content commerce-modal-cart">
            <form class="checkout woocommerce-checkout theobroma-checkout-anchor" method="post">
              <button type="button">Оформить заказ</button>
            </form>
          </div>
        </section>
      </div>
    </body></html>`);
    await page.addStyleTag({ path: path.join(themeRoot, 'style.css') });
    await page.evaluate(() => {
      window.theobromaCommerce = {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: 'test',
        shopUrl: '/catalog/',
        wishlistIds: [],
        wishlistLoggedIn: false,
      };
    });
    await page.addScriptTag({ content: commerceScript });

    const openCartModal = async () => {
      await page.evaluate(() => {
        const modal = document.querySelector('#commerce-modal');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.dataset.commerceType = 'cart';
        modal.classList.add('is-open');
      });
    };

    await openCartModal();
    await page.locator('.commerce-modal-content').click({ position: { x: 4, y: 4 } });
    assert.equal(await page.locator('#commerce-modal').isVisible(), true, 'clicking the white form surface must keep the cart open');

    const contentBox = await page.locator('.commerce-modal-content').boundingBox();
    assert.ok(contentBox, 'cart content must have a rendered box');
    await page.mouse.click(contentBox.x - 2, contentBox.y + 4);
    assert.equal(await page.locator('#commerce-modal').isVisible(), false, 'clicking free space immediately beside the form must close the cart');

    await openCartModal();
    await page.evaluate(() => {
      document.querySelector('#commerce-modal').dataset.commerceType = 'product';
    });
    await page.mouse.click(contentBox.x - 2, contentBox.y + 4);
    assert.equal(await page.locator('#commerce-modal').isVisible(), true, 'cart dismissal must not change product modal surface behavior');

    console.log('Commerce modal free-space dismissal verified.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
