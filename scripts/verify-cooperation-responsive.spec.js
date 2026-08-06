const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  390: { height: 3721, footerY: 2407, form: { x: 18.28125, y: 395.25, width: 353.5, height: 477.84375 } },
  430: { height: 4102, footerY: 2654, form: { x: 20.15625, y: 436.5, width: 389.75, height: 526.84375 } },
  768: {
    height: 2997,
    footerY: 2198,
    form: { x: 84, y: 453, width: 600, height: 397 },
    chocolate: { x: 258, y: 890, width: 253, height: 240 },
    benefitsY: 1168,
    cards: [
      { x: 84, y: 1330, width: 600, height: 224 },
      { x: 84, y: 1574, width: 600, height: 198 },
      { x: 84, y: 1792, width: 600, height: 198 },
      { x: 84, y: 2010, width: 600, height: 180 },
    ],
  },
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
      await page.goto('http://localhost:8080/cooperation/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const form = document.querySelector('.cooperation-form').getBoundingClientRect();
        const chocolate = document.querySelector('.cooperation-chocolate').getBoundingClientRect();
        const benefits = document.querySelector('.cooperation-benefits').getBoundingClientRect();
        const cards = [...document.querySelectorAll('.cooperation-benefit-grid article')].map((card) => {
          const rect = card.getBoundingClientRect();
          return { x: rect.x, y: rect.y + scrollY, width: rect.width, height: rect.height };
        });
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        return {
          height: document.documentElement.scrollHeight,
          scrollWidth: document.documentElement.scrollWidth,
          form: { x: form.x, y: form.y + scrollY, width: form.width, height: form.height },
          chocolate: { x: chocolate.x, y: chocolate.y + scrollY, width: chocolate.width, height: chocolate.height },
          benefitsY: benefits.y + scrollY,
          cards,
          footerY: footer.y + scrollY,
        };
      });
      assert.equal(metrics.scrollWidth, width, `${width}px: horizontal overflow`);
      closeEnough(metrics.height, expected.height, 2, `${width}px document height`);
      closeEnough(metrics.footerY, expected.footerY, 2, `${width}px footer boundary`);
      closeEnough(metrics.form.x, expected.form.x, 2, `${width}px form x`);
      closeEnough(metrics.form.y, expected.form.y, 2, `${width}px form y`);
      closeEnough(metrics.form.width, expected.form.width, 2, `${width}px form width`);
      closeEnough(metrics.form.height, expected.form.height, 2, `${width}px form height`);
      if (expected.chocolate) {
        for (const property of ['x', 'y', 'width', 'height']) {
          closeEnough(metrics.chocolate[property], expected.chocolate[property], 2, `${width}px chocolate ${property}`);
        }
        closeEnough(metrics.benefitsY, expected.benefitsY, 2, `${width}px benefits boundary`);
        assert.equal(metrics.cards.length, expected.cards.length, `${width}px benefits card count`);
        metrics.cards.forEach((card, index) => {
          for (const property of ['x', 'y', 'width', 'height']) {
            closeEnough(card[property], expected.cards[index][property], 2, `${width}px card ${index + 1} ${property}`);
          }
        });
      }
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
