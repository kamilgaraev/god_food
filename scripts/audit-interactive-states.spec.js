const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const config = require('./pairwise-audit.config.json');
const widths = ['390', '430', '768', '1440', '1920', '2048', '2560', '3840'];
const outputDir = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'interactive-states');

const assertNoViewportOverflow = async (page, label) => {
  const metrics = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    content: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth),
  }));
  assert.ok(metrics.content - metrics.viewport <= 1, `${label}: horizontal overflow ${metrics.content - metrics.viewport}px`);
};

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const widthKey of widths) {
      const width = Number(widthKey);
      const context = await browser.newContext({ viewport: config.viewports[widthKey], reducedMotion: 'reduce' });
      const page = await context.newPage();
      const errors = [];
      page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
      page.on('pageerror', (error) => errors.push(error.message));
      await page.goto('http://localhost:8080/catalog/', { waitUntil: 'networkidle' });

      if (width <= 430) {
        await page.goto('http://localhost:8080/', { waitUntil: 'networkidle' });
        const heroMetrics = await page.locator('.hero').evaluate((hero) => {
          const rect = hero.getBoundingClientRect();
          const chocolate = getComputedStyle(hero, '::before');
          return {
            height: rect.height,
            chocolateWidth: parseFloat(chocolate.width),
            chocolateHeight: parseFloat(chocolate.height),
          };
        });
        const scale = width / 390;
        assert.ok(Math.abs(heroMetrics.height - 670.4375 * scale) <= 0.2, `${widthKey}px: hero is ${heroMetrics.height}px tall`);
        assert.ok(Math.abs(heroMetrics.chocolateWidth - 260 * scale) <= 0.2, `${widthKey}px: hero chocolate is ${heroMetrics.chocolateWidth}px wide`);
        assert.ok(Math.abs(heroMetrics.chocolateHeight - 300 * scale) <= 0.2, `${widthKey}px: hero chocolate is ${heroMetrics.chocolateHeight}px tall`);
        await page.goto('http://localhost:8080/catalog/', { waitUntil: 'networkidle' });
      }

      const cookie = page.locator('.cookie-notice');
      await cookie.waitFor({ state: 'visible' });
      await page.screenshot({ path: path.join(outputDir, `cookie-${widthKey}.png`), fullPage: false, animations: 'disabled' });
      await cookie.locator('button').click();
      await cookie.waitFor({ state: 'hidden' });
      await page.reload({ waitUntil: 'networkidle' });
      assert.equal(await cookie.isHidden(), true, `${widthKey}px: cookie consent was not persisted`);

      if (width <= 900) {
        await page.locator('.menu-toggle').click();
        assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'), 'true');
        assert.equal(await page.locator('.mobile-menu').getAttribute('aria-hidden'), 'false');
        const panelWidth = await page.locator('.mobile-menu').evaluate((menu) => parseFloat(getComputedStyle(menu, '::before').width));
        assert.ok(Math.abs(panelWidth - Math.min(width * 0.82051, 320)) <= 0.2, `${widthKey}px: mobile menu panel is ${panelWidth}px`);
        if (width >= 461) {
          const menuMetrics = await page.locator('.mobile-menu').evaluate((menu) => {
            const panel = getComputedStyle(menu, '::before');
            const nav = getComputedStyle(menu.querySelector('nav'));
            return {
              panelWidth: parseFloat(panel.width),
              navLeft: parseFloat(nav.left),
              navWidth: parseFloat(nav.width),
              fontSize: parseFloat(getComputedStyle(menu.querySelector('a')).fontSize),
            };
          });
          assert.ok(Math.abs(menuMetrics.panelWidth - 320) <= 0.1, `${widthKey}px: tablet menu panel is ${menuMetrics.panelWidth}px`);
          assert.ok(Math.abs(menuMetrics.navLeft - 40) <= 0.1, `${widthKey}px: tablet menu content starts at ${menuMetrics.navLeft}px`);
          assert.ok(Math.abs(menuMetrics.navWidth - 240) <= 0.1, `${widthKey}px: tablet menu content is ${menuMetrics.navWidth}px wide`);
          assert.ok(Math.abs(menuMetrics.fontSize - 26) <= 0.1, `${widthKey}px: tablet menu font is ${menuMetrics.fontSize}px`);
        }
        await page.screenshot({ path: path.join(outputDir, `menu-${widthKey}.png`), fullPage: false, animations: 'disabled' });
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('.mobile-menu').getAttribute('aria-hidden'), 'true');
      }

      await page.locator('[data-account-trigger]').click();
      await page.locator('#account-modal.is-open').waitFor();
      const accountLayout = await page.locator('.account-modal-panel').evaluate((panel) => {
        const rect = panel.getBoundingClientRect();
        const headingRange = document.createRange();
        headingRange.selectNodeContents(panel.querySelector('h2'));
        const heading = headingRange.getBoundingClientRect();
        const input = panel.querySelector('#account-email').getBoundingClientRect();
        return {
          x: rect.x,
          width: rect.width,
          headingCenterDelta: Math.abs((heading.x + heading.width / 2) - (rect.x + rect.width / 2)),
          inputWidth: input.width,
          backdropDisplay: getComputedStyle(document.querySelector('.account-modal-backdrop')).display,
        };
      });
      const expectedAccountWidth = width <= 600 ? width : width / 2;
      assert.ok(Math.abs(accountLayout.width - expectedAccountWidth) <= 0.1, `${widthKey}px: account panel width is ${accountLayout.width}px`);
      assert.ok(Math.abs(accountLayout.x - (width - expectedAccountWidth)) <= 0.1, `${widthKey}px: account panel x is ${accountLayout.x}px`);
      assert.ok(accountLayout.headingCenterDelta <= 0.1, `${widthKey}px: account heading is off-center by ${accountLayout.headingCenterDelta}px`);
      assert.ok(Math.abs(accountLayout.inputWidth - (width >= 601 && width <= 900 ? 335 : 340)) <= 0.1, `${widthKey}px: account input is ${accountLayout.inputWidth}px wide`);
      assert.equal(accountLayout.backdropDisplay, width <= 600 ? 'none' : 'block', `${widthKey}px: account backdrop visibility differs`);
      await page.locator('#account-email').fill('audit@example.com');
      await page.locator('[data-account-continue]').click();
      assert.equal(await page.locator('[data-account-login]').isVisible(), true, `${widthKey}px: login step is hidden`);
      await page.locator('[data-account-show-register]').click();
      assert.equal(await page.locator('[data-account-register]').isVisible(), true, `${widthKey}px: register step is hidden`);
      await page.screenshot({ path: path.join(outputDir, `account-register-${widthKey}.png`), fullPage: false, animations: 'disabled' });
      await page.keyboard.press('Escape');
      await page.locator('#account-modal').waitFor({ state: 'hidden' });

      await page.locator('.floating-actions a:first-child').click();
      await page.locator('#commerce-modal[data-commerce-type="cart"].is-open').waitFor();
      await page.locator('#commerce-modal .commerce-cart').waitFor();
      assert.equal(await page.locator('.commerce-cart-empty').count(), 1, `${widthKey}px: empty cart state is missing`);
      assert.match(await page.locator('.commerce-cart-empty').innerText(), /ПОЖАЛУЙСТА, ДОБАВЬТЕ ТОВАРЫ В КОРЗИНУ/i);
      const emptyCartBox = await page.locator('.commerce-modal-cart').boundingBox();
      assert.ok(Math.abs(emptyCartBox.width - (width <= 600 ? 350 : 560)) <= 1, `${widthKey}px: empty cart width ${emptyCartBox.width}px`);
      assert.ok(emptyCartBox.height <= 160, `${widthKey}px: empty cart is not a compact alert (${emptyCartBox.height}px)`);
      assert.ok(Math.abs((emptyCartBox.y + emptyCartBox.height / 2) - config.viewports[widthKey].height / 2) <= 2, `${widthKey}px: empty cart is not vertically centered`);
      const emptyCartStyles = await page.locator('.commerce-cart-empty').evaluate((empty) => {
        const text = getComputedStyle(empty.querySelector('p'));
        const close = empty.querySelector('.commerce-cart-empty-close').getBoundingClientRect();
        const box = empty.getBoundingClientRect();
        return {
          fontFamily: text.fontFamily,
          fontSize: parseFloat(text.fontSize),
          closeTop: close.top - box.top,
          closeRight: box.right - close.right,
          overlay: getComputedStyle(document.querySelector('#commerce-modal')).backgroundColor,
        };
      });
      assert.match(emptyCartStyles.fontFamily, /Cormorant/i, `${widthKey}px: empty cart uses the wrong font`);
      assert.ok(Math.abs(emptyCartStyles.fontSize - (width <= 600 ? 19 : 25)) <= 0.1, `${widthKey}px: empty cart font is ${emptyCartStyles.fontSize}px`);
      assert.ok(Math.abs(emptyCartStyles.closeTop - 10) <= 0.1, `${widthKey}px: empty cart close top is ${emptyCartStyles.closeTop}px`);
      assert.ok(Math.abs(emptyCartStyles.closeRight - 10) <= 0.1, `${widthKey}px: empty cart close right is ${emptyCartStyles.closeRight}px`);
      assert.equal(emptyCartStyles.overlay, 'rgba(52, 52, 52, 0.8)', `${widthKey}px: empty cart overlay differs from source`);
      await page.screenshot({ path: path.join(outputDir, `cart-empty-${widthKey}.png`), fullPage: false, animations: 'disabled' });
      await page.locator('#commerce-modal .commerce-cart-empty-close').click();
      await page.locator('#commerce-modal').waitFor({ state: 'hidden' });

      await page.locator('ul.products li.product a.woocommerce-LoopProduct-link').first().click();
      await page.locator('#commerce-modal[data-commerce-type="product"].is-open .product-detail-page').waitFor();
      await page.screenshot({ path: path.join(outputDir, `product-${widthKey}.png`), fullPage: false, animations: 'disabled' });

      await page.locator('[data-product-image-zoom]').click();
      await page.locator('.product-image-lightbox:not([hidden])').waitFor();
      assert.equal(await page.locator('.product-image-lightbox').getAttribute('aria-hidden'), 'false');
      await page.keyboard.press('Escape');
      await page.locator('.product-image-lightbox').waitFor({ state: 'hidden' });

      await page.locator('#commerce-modal .single_add_to_cart_button').click();
      await page.locator('#commerce-modal[data-commerce-type="cart"].is-open .commerce-cart-product').waitFor();
      await page.screenshot({ path: path.join(outputDir, `cart-${widthKey}.png`), fullPage: false, animations: 'disabled' });
      const plus = page.locator('.commerce-cart-product [data-cart-quantity="2"]');
      await plus.click();
      await page.locator('.commerce-cart-quantity span').filter({ hasText: '2' }).waitFor();
      await page.locator('.commerce-cart-clear').click();
      await page.locator('.commerce-cart-empty').waitFor();

      await assertNoViewportOverflow(page, `${widthKey}px`);
      assert.deepEqual(errors, [], `${widthKey}px browser errors`);
      await context.close();
      console.log(`${widthKey}px interactive states passed`);
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
