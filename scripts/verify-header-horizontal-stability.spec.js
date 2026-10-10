const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const theme = path.join(root, 'wp-content/themes/theobroma');

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROME_PATH,
    args: ['--no-proxy-server', '--disable-http2'],
  });
  try {
    for (const width of [320, 390, 600, 768, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await page.setContent(`
        <header class="site-header">
          <a class="shipping"><span>Бесплатная доставка от 3000 рублей</span></a>
          <nav class="nav">
            <div class="nav-links nav-links-study"><a>Каталог</a><a>Рецепты</a></div>
            <a class="brand"><img width="252" height="106" alt="Theobroma"></a>
            <div class="nav-links nav-links-transactional floating-actions">
              <a class="header-icon header-account header-with-label"><span class="header-action-label">Избранное</span><span class="header-action-icon">♡</span></a>
              <a class="header-icon header-cart header-with-label"><span class="header-action-label">Корзина</span><span class="header-action-icon">♧</span></a>
              <a class="header-icon header-account header-with-label"><span class="header-action-label">Личный кабинет</span><span class="header-action-icon">♙</span></a>
            </div>
            <button class="menu-toggle" aria-expanded="false"><span></span><span></span><span></span></button>
          </nav>
        </header>
        <main style="height:3000px"></main>
      `);
      await page.addStyleTag({ path: path.join(theme, 'style.css') });
      await page.addStyleTag({ path: path.join(theme, 'assets/css/home-redesign.css') });
      await page.addScriptTag({ path: path.join(theme, 'assets/js/site-header.js') });
      const measure = () => page.evaluate(() => {
        const rect = selector => document.querySelector(selector).getBoundingClientRect();
        const brand = rect('.site-header .brand');
        const actions = rect('.site-header .floating-actions');
        const header = rect('.site-header');
        return { brandCenter: (brand.left + brand.right) / 2, actionsRight: actions.right,
          headerLeft: header.left, headerRight: header.right, width: innerWidth };
      });
      const before = await measure();
      await page.evaluate(() => window.scrollTo(0, 200));
      await page.waitForFunction(() => document.body.classList.contains('nav-sticky'));
      const after = await measure();
      assert.ok(Math.abs(after.headerLeft) < 1 && Math.abs(after.headerRight - width) < 1,
        `${width}px: header shifted outside viewport`);
      assert.ok(Math.abs(after.brandCenter - before.brandCenter) <= 1,
        `${width}px: logo shifted horizontally on scroll (${before.brandCenter} → ${after.brandCenter})`);
      assert.ok(Math.abs(after.actionsRight - before.actionsRight) <= 1,
        `${width}px: actions row shifted horizontally on scroll (${before.actionsRight} → ${after.actionsRight})`);
      if (width <= 600) assert.ok(Math.abs(after.brandCenter - width / 2) <= 1,
        `${width}px: mobile logo is not centered`);
      await page.close();
    }
  } finally {
    await browser.close();
  }
  console.log('Header alignment remains stable across scroll and viewport widths.');
})().catch(error => { console.error(error); process.exitCode = 1; });
