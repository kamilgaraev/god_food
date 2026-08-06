const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const expectedByWidth = {
  390: { height: 3341, contactY: 1494, footerY: 2027, card: { x: 18, width: 354, height: 326 } },
  430: { height: 3684, contactY: 1648, footerY: 2236, card: { x: 19.846, width: 390.308, height: 359.436 } },
};

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 430]) {
      const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : 932 }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/recipes/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const grid = document.querySelector('.recipe-grid').getBoundingClientRect();
        const contact = document.querySelector('section.contact').getBoundingClientRect();
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        const cards = [...document.querySelectorAll('.recipe-card')].map((card) => {
          const rect = card.getBoundingClientRect();
          const heading = card.querySelector('h2').getBoundingClientRect();
          return { x: rect.x, width: rect.width, height: rect.height, right: rect.right, headingHeight: heading.height, headingRight: heading.right };
        });
        return {
          height: document.documentElement.scrollHeight,
          grid: { width: grid.width, right: grid.right },
          contactY: contact.y + scrollY,
          footerY: footer.y + scrollY,
          cards,
        };
      });
      const expected = expectedByWidth[width];
      for (const [index, card] of metrics.cards.entries()) {
        assert.ok(card.width <= metrics.grid.width + 0.5, `${width}px card ${index + 1}: ${card.width}px card exceeds ${metrics.grid.width}px grid`);
        assert.ok(card.right <= metrics.grid.right + 0.5, `${width}px card ${index + 1}: card is clipped by the recipe grid`);
        assert.ok(card.headingRight <= metrics.grid.right + 0.5, `${width}px card ${index + 1}: heading is clipped`);
      }
      assert.ok(metrics.cards[2].headingHeight > metrics.cards[0].headingHeight, `${width}px: banana recipe title must wrap instead of being clipped`);
      closeEnough(metrics.height, expected.height, 2, `${width}px document height`);
      closeEnough(metrics.contactY, expected.contactY, 2, `${width}px contact boundary`);
      closeEnough(metrics.footerY, expected.footerY, 2, `${width}px footer boundary`);
      closeEnough(metrics.cards[0].width, expected.card.width, 2, `${width}px card width`);
      closeEnough(metrics.cards[0].height, expected.card.height, 2, `${width}px card height`);
      closeEnough(metrics.cards[0].x, expected.card.x, 2, `${width}px card x`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
