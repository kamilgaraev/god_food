const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const baseUrl = (process.env.THEOBROMA_URL || 'http://localhost:8080').replace(/\/$/, '');
const profiles = [
  { width: 320, expectedRoot: 15 },
  { width: 390, expectedRoot: 16 },
  { width: 768, expectedRoot: 16 },
  { width: 1440, expectedRoot: 16 },
  { width: 1920, expectedRoot: 17.7143 },
  { width: 2560, expectedRoot: 20 },
  { width: 3200, expectedRoot: 20 },
  { width: 3840, expectedRoot: 20 },
];
const overflowWidths = [320, 390, 600, 768, 900, 1199, 1200, 1440, 1920, 2560, 3200];
const routes = [
  '/',
  '/catalog/',
  '/product/theobroma-100-70/',
  '/recipes/',
  '/recipe/classic/',
  '/delivery/',
  '/media/',
  '/chto-oznachayut-protsenty-na-plitke-shokolada/',
  '/policy/',
  '/corporate-gifts/',
  '/my-account/',
  '/cart/',
  '/checkout/',
];

async function openPage(browser, path, width) {
  const context = await browser.newContext({
    viewport: { width, height: width <= 600 ? 932 : 1200 },
    reducedMotion: 'reduce',
  });
  const page = await context.newPage();
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));
  const response = await page.goto(`${baseUrl}${path}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.evaluate(() => document.fonts.ready);
  return { context, page, pageErrors, response };
}

async function dimensionsAt(browser, width) {
  const { context, page } = await openPage(browser, '/', width);
  try {
    return await page.evaluate(() => {
      const dimensions = (selector) => {
        const box = document.querySelector(selector).getBoundingClientRect();
        return { width: box.width, height: box.height };
      };
      return {
        brand: dimensions('.brand'),
        button: dimensions('.button'),
        footer: dimensions('.footer-shell'),
      };
    });
  } finally {
    await context.close();
  }
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const profile of profiles) {
      const { context, page } = await openPage(browser, '/', profile.width);
      try {
        const rootSize = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize));
        assert.ok(
          Math.abs(rootSize - profile.expectedRoot) <= 0.02,
          `${profile.width}px: expected root ${profile.expectedRoot}px, received ${rootSize}px`,
        );
      } finally {
        await context.close();
      }
    }

    const capped = await dimensionsAt(browser, 2560);
    const ultrawide = await dimensionsAt(browser, 3200);
    for (const component of Object.keys(capped)) {
      for (const dimension of ['width', 'height']) {
        assert.ok(
          Math.abs(capped[component][dimension] - ultrawide[component][dimension]) <= 0.5,
          `${component} ${dimension} grows after the 2560px cap`,
        );
      }
    }

    for (const width of overflowWidths) {
      for (const route of routes) {
        const { context, page, pageErrors, response } = await openPage(browser, route, width);
        try {
          const metrics = await page.evaluate(() => ({
            origin: location.origin,
            viewportWidth: document.documentElement.clientWidth,
            scrollWidth: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth),
          }));
          assert.ok(response && response.ok(), `${width}px ${route}: HTTP ${response?.status() || 'none'}`);
          assert.equal(metrics.origin, new URL(baseUrl).origin, `${width}px ${route}: left the expected origin`);
          assert.deepEqual(pageErrors, [], `${width}px ${route}: page errors: ${pageErrors.join('; ')}`);
          assert.ok(
            metrics.scrollWidth - metrics.viewportWidth <= 1,
            `${width}px ${route}: horizontal overflow ${metrics.scrollWidth - metrics.viewportWidth}px`,
          );
        } finally {
          await context.close();
        }
      }
    }
  } finally {
    await browser.close();
  }

  console.log('Fluid rem responsive contract verified from 320px through ultrawide');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
