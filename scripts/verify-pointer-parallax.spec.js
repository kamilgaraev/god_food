const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = (process.env.THEOBROMA_URL || 'http://localhost:8080').replace(/\/$/, '');
const themeDir = process.env.THEOBROMA_THEME_DIR;

async function openPage(browser, pathname, options = {}) {
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
    reducedMotion: options.reducedMotion || 'no-preference',
    hasTouch: options.hasTouch || false,
  });
  const page = await context.newPage();

  if (new URL(baseUrl).port !== '8080') {
    await page.route('http://localhost:8080/**', async (route) => {
      const target = new URL(route.request().url());
      const local = new URL(baseUrl);
      target.protocol = local.protocol;
      target.hostname = local.hostname;
      target.port = local.port;
      await route.continue({ url: target.href });
    });
  }

  await page.goto(`${baseUrl}${pathname}`, { waitUntil: 'networkidle', timeout: 45_000 });
  await page.evaluate(() => document.fonts.ready);

  if (themeDir) {
    await page.addStyleTag({ content: fs.readFileSync(path.join(themeDir, 'style.css'), 'utf8') });
    await page.addScriptTag({ content: fs.readFileSync(path.join(themeDir, 'assets/js/decorative-motion.js'), 'utf8') });
  }

  return { context, page };
}

const cases = [
  { pathname: '/', selector: '.about-award', label: 'homepage chocolate' },
  { pathname: '/cooperation/', selector: 'img[src*="cooperation-chocolate.webp"]', label: 'cooperation chocolate' },
];

function translation(transform) {
  if (!transform || transform === 'none') return { x: 0, y: 0 };
  const values = transform.match(/^matrix(?:3d)?\((.+)\)$/)?.[1].split(',').map(Number) || [];
  return values.length === 16
    ? { x: values[12], y: values[13] }
    : { x: values[4] || 0, y: values[5] || 0 };
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const testCase of cases) {
      const { context, page } = await openPage(browser, testCase.pathname);
      const matchingImages = page.locator(testCase.selector);
      const animatedImages = page.locator(`${testCase.selector}[data-pointer-parallax]`);

      assert.ok(await matchingImages.count() > 0, `${testCase.label} asset is missing`);
      assert.equal(
        await animatedImages.count(),
        await matchingImages.count(),
        `${testCase.label} must opt into pointer parallax`,
      );
      assert.equal(
        await animatedImages.first().evaluate((element) => getComputedStyle(element).animationName),
        'none',
        `${testCase.label} must not animate without pointer input`,
      );

      await page.mouse.move(1430, 990);
      await page.waitForTimeout(500);
      const movedTransform = await animatedImages.first().evaluate((element) => getComputedStyle(element).transform);
      const moved = translation(movedTransform);
      assert.ok(moved.x >= 8 && moved.x <= 12.5, `${testCase.label} horizontal parallax must stay light, got ${moved.x}px`);
      assert.ok(moved.y >= 5 && moved.y <= 8.5, `${testCase.label} vertical parallax must stay light, got ${moved.y}px`);

      await page.mouse.move(720, 500);
      await page.waitForTimeout(500);
      const centered = translation(await animatedImages.first().evaluate((element) => getComputedStyle(element).transform));
      assert.ok(Math.abs(centered.x) < 1 && Math.abs(centered.y) < 1, `${testCase.label} parallax must settle near its origin at viewport center`);
      await context.close();

      const reduced = await openPage(browser, testCase.pathname, { reducedMotion: 'reduce' });
      await reduced.page.mouse.move(1430, 990);
      await reduced.page.waitForTimeout(100);
      assert.equal(
        await reduced.page.locator(`${testCase.selector}[data-pointer-parallax]`).first().evaluate((element) => getComputedStyle(element).transform),
        'none',
        `${testCase.label} must stay static for reduced-motion users`,
      );
      await reduced.context.close();

      const touch = await openPage(browser, testCase.pathname, { hasTouch: true });
      assert.equal(
        await touch.page.locator(`${testCase.selector}[data-pointer-parallax]`).first().evaluate((element) => getComputedStyle(element).transform),
        'none',
        `${testCase.label} must stay static on touch devices`,
      );
      await touch.context.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Pointer-driven chocolate parallax verified.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
