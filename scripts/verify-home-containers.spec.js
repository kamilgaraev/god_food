const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = (process.env.THEOBROMA_URL || 'http://localhost:8080').replace(/\/$/, '');
const themeRoot = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma');
const styles = [
  fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8'),
  fs.readFileSync(path.join(themeRoot, 'assets', 'css', 'home-redesign.css'), 'utf8'),
].join('\n');

function fetchHomepage() {
  const target = new URL(baseUrl);
  return new Promise((resolve, reject) => {
    const request = http.get({
      hostname: target.hostname,
      port: target.port || 80,
      path: '/',
      headers: { Host: 'localhost:8080' },
    }, (response) => {
      let html = '';
      response.setEncoding('utf8');
      response.on('data', (chunk) => { html += chunk; });
      response.on('end', () => resolve(html));
    });
    request.on('error', reject);
  });
}

async function openHomepage(browser, width, html) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  const document = html
    .replace(/<link\b[^>]*rel=['"]stylesheet['"][^>]*>/gi, '')
    .replace(/<script\b[\s\S]*?<\/script>/gi, '');
  await page.setContent(document, { waitUntil: 'domcontentloaded' });
  await page.addStyleTag({ content: styles });
  return page;
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const html = await fetchHomepage();
    for (const width of [1100, 2048, 2560]) {
      const page = await openHomepage(browser, width, html);
      const metrics = await page.evaluate(() => {
        const bounds = (element) => {
          const rect = element.getBoundingClientRect();
          return { left: rect.left, right: rect.right, width: rect.width };
        };
        const composition = document.querySelector('.home-composition');
        const containerSelectors = [
          '.home-section-heading',
          '.home-product-grid',
          '.home-cacao__shell',
          '.home-composition__shell',
        ];
        const containers = containerSelectors.map((selector) => ({ selector, ...bounds(document.querySelector(selector)) }));
        const cards = Array.from(document.querySelectorAll('.home-promo-card'));
        return {
          viewport: innerWidth,
          rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
          composition: composition ? bounds(composition) : null,
          containers,
          promoCards: cards.length === 2
            ? { left: bounds(cards[0]).left, right: bounds(cards[1]).right }
            : null,
        };
      });

      assert(metrics.composition, `${width}px composition section must exist`);
      assert.equal(metrics.composition.width, metrics.viewport, `${width}px composition background must remain full width`);
      const containerLefts = metrics.containers.map(({ left }) => left);
      const containerRights = metrics.containers.map(({ right }) => right);
      assert(Math.max(...containerLefts) - Math.min(...containerLefts) <= 2, `${width}px homepage sections must share one left container edge`);
      assert(Math.max(...containerRights) - Math.min(...containerRights) <= 2, `${width}px homepage sections must share one right container edge`);
      assert(metrics.promoCards, `${width}px promo section must contain two cards`);
      const compositionShell = metrics.containers.find(({ selector }) => selector === '.home-composition__shell');
      assert(Math.abs(compositionShell.left - metrics.promoCards.left) <= 12, `${width}px composition and promo content must share the left container edge`);
      assert(Math.abs(compositionShell.right - metrics.promoCards.right) <= 12, `${width}px composition and promo content must share the right container edge`);
      if (width === 1100) {
        const expectedGutter = 2.5 * metrics.rootFontSize;
        assert(Math.abs(compositionShell.left - expectedGutter) <= 2, `${width}px tablet content must use the shared ${expectedGutter.toFixed(1)}px side gutter instead of a narrow fixed measure`);
        assert(Math.abs(metrics.viewport - compositionShell.right - expectedGutter) <= 2, `${width}px tablet content must use the shared ${expectedGutter.toFixed(1)}px side gutter on both sides`);
      }
      await page.close();
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
