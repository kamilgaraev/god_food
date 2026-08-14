const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = [390, 430, 768];

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of cases) {
      const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : 932 }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/cooperation/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const form = document.querySelector('.cooperation-form').getBoundingClientRect();
        const innerForm = document.querySelector('.cooperation-form form').getBoundingClientRect();
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
          innerForm: { x: innerForm.x, right: innerForm.right, width: innerForm.width },
          chocolate: { x: chocolate.x, y: chocolate.y + scrollY, width: chocolate.width, height: chocolate.height },
          benefitsY: benefits.y + scrollY,
          cards,
          footerY: footer.y + scrollY,
        };
      });
      assert.equal(metrics.scrollWidth, width, `${width}px: horizontal overflow`);
      closeEnough(metrics.form.x, width - metrics.form.x - metrics.form.width, 1, `${width}px form centering`);
      closeEnough(metrics.innerForm.x - metrics.form.x, metrics.form.x + metrics.form.width - metrics.innerForm.right, 1, `${width}px form inner insets`);
      assert.equal(metrics.cards.length, 4, `${width}px benefits card count`);
      metrics.cards.forEach((card, index) => {
        assert.ok(card.x >= -1 && card.x + card.width <= width + 1, `${width}px card ${index + 1} stays in viewport`);
        if (index > 0) assert.ok(card.y >= metrics.cards[index - 1].y + metrics.cards[index - 1].height, `${width}px card ${index + 1} follows the previous card`);
      });
      assert.ok(metrics.chocolate.y + metrics.chocolate.height <= metrics.benefitsY + 1, `${width}px chocolate precedes benefits`);
      assert.ok(metrics.footerY > metrics.benefitsY, `${width}px footer follows benefits`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
