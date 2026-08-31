const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const cssPath = path.join(root, 'wp-content/themes/theobroma/style.css');
const homeCssPath = path.join(root, 'wp-content/themes/theobroma/assets/css/home-redesign.css');
const headerScriptPath = path.join(root, 'wp-content/themes/theobroma/assets/js/site-header.js');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of [390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await page.setContent(`
        <!doctype html>
        <header class="site-header">
          <a class="shipping"></a>
          <nav class="nav">
            <div class="nav-links nav-links-study"></div>
            <a class="brand">Theobroma</a>
            <div class="nav-links nav-links-transactional floating-actions"></div>
          </nav>
        </header>
        <main class="home">
          <section style="height: 1200px">
            <a class="home-button" href="#cacao-selector">Выберите свой вкус</a>
          </section>
          <section class="home-cacao" id="cacao-selector" style="height: 900px"></section>
          <div style="height: 900px"></div>
        </main>
      `);
      await page.addStyleTag({ path: cssPath });
      await page.addStyleTag({ path: homeCssPath });
      await page.addScriptTag({ path: headerScriptPath });

      await page.locator('a[href="#cacao-selector"]').click();
      await page.waitForFunction(() => document.body.classList.contains('nav-sticky'));
      await page.waitForTimeout(width > 600 ? 700 : 50);

      const layout = await page.evaluate(() => {
        const nav = document.querySelector('.nav').getBoundingClientRect();
        const target = document.querySelector('#cacao-selector').getBoundingClientRect();
        return { navBottom: nav.bottom, targetTop: target.top };
      });

      assert.ok(
        layout.targetTop >= layout.navBottom - 1,
        `${width}px: anchor target starts at ${layout.targetTop}px behind the sticky header ending at ${layout.navBottom}px`,
      );
      assert.ok(
        layout.targetTop <= layout.navBottom + 2,
        `${width}px: anchor target leaves an uneven ${(layout.targetTop - layout.navBottom).toFixed(2)}px gap below the sticky header`,
      );

      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Homepage anchor targets align directly below the sticky header');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
