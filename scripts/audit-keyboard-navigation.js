const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';

async function visibleFocusables(page, root) {
  return page.locator(root).locator('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])').evaluateAll((elements) => elements.filter((element) => element.getClientRects().length > 0).map((element) => ({
    tag: element.tagName,
    className: element.className,
    label: element.getAttribute('aria-label') || element.textContent.trim(),
  })));
}

async function assertFocusTrap(page, root, label) {
  const focusables = page.locator(root).locator('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])').filter({ visible: true });
  const count = await focusables.count();
  assert.ok(count > 0, `${label}: no visible focusable controls`);
  await focusables.first().focus();
  await page.keyboard.press('Shift+Tab');
  assert.equal(await focusables.last().evaluate((element) => element === document.activeElement), true, `${label}: Shift+Tab must wrap to the last control`);
  await page.keyboard.press('Tab');
  assert.equal(await focusables.first().evaluate((element) => element === document.activeElement), true, `${label}: Tab must wrap to the first control`);
}

async function assertVisibleFocus(page, label) {
  const state = await page.evaluate(() => ({
    tag: document.activeElement?.tagName,
    className: document.activeElement?.className,
    visible: Boolean(document.activeElement?.getClientRects().length),
  }));
  assert.equal(state.visible, true, `${label}: focus is hidden ${JSON.stringify(state)}`);
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 1440]) {
      const context = await browser.newContext({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
      await context.addInitScript(() => localStorage.setItem('theobroma_cookie_notice_seen', '1'));
      const page = await context.newPage();
      await page.goto(baseUrl, { waitUntil: 'networkidle' });

      await page.keyboard.press('Tab');
      assert.equal(await page.locator('.skip-link').evaluate((element) => element === document.activeElement), true, `${width}px skip link must be first in keyboard order`);
      await page.keyboard.press('Enter');
      assert.equal(await page.locator('#theobroma-main').evaluate((element) => element === document.activeElement), true, `${width}px skip link must focus its target`);

      if (width === 390) {
        await page.locator('.menu-toggle').focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'), 'true');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('.menu-toggle').evaluate((element) => element === document.activeElement), true, 'Mobile menu must restore focus');
      }

      const account = page.locator('[data-account-trigger]');
      await account.focus();
      await page.keyboard.press('Enter');
      await page.locator('#account-modal:not([hidden])').waitFor();
      await assertVisibleFocus(page, `${width}px account modal`);
      await page.keyboard.press('Escape');
      await page.waitForTimeout(250);
      assert.equal(await account.evaluate((element) => element === document.activeElement), true, `${width}px account modal must restore focus`);

      const wishlist = page.locator('[data-wishlist-open]');
      await wishlist.focus();
      await page.keyboard.press('Enter');
      await page.locator('#commerce-modal[data-commerce-type="wishlist"].is-open .commerce-wishlist-empty').waitFor();
      await assertVisibleFocus(page, `${width}px empty wishlist`);
      await assertFocusTrap(page, '#commerce-modal .commerce-modal-panel', `${width}px empty wishlist`);
      await page.keyboard.press('Escape');
      assert.equal(await wishlist.evaluate((element) => element === document.activeElement), true, `${width}px wishlist must restore focus`);

      const cart = page.locator('.floating-actions a[aria-label="Корзина"]');
      await cart.focus();
      await page.keyboard.press('Enter');
      await page.locator('#commerce-modal[data-commerce-type="cart"].is-open .commerce-cart--empty').waitFor();
      await assertVisibleFocus(page, `${width}px empty cart`);
      await assertFocusTrap(page, '#commerce-modal .commerce-modal-panel', `${width}px empty cart`);
      await page.keyboard.press('Escape');
      assert.equal(await cart.evaluate((element) => element === document.activeElement), true, `${width}px cart must restore focus`);

      const product = page.locator('[data-product-modal-link],ul.products li.product a.woocommerce-LoopProduct-link,.product > a,.product-related a[href*="/product/"]').first();
      const productMarkup = await product.evaluate((element) => element.outerHTML.slice(0, 300));
      await product.focus();
      await page.keyboard.press('Enter');
      await page.locator('#commerce-modal[data-commerce-type="product"].is-open .product-detail-page').waitFor();
      const productModalUrl = page.url();
      await assertVisibleFocus(page, `${width}px product modal`);
      const initialProductFocus = await page.locator('#commerce-modal .commerce-modal-panel').evaluate((panel) => {
        const active = document.activeElement;
        const style = getComputedStyle(active);
        return { inside: panel.contains(active), active: active?.outerHTML?.slice(0, 180), outlineStyle: style.outlineStyle, outlineWidth: style.outlineWidth };
      });
      assert.equal(initialProductFocus.inside, true, `${width}px product focus must start inside the dialog: ${JSON.stringify(initialProductFocus)}`);
      assert.notEqual(initialProductFocus.outlineStyle, 'none', `${width}px initial product control must have a visible focus indicator: ${JSON.stringify(initialProductFocus)}`);
      assert.notEqual(initialProductFocus.outlineWidth, '0px', `${width}px initial product control must have a visible focus indicator: ${JSON.stringify(initialProductFocus)}`);
      await assertFocusTrap(page, '#commerce-modal .commerce-modal-panel', `${width}px product modal`);
      await page.keyboard.press('Escape');
      await page.locator('#commerce-modal').waitFor({ state: 'hidden' });
      await page.waitForTimeout(250);
      const restoredProductFocus = await product.evaluate((element) => ({
        restored: element === document.activeElement,
        active: document.activeElement?.outerHTML?.slice(0, 240),
      }));
      assert.equal(restoredProductFocus.restored, true, `${width}px product modal must restore focus: ${JSON.stringify({ ...restoredProductFocus, productMarkup, productModalUrl, closedUrl: page.url() })}`);

      console.log(`${width}px keyboard navigation passed; modal focusables ${JSON.stringify(await visibleFocusables(page, 'body'))}`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
