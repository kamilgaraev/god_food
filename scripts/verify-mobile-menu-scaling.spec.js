const { readFile } = require('node:fs/promises');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const scriptPath = path.join(root, 'wp-content/themes/theobroma/assets/js/site-header.js');
const viewportCases = [
  { width: 744, height: 1133, panelWidth: 654.72, linkScale: 2 },
  { width: 320, panelWidth: 281.6, linkScale: 1.5 },
  { width: 390, panelWidth: 343.2, linkScale: 1.5 },
  { width: 600, panelWidth: 528, linkScale: 1.5 },
  { width: 601, panelWidth: 528.88, linkScale: 2 },
  { width: 1199, panelWidth: 1055.12, linkScale: 2 },
];

const markup = `
  <button class="menu-toggle" type="button"><span></span><span></span><span></span></button>
  <div class="mobile-menu" aria-hidden="false">
    <button class="mobile-menu-close" type="button" aria-label="Закрыть меню"></button>
    <nav aria-label="Мобильная навигация">
      <p class="mobile-menu-label">О продукте</p>
      <ul>
        <li><a href="#catalog">Каталог</a></li>
        <li><a href="#recipes">Рецепты</a></li>
      </ul>
      <p class="mobile-menu-label">Покупателям</p>
      <ul>
        <li><a href="#delivery">Доставка и оплата</a></li>
        <li><a href="#contacts">Контакты</a></li>
      </ul>
    </nav>
  </div>
  <div class="cookie-notice"><p>Мы используем cookie</p><button type="button">Принять</button></div>
`;

(async () => {
  const [themeCss, redesignCss] = await Promise.all([
    readFile(path.join(root, 'wp-content/themes/theobroma/style.css'), 'utf8'),
    readFile(path.join(root, 'wp-content/themes/theobroma/assets/css/home-redesign.css'), 'utf8'),
  ]);
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const expected of viewportCases) {
      const page = await browser.newPage({ viewport: { width: expected.width, height: expected.height || 900 } });
      await page.setContent(`<body class="mobile-menu-open">${markup}<main style="height:3000px"></main></body>`);
      await page.locator('html').evaluate((element) => element.classList.add('mobile-menu-open'));
      await page.addStyleTag({ content: themeCss });
      await page.addStyleTag({ content: redesignCss });

      const metrics = await page.evaluate(() => {
        const menu = document.querySelector('.mobile-menu');
        const nav = document.querySelector('.mobile-menu nav').getBoundingClientRect();
        const close = document.querySelector('.mobile-menu-close').getBoundingClientRect();
        const labelStyle = getComputedStyle(document.querySelector('.mobile-menu-label'));
        const linkStyle = getComputedStyle(document.querySelector('.mobile-menu a'));

        return {
          panelWidth: parseFloat(getComputedStyle(menu, '::before').width),
          navLeft: nav.left,
          navRight: nav.right,
          closeRight: close.right,
          labelTransform: labelStyle.textTransform,
          labelLetterSpacing: parseFloat(labelStyle.letterSpacing),
          linkTransform: linkStyle.textTransform,
          linkSize: parseFloat(linkStyle.fontSize),
          rootSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
          menuOwnsBottomLayer: document.elementFromPoint(
            document.documentElement.clientWidth - 8,
            window.innerHeight - 8,
          ).closest('.mobile-menu') !== null,
          viewportWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
        };
      });

      assert.ok(
        Math.abs(metrics.panelWidth - expected.panelWidth) <= 1,
        `${expected.width}px: drawer must be 88vw (${expected.panelWidth}px), got ${metrics.panelWidth}px`,
      );
      assert.ok(
        Math.abs(metrics.navLeft - (metrics.panelWidth - metrics.navRight)) <= 1,
        `${expected.width}px: drawer navigation must have symmetrical inline insets`,
      );
      assert.ok(
        metrics.closeRight <= metrics.panelWidth && metrics.panelWidth - metrics.closeRight <= metrics.rootSize,
        `${expected.width}px: close control must stay aligned to the drawer edge`,
      );
      assert.equal(metrics.labelTransform, 'uppercase', `${expected.width}px: section labels must stay uppercase`);
      assert.ok(metrics.labelLetterSpacing >= 1.5, `${expected.width}px: section label tracking is missing`);
      assert.equal(metrics.linkTransform, 'none', `${expected.width}px: drawer links must remain title case`);
      assert.ok(
        Math.abs(metrics.linkSize - metrics.rootSize * expected.linkScale) <= 1,
        `${expected.width}px: drawer links must render at ${expected.linkScale}rem`,
      );
      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${expected.width}px: drawer creates horizontal overflow`);
      assert.equal(
        await page.locator('html').evaluate((element) => getComputedStyle(element).overflowY),
        'hidden',
        `${expected.width}px: the root scroller must be locked while the drawer is open`,
      );
      if (expected.width === 744) {
        assert.equal(metrics.menuOwnsBottomLayer, true, '744px: cookie notice must not cover an open drawer');
      }
      await page.close();
    }

    const desktop = await browser.newPage({ viewport: { width: 1200, height: 900 } });
    await desktop.setContent(`<body class="mobile-menu-open">${markup}</body>`);
    await desktop.addStyleTag({ content: themeCss });
    await desktop.addStyleTag({ content: redesignCss });
    assert.equal(await desktop.locator('.mobile-menu').evaluate((element) => getComputedStyle(element).display), 'none');
    await desktop.close();

    const scrollLockPage = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await scrollLockPage.setContent(`<body><header class="site-header"><a class="shipping"></a></header>${markup}<main style="height:3000px"></main></body>`);
    await scrollLockPage.addStyleTag({ content: themeCss });
    await scrollLockPage.addStyleTag({ content: redesignCss });
    await scrollLockPage.addScriptTag({ path: scriptPath });
    await scrollLockPage.evaluate(() => window.scrollTo(0, 500));
    await scrollLockPage.waitForFunction(() => document.body.classList.contains('nav-sticky'));
    await scrollLockPage.locator('.menu-toggle').evaluate((element) => element.click());
    const stickyShippingBottom = await scrollLockPage.evaluate(() => {
      const probe = document.createElement('div');
      probe.style.height = 'var(--home-shipping-height)';
      document.body.append(probe);
      const height = probe.getBoundingClientRect().height;
      probe.remove();
      return height;
    });
    assert.equal(
      await scrollLockPage.locator('.mobile-menu').evaluate((element) => element.getBoundingClientRect().top),
      stickyShippingBottom,
      'the drawer must stay attached below the visible sticky shipping banner',
    );
    const lockedScrollY = await scrollLockPage.evaluate(() => window.scrollY);
    await scrollLockPage.mouse.wheel(0, 600);
    await scrollLockPage.waitForTimeout(50);
    assert.equal(await scrollLockPage.evaluate(() => window.scrollY), lockedScrollY, 'an open drawer must prevent background scrolling');
    await scrollLockPage.locator('.mobile-menu-close').click();
    assert.equal(await scrollLockPage.evaluate(() => window.scrollY), lockedScrollY, 'closing the drawer must preserve the page position');
    assert.equal(await scrollLockPage.locator('html').evaluate((element) => element.classList.contains('mobile-menu-open')), false, 'closing the drawer must unlock the root scroller');
    await scrollLockPage.close();
  } finally {
    await browser.close();
  }

  console.log('Mobile menu scaling verified across phone and tablet breakpoints.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
