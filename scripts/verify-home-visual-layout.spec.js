const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const failures = [];

function assert(condition, message) {
  if (!condition) {
    failures.push(message);
  }
}

async function loadHomepage(browser, viewport) {
  const page = await browser.newPage({ viewport });
  await page.goto(BASE_URL, { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: '.cookie-notice { display: none !important; }' });
  return page;
}

async function box(page, selector) {
  const element = page.locator(selector);
  if (!await element.count()) {
    failures.push(`${selector} is missing`);
    return null;
  }

  const value = await element.first().boundingBox();
  if (!value) {
    failures.push(`${selector} must have a layout box`);
    return null;
  }

  return value;
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const viewport of [
      { width: 2295, height: 1119 },
      { width: 1440, height: 900 },
      { width: 1200, height: 1222 },
      { width: 768, height: 1024 },
      { width: 390, height: 844 },
    ]) {
      const page = await loadHomepage(browser, viewport);
      const { documentWidth, viewportWidth } = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
      }));

      assert(documentWidth === viewportWidth, 'document must not overflow horizontally');

      if (viewport.width === 1440) {
        const hero = await box(page, '.home-hero');
        await box(page, '.home-benefit-strip');
        const catalog = await box(page, '.home-catalog');

        if (hero) {
          assert(hero.height >= 400 && hero.height <= 500, 'desktop hero must stay within 400–500px');
        }
        if (catalog) {
          assert(catalog.y <= 570, 'catalog must enter the first 570px of the page');
        }
      }

      if (viewport.width === 2295) {
        const wideGrid = await box(page, '.home-product-grid');
        const wideCard = await box(page, '.home-product-grid .home-product-card');
        await box(page, '.home-cacao');
        const cacaoCircle = await box(page, '.home-cacao__image-wrap');

        if (wideCard) {
          assert(wideCard.width <= 340, 'ultrawide product cards must not exceed 340px');
        }
        if (wideGrid) {
          assert(wideGrid.width <= 1440, 'catalog grid must be capped at 1440px');
        }
        if (cacaoCircle) {
          assert(cacaoCircle.width <= 440, 'desktop cacao image circle must be capped at 440px');
        }
      }

      await page.close();
    }

    if (failures.length) {
      throw new Error(failures.join('\n'));
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
