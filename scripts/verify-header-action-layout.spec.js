const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const headerSource = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/header.php'), 'utf8');
const homeStyles = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/assets/css/home-redesign.css'), 'utf8');
const headerMarkup = headerSource
  .match(/<header class="site-header">[\s\S]*?<\/header>/)?.[0]
  .replace(/<\?php[\s\S]*?\?>/g, '');

assert.ok(headerMarkup, 'The site header markup must be available to the layout test');

async function withRenderedHeader(callback) {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 320 } });
    await page.setContent(`<style>${homeStyles}</style>${headerMarkup}`);
    return await callback(page);
  } finally {
    await browser.close();
  }
}

test('cart is rendered before the account action', async () => {
  const classes = await withRenderedHeader((page) => page.locator('.nav-links-transactional > a').evaluateAll(
    (actions) => actions.map((action) => Array.from(action.classList).find((name) => name.startsWith('header-') && name !== 'header-icon')),
  ));

  assert.deepEqual(classes, ['header-where', 'header-cart', 'header-account']);
});

test('cart and account controls use no more than half a spacing unit', async () => {
  const metrics = await withRenderedHeader((page) => page.evaluate(() => {
    const cart = document.querySelector('.header-cart').getBoundingClientRect();
    const account = document.querySelector('.header-account').getBoundingClientRect();
    return {
      gap: account.left - cart.right,
      rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
    };
  }));

  assert.ok(
    metrics.gap >= 0 && metrics.gap <= metrics.rootFontSize * 0.5 + 0.5,
    `Expected cart/account gap at most 0.5rem, received ${metrics.gap}px`,
  );
});
