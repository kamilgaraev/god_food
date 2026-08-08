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
  assert(await element.count(), `${selector} is missing`);
  const value = await element.first().boundingBox();
  assert(value, `${selector} must have a layout box`);
  return value;
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const desktop = await loadHomepage(browser, { width: 1440, height: 900 });
    const hero = await box(desktop, '.home-hero');
    const benefitStrip = await box(desktop, '.home-benefit-strip');
    const catalog = await box(desktop, '.home-catalog');

    assert(hero.height >= 400 && hero.height <= 500, 'desktop hero must stay within 400–500px');
    assert(catalog.y <= 570, 'catalog must enter the first 570px of the page');
    await desktop.close();

    const ultrawide = await loadHomepage(browser, { width: 2295, height: 1119 });
    const wideGrid = await box(ultrawide, '.home-product-grid');
    const wideCard = await box(ultrawide, '.home-product-grid .home-product-card');
    const cacao = await box(ultrawide, '.home-cacao');
    const cacaoCircle = await box(ultrawide, '.home-cacao__image-wrap');
    const { documentWidth, viewportWidth } = await ultrawide.evaluate(() => ({
      documentWidth: document.documentElement.scrollWidth,
      viewportWidth: document.documentElement.clientWidth,
    }));

    assert(wideCard.width <= 340, 'ultrawide product cards must not exceed 340px');
    assert(wideGrid.width <= 1440, 'catalog grid must be capped at 1440px');
    assert(cacaoCircle.width <= 440, 'desktop cacao image circle must be capped at 440px');
    assert(documentWidth === viewportWidth, 'document must not overflow horizontally');
    await ultrawide.close();

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
