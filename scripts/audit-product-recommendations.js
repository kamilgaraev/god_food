const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const config = require('./pairwise-audit.config.json');
const sourceBase = process.env.THEOBROMA_SOURCE_URL || 'https://theobroma.one';
const localBase = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';
const products = config.pairs.filter((pair) => pair.group === 'products');
const localIds = new Map(products.map((pair) => [new URL(pair.local, localBase).pathname.replace(/\/$/, ''), pair.id]));

function unique(values) {
  return [...new Set(values)];
}

async function extractSource(page, url) {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('.js-product-relevant', { state: 'attached', timeout: 15000 });
  return page.locator('.js-product-relevant').evaluateAll((cards) => cards.slice(0, 4).map((card) => ({
    id: card.dataset.productUid,
    title: card.textContent.replace(/\s+/g, ' ').trim(),
    url: card.dataset.productUrl,
  })));
}

async function extractLocal(page, url) {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForSelector('.product-related-grid-desktop article', { state: 'attached', timeout: 15000 });
  const cards = await page.locator('.product-related-grid-desktop article').evaluateAll((elements) => elements
    .filter((element) => element.offsetParent !== null)
    .slice(0, 4)
    .map((card) => {
      const link = card.querySelector('h3 a[href]');
      return { path: new URL(link.href).pathname.replace(/\/$/, ''), title: link.textContent.replace(/\s+/g, ' ').trim() };
    }));
  return cards.map((card) => ({ ...card, id: localIds.get(card.path) }));
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const pair of products) {
      const page = await browser.newPage({ viewport: { width: 1920, height: 1200 } });
      const source = await extractSource(page, new URL(pair.source, sourceBase).href);
      const local = await extractLocal(page, new URL(pair.local, localBase).href);
      await page.close();
      const sourceIds = unique(source.map((card) => card.id).filter(Boolean));
      const localCardIds = unique(local.map((card) => card.id).filter(Boolean));
      const matches = source.length === 4 && sourceIds.length === 4 && local.length === 4 && localCardIds.length === 4 && !localCardIds.includes(pair.id);
      results.push({ id: pair.id, matches, source, local });
      console.log(`${matches ? 'PASS' : 'FAIL'} ${pair.id}: source ${sourceIds.length}/4 unique, local ${localCardIds.length}/4 unique`);
    }
  } finally {
    await browser.close();
  }

  const output = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'product-recommendations.json');
  fs.mkdirSync(path.dirname(output), { recursive: true });
  fs.writeFileSync(output, `${JSON.stringify(results, null, 2)}\n`);
  const failures = results.filter((result) => !result.matches);
  console.log(`Recommendation matches: ${results.length - failures.length}/${results.length}`);
  if (failures.length) process.exitCode = 1;
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
