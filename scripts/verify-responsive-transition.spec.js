const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');

const baseUrl = (process.env.THEOBROMA_URL || 'https://theobroma.uit-dev.ru').replace(/\/$/, '');
const cssFile = process.env.THEOBROMA_CSS_FILE || '';
const screenshotDir = process.env.THEOBROMA_SCREENSHOT_DIR || '';
const widths = [901, 1024, 1159, 1199, 1200, 1280];
const routes = [
  {
    path: '/',
    selectors: [
      '.nav',
      '.brand',
      '.home-hero__shell',
      '.home-catalog',
      '.home-cacao__shell',
      '.home-composition',
      '.home-promo-grid',
      '.about-stage',
      '.reviews-stage',
      '.contact-card',
      '.footer-shell',
    ],
  },
  {
    path: '/catalog/',
    selectors: ['.nav', '.catalog-title', '.catalog-page .shop-shell'],
  },
  {
    path: '/recipes/',
    selectors: ['.nav', '.recipes-breadcrumb', '.recipes-page h1', '.recipe-grid'],
  },
  {
    path: '/delivery/',
    selectors: ['.nav', '.delivery-breadcrumb', '.delivery-page h1', '.delivery-accordion'],
  },
  {
    path: '/media/',
    selectors: ['.nav', '.media-breadcrumb', '.media-page h1', '.media-grid'],
  },
];

function assertInsideViewport(metric, context) {
  const tolerance = 1;
  assert.ok(metric.width <= metric.viewportWidth + tolerance, `${context}: width ${metric.width}px exceeds viewport ${metric.viewportWidth}px`);
  assert.ok(metric.left >= -tolerance, `${context}: left edge is clipped at ${metric.left}px`);
  assert.ok(metric.right <= metric.viewportWidth + tolerance, `${context}: right edge is clipped at ${metric.right}px`);
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of widths) {
      for (const route of routes) {
        const page = await browser.newPage({ viewport: { width, height: 1000 } });
        await page.goto(`${baseUrl}${route.path}`, { waitUntil: 'domcontentloaded' });
        await page.locator('.site-header').waitFor({ state: 'attached' });
        await page.evaluate(() => document.fonts.ready);

        if (cssFile) {
          await page.addStyleTag({ content: fs.readFileSync(cssFile, 'utf8') });
        }

        for (const selector of route.selectors) {
          const locator = page.locator(selector).first();
          await locator.waitFor({ state: 'attached' });
          const metric = await locator.evaluate((element) => {
            const box = element.getBoundingClientRect();
            return {
              left: box.left,
              right: box.right,
              width: box.width,
              viewportWidth: document.documentElement.clientWidth,
            };
          });
          assertInsideViewport(metric, `${width}px ${route.path} ${selector}`);
        }

        if (screenshotDir && route.path === '/') {
          fs.mkdirSync(screenshotDir, { recursive: true });
          await page.screenshot({ path: `${screenshotDir}/home-${width}.png`, fullPage: true });
        }

        await page.close();
      }
    }
  } finally {
    await browser.close();
  }

  console.log(`Responsive transition verified at: ${widths.join(', ')}px`);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
