const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { chromium } = require('playwright');

const origin = process.env.SITE_URL || 'https://theobroma.one';
const url = new URL('/404-layout-check-20261117/', origin).href;
const preview = process.env.NOT_FOUND_PREVIEW !== '0';
const output = process.env.NOT_FOUND_OUTPUT || path.join(os.tmpdir(), 'theobroma-404-layout');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  fs.mkdirSync(output, { recursive: true });

  try {
    if (preview) {
      for (const file of ['style.css', 'assets/css/design-system.css']) {
        const stylesheet = `/wp-content/themes/theobroma/${file}`;
        await page.route(requestUrl => requestUrl.pathname === stylesheet, route => route.fulfill({
          path: path.join(__dirname, '../wp-content/themes/theobroma', file),
          contentType: 'text/css',
        }));
      }
    }

    for (const width of [320, 390, 768, 1440, 2560]) {
      await page.setViewportSize({ width, height: 1000 });
      const response = await page.goto(url, { waitUntil: 'networkidle' });
      assert.equal(response.status(), 404, 'The missing page must retain its HTTP 404 status');
      await page.evaluate(() => document.fonts.ready);
      const metrics = await page.locator('.site-not-found').evaluate(main => {
        const box = element => {
          const r = element.getBoundingClientRect();
          return { x: r.x, y: r.y, width: r.width, height: r.height };
        };
        const heading = main.querySelector('h1');
        return {
          main: box(main),
          number: box(main.querySelector('.site-not-found__number')),
          heading: box(heading),
          copy: box(main.querySelector('p')),
          actions: box(main.querySelector('.site-not-found__actions')),
          headingPosition: getComputedStyle(heading).position,
          headingCase: getComputedStyle(heading).textTransform,
          overflow: document.documentElement.scrollWidth > innerWidth,
        };
      });
      assert.ok(!['absolute', 'fixed'].includes(metrics.headingPosition), `Heading must stay in flow at ${width}px`);
      assert.equal(metrics.headingCase, 'none', 'Heading must use normal case');
      assert.equal(metrics.overflow, false, `No horizontal overflow at ${width}px`);
      for (const key of ['number', 'heading', 'copy', 'actions']) {
        const box = metrics[key];
        assert.ok(Math.abs(box.x + box.width / 2 - width / 2) < 2, `${key} must be centered at ${width}px`);
        assert.ok(box.x >= 0 && box.x + box.width <= width + 1, `${key} must fit at ${width}px`);
      }
      for (const [previous, next] of [['number', 'heading'], ['heading', 'copy'], ['copy', 'actions']]) {
        assert.ok(metrics[previous].y + metrics[previous].height <= metrics[next].y, `${previous} and ${next} must not overlap`);
      }
      for (const action of await page.locator('.site-not-found__actions a').all()) {
        assert.ok((await action.boundingBox()).height >= 44, `Action must have a 44px touch target at ${width}px`);
      }
      await page.screenshot({ path: path.join(output, `${preview ? 'preview' : 'production'}-${width}.png`), fullPage: true });
    }

    const home = page.getByRole('link', { name: 'На главную', exact: true });
    const catalog = page.getByRole('link', { name: 'Открыть каталог', exact: true });
    await home.focus();
    assert.ok(await home.evaluate(element => element.matches(':focus-visible')));
    await page.keyboard.press('Tab');
    assert.ok(await catalog.evaluate(element => element === document.activeElement), 'Both actions must be keyboard reachable');
    assert.ok(await catalog.evaluate(element => parseFloat(getComputedStyle(element).outlineWidth) >= 2), 'Focused action must have a visible outline');
    const restingBackground = await home.evaluate(element => getComputedStyle(element).backgroundColor);
    await home.hover();
    assert.notEqual(await home.evaluate(element => getComputedStyle(element).backgroundColor), restingBackground, 'Primary action must show hover feedback');
    const catalogUrl = await catalog.getAttribute('href');
    const homeResponse = await context.request.get(await home.getAttribute('href'));
    const catalogResponse = await context.request.get(catalogUrl);
    assert.equal(homeResponse.status(), 200, 'Home action must reach a working page');
    assert.equal(catalogResponse.status(), 200, 'Catalog action must reach a working page');
    console.log(`404 layout: 5 widths, HTTP status, keyboard focus and action destinations passed (${preview ? 'preview' : 'production'}). Screenshots: ${output}`);
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
