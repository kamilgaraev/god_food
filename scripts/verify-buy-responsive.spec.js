const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  390: { height: 2693, contactY: 846, footerY: 1379, card: { x: 18.28125, y: 423.984375, width: 353.5, height: 349.84375 } },
  430: { height: 2970, contactY: 934, footerY: 1522, card: { x: 20.15625, y: 468.109375, width: 389.75, height: 385.71875 } },
};

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const [widthKey, expected] of Object.entries(cases)) {
      const width = Number(widthKey);
      const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : 932 }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/buy/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const card = document.querySelector('.buy-location').getBoundingClientRect();
        const contact = document.querySelector('section.contact').getBoundingClientRect();
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        return {
          height: document.documentElement.scrollHeight,
          scrollWidth: document.documentElement.scrollWidth,
          card: { x: card.x, y: card.y + scrollY, width: card.width, height: card.height },
          contactY: contact.y + scrollY,
          footerY: footer.y + scrollY,
        };
      });
      assert.equal(metrics.scrollWidth, width, `${width}px: horizontal overflow`);
      closeEnough(metrics.height, expected.height, 2, `${width}px document height`);
      closeEnough(metrics.contactY, expected.contactY, 2, `${width}px contact boundary`);
      closeEnough(metrics.footerY, expected.footerY, 2, `${width}px footer boundary`);
      closeEnough(metrics.card.x, expected.card.x, 2, `${width}px card x`);
      closeEnough(metrics.card.y, expected.card.y, 2, `${width}px card y`);
      closeEnough(metrics.card.width, expected.card.width, 2, `${width}px card width`);
      closeEnough(metrics.card.height, expected.card.height, 2, `${width}px card height`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
