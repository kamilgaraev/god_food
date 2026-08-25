const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeCss = fs.readFileSync(
  path.join(__dirname, '../wp-content/themes/theobroma/style.css'),
  'utf8',
);

async function launchBrowser() {
  const launchOptions = { headless: true };
  if (process.env.PLAYWRIGHT_CHROME_CHANNEL) {
    launchOptions.channel = process.env.PLAYWRIGHT_CHROME_CHANNEL;
    return chromium.launch(launchOptions);
  }

  try {
    return await chromium.launch(launchOptions);
  } catch (error) {
    if (!error.message.includes("Executable doesn't exist")) {
      throw error;
    }

    return chromium.launch({ channel: 'chrome', headless: true });
  }
}

(async () => {
  const browser = await launchBrowser();
  const page = await browser.newPage({ viewport: { width: 588, height: 285 } });

  try {
    await page.setContent(`
      <style>#place_order { float: right; }</style>
      <style>${themeCss}</style>
      <div class="commerce-cart-checkout">
        <div id="payment" class="woocommerce-checkout-payment">
          <div class="form-row place-order">
            <button id="place_order" type="button">Заказать</button>
            <p class="commerce-checkout-afterword">После оформления заказа с вами свяжется наш менеджер.</p>
          </div>
        </div>
      </div>
    `);

    const spacing = await page.evaluate(() => {
      const button = document.querySelector('#place_order').getBoundingClientRect();
      const textNode = document.querySelector('.commerce-checkout-afterword').firstChild;
      const range = document.createRange();
      range.selectNodeContents(textNode);
      const text = range.getBoundingClientRect();

      return {
        gap: text.top - button.bottom,
        buttonFloat: getComputedStyle(document.querySelector('#place_order')).float,
      };
    });

    assert.equal(spacing.buttonFloat, 'none', 'checkout button must stay in the normal document flow');
    assert.ok(spacing.gap >= 20, `checkout afterword gap must be at least 20px, received ${spacing.gap}px`);
  } finally {
    await browser.close();
  }

  console.log('Checkout button spacing verification passed.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
