const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  390: { height: 3098, contactY: 1251, footerY: 1784, image: { x: 18.28125, y: 356.25, width: 170.65625, height: 207.21875 } },
  430: { height: 3416, contactY: 1380, footerY: 1968, image: { x: 20.15625, y: 393.5, width: 188.15625, height: 228.46875 } },
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
      await page.goto('http://localhost:8080/marketplace/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const image = document.querySelector('.market-image').getBoundingClientRect();
        const contact = document.querySelector('section.contact').getBoundingClientRect();
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        return {
          height: document.documentElement.scrollHeight,
          scrollWidth: document.documentElement.scrollWidth,
          image: { x: image.x, y: image.y + scrollY, width: image.width, height: image.height },
          contactY: contact.y + scrollY,
          footerY: footer.y + scrollY,
        };
      });
      assert.equal(metrics.scrollWidth, width, `${width}px: horizontal overflow`);
      closeEnough(metrics.height, expected.height, 2, `${width}px document height`);
      closeEnough(metrics.contactY, expected.contactY, 2, `${width}px contact boundary`);
      closeEnough(metrics.footerY, expected.footerY, 2, `${width}px footer boundary`);
      closeEnough(metrics.image.x, expected.image.x, 2, `${width}px image x`);
      closeEnough(metrics.image.y, expected.image.y, 2, `${width}px image y`);
      closeEnough(metrics.image.width, expected.image.width, 2, `${width}px image width`);
      closeEnough(metrics.image.height, expected.image.height, 2, `${width}px image height`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
