const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright');

const productUrl = process.env.CART_LOCAL_URL || 'http://localhost:8080/product/theobroma-200-68-coriander/';
const themeRoot = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma');
const stylesheet = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8');

async function openCart(page) {
  await page.goto(productUrl, { waitUntil: 'networkidle', timeout: 60_000 });
  await page.locator('#commerce-modal .product-detail-page').waitFor();
  await page.locator('#commerce-modal .single_add_to_cart_button').click();
  await page.locator('#commerce-modal[data-commerce-type="cart"].is-open .commerce-cart-product').waitFor();
  await page.locator('.commerce-cart-checkout input.input-text:visible').first().waitFor();
  await page.locator('#place_order').waitFor();
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({ viewport: { width: 600, height: 1000 } });
    await openCart(page);
    await page.addStyleTag({ content: stylesheet });

    const orderButtonResult = spawnSync(
      'php',
      ['-r', `function add_filter($hook, $callback) { if ($hook === 'woocommerce_order_button_text') { echo call_user_func($callback); } } require ${JSON.stringify(path.join(themeRoot, 'inc', 'checkout-order-button.php'))};`],
      { encoding: 'utf8' },
    );
    assert.equal(orderButtonResult.status, 0, orderButtonResult.stderr || 'Unable to evaluate the checkout button label');
    assert.equal(orderButtonResult.stdout, 'Заказать', 'The registered checkout action must start with a capital letter');

    for (const width of [320, 390, 391, 600, 601, 1199, 1200, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      const metrics = await page.evaluate(() => {
      const rootFontSize = parseFloat(getComputedStyle(document.documentElement).fontSize);
      const fontSizes = {};
      for (const selector of [
        '.commerce-cart-product h3',
        '.commerce-cart-quantity',
        '.commerce-cart-price',
        '.commerce-cart-subtotal',
        '.commerce-cart-auth',
        '.commerce-cart-notes',
        '.commerce-cart-checkout > h3',
        '.commerce-cart-checkout label',
        '.commerce-cart-checkout input.input-text',
        '.commerce-checkout-consent',
        '.commerce-checkout-afterword',
      ]) {
        const element = [...document.querySelectorAll(selector)].find((candidate) => candidate.getClientRects().length > 0);
        if (!element) throw new Error(`Missing cart element: ${selector}`);
        fontSizes[selector] = {
          value: parseFloat(getComputedStyle(element).fontSize),
          element: `${element.tagName.toLowerCase()}#${element.id}.${element.className}`,
        };
      }

      const button = document.querySelector('#place_order');
      const buttonRect = button.getBoundingClientRect();
      const buttonStyle = getComputedStyle(button);
      const title = document.querySelector('.commerce-cart-product h3 a');
      const titleRange = document.createRange();
      titleRange.selectNodeContents(title);
      const titleRect = titleRange.getBoundingClientRect();
      const quantityRect = document.querySelector('.commerce-cart-quantity').getBoundingClientRect();
      const priceRect = document.querySelector('.commerce-cart-price').getBoundingClientRect();
      const cart = document.querySelector('.commerce-modal-cart');
      const overlaps = (first, second) => first.left < second.right
        && first.right > second.left
        && first.top < second.bottom
        && first.bottom > second.top;

      return {
        rootFontSize,
        fontSizes,
        layout: {
          titleOverlapsControls: overlaps(titleRect, quantityRect) || overlaps(titleRect, priceRect),
          overflow: cart.scrollWidth - cart.clientWidth,
        },
        button: {
          fontSize: parseFloat(buttonStyle.fontSize),
          height: buttonRect.height,
          display: buttonStyle.display,
          alignItems: buttonStyle.alignItems,
          justifyContent: buttonStyle.justifyContent,
          paddingTop: parseFloat(buttonStyle.paddingTop),
          paddingBottom: parseFloat(buttonStyle.paddingBottom),
        },
      };
      });

      for (const [selector, fontSize] of Object.entries(metrics.fontSizes)) {
        assert.ok(fontSize.value >= metrics.rootFontSize, `${width}px: ${selector} (${fontSize.element}) renders at ${fontSize.value}px below the ${metrics.rootFontSize}px cart body scale`);
      }
      assert.ok(metrics.button.fontSize >= metrics.rootFontSize, `${width}px: the checkout action must use the cart body scale`);
      assert.ok(Math.abs(metrics.button.height - metrics.rootFontSize * 3.75) <= 0.5, `${width}px: the checkout action must have an exact 3.75rem height`);
      assert.equal(metrics.button.display, 'flex', `${width}px: the checkout action must use flex centering`);
      assert.equal(metrics.button.alignItems, 'center', `${width}px: the checkout action text must be vertically centered`);
      assert.equal(metrics.button.justifyContent, 'center', `${width}px: the checkout action text must be horizontally centered`);
      assert.equal(metrics.button.paddingTop, 0, `${width}px: the checkout action must not inherit asymmetric vertical padding`);
      assert.equal(metrics.button.paddingBottom, 0, `${width}px: the checkout action must not inherit asymmetric vertical padding`);
      assert.equal(metrics.layout.titleOverlapsControls, false, `${width}px: the product title must not overlap quantity or price`);
      assert.ok(metrics.layout.overflow <= 1, `${width}px: the cart must not overflow horizontally (${metrics.layout.overflow}px)`);
    }

    console.log('Cart typography and checkout action contract verified across responsive breakpoints.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
