const { chromium } = require('playwright');

const sourceUrl = 'https://theobroma.one/catalog/tproduct/281858081192-kakao-poroshok-naturalnii';
const localUrl = 'http://localhost:8080/product/theobroma-cacao-200/';

async function waitForProduct(page, side) {
  await page.goto(side === 'source' ? sourceUrl : localUrl, {
    waitUntil: 'domcontentloaded',
    timeout: 45_000,
  });
  await page.waitForSelector(
    side === 'source' ? '.t-store__prod-popup__slider' : '.commerce-modal.is-open .product-detail-image',
    { state: 'visible', timeout: 20_000 },
  );
  await page.evaluate(() => document.fonts?.ready);
}

async function main() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 390, height: 932 } });
    const source = await context.newPage();
    const local = await context.newPage();
    await waitForProduct(source, 'source');
    await waitForProduct(local, 'local');

    const sourceTopBar = await source.evaluate(() => {
      let element = document.elementFromPoint(195, 20);
      while (element) {
        const color = getComputedStyle(element).backgroundColor;
        if (color !== 'rgba(0, 0, 0, 0)') return color;
        element = element.parentElement;
      }
      return 'rgba(0, 0, 0, 0)';
    });
    const localTopBar = await local.locator('.commerce-modal[data-commerce-type="product"]').evaluate((element) => (
      getComputedStyle(element, '::before').backgroundColor
    ));

    if (sourceTopBar !== 'rgb(74, 74, 74)') {
      throw new Error(`Unexpected source mobile product bar: ${sourceTopBar}`);
    }
    if (localTopBar !== sourceTopBar) {
      throw new Error(`Mobile product bar differs: source ${sourceTopBar}, local ${localTopBar}`);
    }

    console.log(`PASS product mobile top bar: ${localTopBar}`);
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
