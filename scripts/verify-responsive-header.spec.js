const { chromium } = require('playwright');
const assert = require('node:assert/strict');

const url = process.env.THEOBROMA_URL || 'http://localhost:8080/';

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of [1200, 1440, 1920, 2560]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await page.goto(url, { waitUntil: 'networkidle' });

      const metrics = await page.evaluate(() => {
        const nav = document.querySelector('.nav');
        const brand = document.querySelector('.brand').getBoundingClientRect();
        const navBox = nav.getBoundingClientRect();
        return {
          position: getComputedStyle(nav).position,
          top: navBox.top,
          height: navBox.height,
          brandCenter: brand.left + brand.width / 2,
          viewportCenter: document.documentElement.clientWidth / 2,
          viewportWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
          studyVisible: getComputedStyle(document.querySelector('.nav-links-study')).display !== 'none',
          transactionalCount: document.querySelectorAll('.nav-links-transactional > a').length,
          wishlistCount: document.querySelectorAll('.header-wishlist, [data-wishlist]').length,
        };
      });

      assert.equal(metrics.position, 'fixed', `${width}px: navigation must be fixed`);
      assert.ok(Math.abs(metrics.top) <= 1, `${width}px: navigation must stay at the top`);
      assert.ok(metrics.height >= 77 && metrics.height <= 79, `${width}px: unexpected header height ${metrics.height}`);
      assert.ok(Math.abs(metrics.brandCenter - metrics.viewportCenter) <= 1, `${width}px: logo is not centered`);
      assert.equal(metrics.studyVisible, true, `${width}px: desktop links must be visible`);
      assert.equal(metrics.transactionalCount, 3, `${width}px: where-to-buy, account and cart are required`);
      assert.equal(metrics.wishlistCount, 0, `${width}px: wishlist must be removed`);
      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${width}px: horizontal overflow detected`);
      await page.close();
    }

    for (const viewport of [{ width: 768, height: 1024 }, { width: 390, height: 844 }]) {
      const page = await browser.newPage({ viewport });
      await page.goto(url, { waitUntil: 'networkidle' });

      const metrics = await page.evaluate(() => ({
        headerHeight: document.querySelector('.nav').getBoundingClientRect().height,
        brandVisible: document.querySelector('.brand').getBoundingClientRect().width > 0,
        cartVisible: document.querySelector('.header-cart').getBoundingClientRect().width > 0,
        menuVisible: document.querySelector('.menu-toggle').getBoundingClientRect().width > 0,
        studyDisplay: getComputedStyle(document.querySelector('.nav-links-study')).display,
        accountDisplay: getComputedStyle(document.querySelector('.header-account')).display,
        viewportWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
      }));

      assert.ok(metrics.headerHeight >= 67 && metrics.headerHeight <= 69, `${viewport.width}px: unexpected mobile header height`);
      assert.equal(metrics.brandVisible, true, `${viewport.width}px: logo is hidden`);
      assert.equal(metrics.cartVisible, true, `${viewport.width}px: cart is hidden`);
      assert.equal(metrics.menuVisible, true, `${viewport.width}px: burger is hidden`);
      assert.equal(metrics.studyDisplay, 'none', `${viewport.width}px: desktop links are visible`);
      assert.equal(metrics.accountDisplay, 'none', `${viewport.width}px: account must move into the menu`);
      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${viewport.width}px: horizontal overflow detected`);

      await page.locator('.menu-toggle').focus();
      await page.keyboard.press('Enter');
      assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'), 'true');
      assert.equal(await page.locator('.mobile-menu').getAttribute('aria-hidden'), 'false');
      const mobileMenu = page.locator('.mobile-menu');
      assert.ok(await mobileMenu.getByRole('link', { name: 'Доставка и оплата' }).isVisible());
      assert.ok(await mobileMenu.getByRole('link', { name: 'Контакты' }).isVisible());
      await page.locator('.mobile-menu a').last().focus();
      await page.keyboard.press('Tab');
      assert.equal(await page.locator('.mobile-menu-close').evaluate((element) => element === document.activeElement), true, 'Mobile menu must trap forward focus');
      await page.keyboard.press('Shift+Tab');
      assert.equal(await page.locator('.mobile-menu a').last().evaluate((element) => element === document.activeElement), true, 'Mobile menu must trap backward focus');
      await page.keyboard.press('Escape');
      assert.equal(await page.locator('.mobile-menu').getAttribute('aria-hidden'), 'true');
      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Responsive header verified without wishlist');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
