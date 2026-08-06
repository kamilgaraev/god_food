const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 768, height: 1024 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    await page.goto('http://localhost:8080/delivery/', { waitUntil: 'networkidle' });
    await page.evaluate(async () => document.fonts?.ready);
    const metrics = await page.evaluate(() => {
      const accordion = document.querySelector('.delivery-accordion').getBoundingClientRect();
      const footer = document.querySelector('.site-footer').getBoundingClientRect();
      return {
        height: document.documentElement.scrollHeight,
        scrollWidth: document.documentElement.scrollWidth,
        accordion: { x: accordion.x, y: accordion.y + scrollY, width: accordion.width, height: accordion.height },
        footerY: footer.y + scrollY,
      };
    });
    assert.equal(metrics.scrollWidth, 768, '768px: horizontal overflow');
    closeEnough(metrics.height, 1720, 2, '768px document height');
    closeEnough(metrics.footerY, 921, 2, '768px footer boundary');
    closeEnough(metrics.accordion.x, 84, 2, '768px accordion x');
    closeEnough(metrics.accordion.y, 390, 2, '768px accordion y');
    closeEnough(metrics.accordion.width, 600, 2, '768px accordion width');
    closeEnough(metrics.accordion.height, 531, 2, '768px accordion height');
    await context.close();
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
