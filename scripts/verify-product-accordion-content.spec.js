const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const config = require('./pairwise-audit.config.json');

const products = config.pairs.filter((pair) => pair.group === 'products');
const sourceBase = process.env.THEOBROMA_SOURCE_URL || 'https://theobroma.one';
const localBase = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';

function normalize(text) {
  return text.replace(/\s+/g, ' ').trim();
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const source = await context.newPage();
    const local = await context.newPage();

    for (const product of products) {
      await source.goto(new URL(product.source, sourceBase).href, { waitUntil: 'domcontentloaded', timeout: 45_000 });
      await source.waitForSelector('.t-store__tabs__item-title', { state: 'attached', timeout: 20_000 });
      const sourceTitles = (await source.locator('.t-store__tabs__item-title').allTextContents()).map(normalize);
      const sourceContents = (await source.locator('.t-store__tabs__content').allTextContents()).map(normalize);

      await local.goto(new URL(product.local, localBase).href, { waitUntil: 'domcontentloaded', timeout: 45_000 });
      await local.waitForSelector('.commerce-modal.is-open .product-detail-accordions details', { state: 'attached', timeout: 20_000 });
      const accordions = local.locator('.commerce-modal.is-open .product-detail-accordions details');
      const localTitles = (await accordions.locator('summary').allTextContents()).map(normalize);
      const localContents = (await accordions.locator(':scope > div').allTextContents()).map(normalize);

      assert.deepEqual(localTitles, sourceTitles, `${product.id}: accordion titles differ from the source product`);
      assert.deepEqual(localContents, sourceContents, `${product.id}: accordion contents differ from the source product`);
      console.log(`PASS ${product.id}: ${localTitles.length} accordion(s)`);
    }

    await context.close();
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
