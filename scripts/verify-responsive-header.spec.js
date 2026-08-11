const { chromium } = require('playwright');
const assert = require('node:assert/strict');

const url = process.env.THEOBROMA_URL || 'http://localhost:8080/';

async function routeLocalAssets(page) {
  const local = new URL(url);
  if (local.port === '8080') return;
  await page.route('http://localhost:8080/**', async (route) => {
    const target = new URL(route.request().url());
    target.protocol = local.protocol;
    target.hostname = local.hostname;
    target.port = local.port;
    await route.continue({ url: target.href });
  });
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of [1101, 1200, 1440, 2295]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await routeLocalAssets(page);
      await page.goto(url, { waitUntil: 'networkidle' });

      const metrics = await page.evaluate(() => {
        const nav = document.querySelector('.nav');
        const brand = document.querySelector('.brand').getBoundingClientRect();
        const navBox = nav.getBoundingClientRect();
        const study = document.querySelector('.nav-links-study').getBoundingClientRect();
        const transactional = document.querySelector('.nav-links-transactional').getBoundingClientRect();
        const headerCenter = navBox.top + navBox.height / 2;
        const controlCenters = Array.from(nav.querySelectorAll('.nav-links a, .brand'), (element) => {
          const rect = element.getBoundingClientRect();
          return rect.top + rect.height / 2;
        });
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
          studyRight: study.right,
          transactionalLeft: transactional.left,
          brandLeft: brand.left,
          brandRight: brand.right,
          maxControlAxisDrift: Math.max(...controlCenters.map((center) => Math.abs(center - headerCenter))),
          rootScale: parseFloat(getComputedStyle(document.documentElement).fontSize) / 16,
        };
      });

      assert.equal(metrics.position, 'fixed', `${width}px: navigation must be fixed`);
      assert.ok(Math.abs(metrics.top) <= 1, `${width}px: navigation must stay at the top`);
      assert.ok(Math.abs(metrics.height - 78 * metrics.rootScale) <= 1.5, `${width}px: unexpected header height ${metrics.height}`);
      assert.ok(Math.abs(metrics.brandCenter - metrics.viewportCenter) <= 1, `${width}px: logo is not centered`);
      assert.equal(metrics.studyVisible, true, `${width}px: desktop links must be visible`);
      assert.equal(metrics.transactionalCount, 3, `${width}px: where-to-buy, account and cart are required`);
      assert.equal(metrics.wishlistCount, 0, `${width}px: wishlist must be removed`);
      assert.ok(metrics.studyRight <= metrics.brandLeft - 16, `${width}px: left navigation overlaps the logo`);
      assert.ok(metrics.transactionalLeft >= metrics.brandRight + 16, `${width}px: right actions overlap the logo`);
      assert.ok(metrics.maxControlAxisDrift <= 2, `${width}px: header controls do not share one vertical axis`);
      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${width}px: horizontal overflow detected`);
      await page.close();
    }

    for (const width of [801, 850, 900, 950, 1000, 1050, 1100]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await routeLocalAssets(page);
      await page.goto(url, { waitUntil: 'networkidle' });

      const metrics = await page.evaluate(() => {
        const nav = document.querySelector('.nav').getBoundingClientRect();
        const rect = (selector) => document.querySelector(selector).getBoundingClientRect();
        const display = (selector) => getComputedStyle(document.querySelector(selector)).display;
        const brand = rect('.brand');
        const account = rect('.header-account');
        const accountIcon = rect('.header-account img');
        const cart = rect('.header-cart');
        const menu = rect('.menu-toggle');
        const heroActions = rect('.home-hero__actions');
        const heroTrust = rect('.home-hero__trust');
        const centers = [brand, account, cart, menu].map((box) => box.top + box.height / 2);

        return {
          studyDisplay: display('.nav-links-study'),
          whereDisplay: display('.header-where'),
          accountIconCenterDrift: Math.max(
            Math.abs((accountIcon.left + accountIcon.width / 2) - (account.left + account.width / 2)),
            Math.abs((accountIcon.top + accountIcon.height / 2) - (account.top + account.height / 2)),
          ),
          accountVisible: account.width > 0,
          cartVisible: cart.width > 0,
          menuVisible: menu.width > 0,
          maxAxisDrift: Math.max(...centers.map((center) => Math.abs(center - (nav.top + nav.height / 2)))),
          controlsRight: Math.max(account.right, cart.right, menu.right),
          heroActionTrustGap: heroTrust.left - heroActions.right,
          viewportWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
        };
      });

      assert.equal(metrics.studyDisplay, 'none', `${width}px: dense desktop navigation must collapse at the tablet breakpoint`);
      assert.notEqual(metrics.whereDisplay, 'none', `${width}px: where-to-buy must remain visible in the tablet header`);
      assert.equal(metrics.accountVisible, true, `${width}px: account icon must remain visible in the tablet header`);
      assert.ok(metrics.accountIconCenterDrift <= 1, `${width}px: account glyph is not centered inside its circular control`);
      assert.equal(metrics.cartVisible, true, `${width}px: cart must remain visible in the tablet header`);
      assert.equal(metrics.menuVisible, true, `${width}px: tablet menu trigger is missing`);
      assert.ok(metrics.maxAxisDrift <= 2, `${width}px: tablet header controls do not share one vertical axis`);
      assert.ok(metrics.controlsRight <= metrics.viewportWidth, `${width}px: tablet actions are clipped by the viewport`);
      assert.ok(metrics.heroActionTrustGap >= 10, `${width}px: hero actions collide with the trust metrics`);
      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${width}px: tablet header creates horizontal overflow`);
      await page.close();
    }

    for (const viewport of [{ width: 768, height: 1024 }, { width: 440, height: 956 }, { width: 390, height: 844 }, { width: 320, height: 720 }]) {
      const page = await browser.newPage({ viewport });
      await routeLocalAssets(page);
      await page.goto(url, { waitUntil: 'networkidle' });

      const metrics = await page.evaluate(() => {
        const nav = document.querySelector('.nav').getBoundingClientRect();
        const rect = (selector) => document.querySelector(selector).getBoundingClientRect();
        const brand = rect('.brand');
        const account = rect('.header-account');
        const cart = rect('.header-cart');
        const menu = rect('.menu-toggle');
        const centers = [brand, account, cart, menu].map((box) => box.top + box.height / 2);
        return {
        headerHeight: nav.height,
        brandVisible: document.querySelector('.brand').getBoundingClientRect().width > 0,
        cartVisible: document.querySelector('.header-cart').getBoundingClientRect().width > 0,
        menuVisible: document.querySelector('.menu-toggle').getBoundingClientRect().width > 0,
        studyDisplay: getComputedStyle(document.querySelector('.nav-links-study')).display,
        accountDisplay: getComputedStyle(document.querySelector('.header-account')).display,
        viewportWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        cartHeight: cart.height,
        rootScale: parseFloat(getComputedStyle(document.documentElement).fontSize) / 16,
        maxAxisDrift: Math.max(...centers.map((center) => Math.abs(center - (nav.top + nav.height / 2)))),
      }});

      assert.ok(Math.abs(metrics.headerHeight - 68 * metrics.rootScale) <= 1.5, `${viewport.width}px: unexpected mobile header height`);
      assert.equal(metrics.brandVisible, true, `${viewport.width}px: logo is hidden`);
      assert.equal(metrics.cartVisible, true, `${viewport.width}px: cart is hidden`);
      assert.equal(metrics.menuVisible, true, `${viewport.width}px: burger is hidden`);
      assert.equal(metrics.studyDisplay, 'none', `${viewport.width}px: desktop links are visible`);
      assert.notEqual(metrics.accountDisplay, 'none', `${viewport.width}px: account icon must be visible in the top header`);
      assert.ok(Math.abs(metrics.cartHeight - 38 * metrics.rootScale) <= 1.5, `${viewport.width}px: cart height is not stable`);
      assert.ok(metrics.maxAxisDrift <= 2, `${viewport.width}px: mobile controls do not share one vertical axis`);
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
