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
    for (const width of [1440, 2048]) {
      const page = await openHomepage(browser, width, html);
      const metrics = await page.evaluate(() => {
        const bounds = (element) => {
          const rect = element.getBoundingClientRect();
          return { left: rect.left, right: rect.right, width: rect.width };
        };
        const composition = document.querySelector('.home-composition');
        const shell = document.querySelector('.home-composition__shell');
        const cards = Array.from(document.querySelectorAll('.home-promo-card'));
        return {
          viewport: innerWidth,
          composition: composition ? bounds(composition) : null,
          shell: shell ? bounds(shell) : null,
          promoCards: cards.length === 2
            ? { left: bounds(cards[0]).left, right: bounds(cards[1]).right }
            : null,
        };
      });

      assert(metrics.composition, `${width}px composition section must exist`);
      assert.equal(metrics.composition.width, metrics.viewport, `${width}px composition background must remain full width`);
      assert(metrics.shell, `${width}px composition copy and facts must have a shared inner container`);
      assert(metrics.promoCards, `${width}px promo section must contain two cards`);
      assert(Math.abs(metrics.shell.left - metrics.promoCards.left) <= 12, `${width}px composition and promo content must share the left container edge`);
      assert(Math.abs(metrics.shell.right - metrics.promoCards.right) <= 12, `${width}px composition and promo content must share the right container edge`);
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
