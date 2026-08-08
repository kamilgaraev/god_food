const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 768, height: 1024 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    await page.goto('http://localhost:8080/', { waitUntil: 'networkidle' });
    await page.evaluate(async () => document.fonts?.ready);

    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), 768, '768px horizontal overflow');
    assert.equal(await page.locator('.home-product-card').count(), 4, 'Tablet homepage must show four products');

    const cards = await page.locator('.home-product-card').evaluateAll((nodes) => nodes.slice(0, 3).map((node) => {
      const box = node.getBoundingClientRect();
      return { x: box.x, y: box.y };
    }));
    assert.ok(Math.abs(cards[0].y - cards[1].y) < 2, 'First two tablet cards must share a row');
    assert.ok(cards[2].y > cards[0].y, 'Third tablet card must start the second row');
    assert.equal(await page.locator('[data-cacao-option="70"]').getAttribute('aria-selected'), 'true');
    assert.equal(await page.locator('[data-cacao-panel]').evaluate((node) => getComputedStyle(node).transitionDuration), '0s');
    assert.ok(await page.locator('.story').isVisible(), 'Brand story must remain visible');
    assert.equal(await page.locator('.home-composition dl > div').count(), 4, 'Composition block must expose four facts');
    assert.ok(await page.locator('.reviews .review').first().isVisible(), 'Reviews must remain visible');
    assert.ok(await page.locator('.contact form').isVisible(), 'Question form must remain visible');
    await context.close();
  } finally {
    await browser.close();
  }

  console.log('Tablet homepage redesign verified');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
