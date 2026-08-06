const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  390: { height: 3098, contactY: 1251, footerY: 1784, image: { x: 18.28125, y: 356.25, width: 170.65625, height: 207.21875 } },
  430: { height: 3416, contactY: 1380, footerY: 1968, image: { x: 20.15625, y: 393.5, width: 188.15625, height: 228.46875 } },
  768: {
    height: 2593,
    contactY: 1380,
    footerY: 1794,
    images: [
      { x: 84, y: 390, width: 290, height: 310 },
      { x: 394, y: 390, width: 290, height: 310 },
      { x: 84, y: 824, width: 290, height: 310 },
      { x: 394, y: 824, width: 290, height: 310 },
    ],
    buttons: [
      { x: 159, y: 1258, width: 240, height: 42 },
      { x: 409, y: 1258, width: 200, height: 42 },
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
      const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : (width === 430 ? 932 : 1024) }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/marketplace/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const image = document.querySelector('.market-image').getBoundingClientRect();
        const images = [...document.querySelectorAll('.market-image')].map((element) => {
          const rect = element.getBoundingClientRect();
          return { x: rect.x, y: rect.y + scrollY, width: rect.width, height: rect.height };
        });
        const buttons = [...document.querySelectorAll('.market-buttons a')].map((element) => {
          const rect = element.getBoundingClientRect();
          return { x: rect.x, y: rect.y + scrollY, width: rect.width, height: rect.height };
        });
        const contact = document.querySelector('section.contact').getBoundingClientRect();
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        return {
          height: document.documentElement.scrollHeight,
          scrollWidth: document.documentElement.scrollWidth,
          image: { x: image.x, y: image.y + scrollY, width: image.width, height: image.height },
          images,
          buttons,
          contactY: contact.y + scrollY,
          footerY: footer.y + scrollY,
        };
      });
      assert.equal(metrics.scrollWidth, width, `${width}px: horizontal overflow`);
      closeEnough(metrics.height, expected.height, 2, `${width}px document height`);
      closeEnough(metrics.contactY, expected.contactY, 2, `${width}px contact boundary`);
      closeEnough(metrics.footerY, expected.footerY, 2, `${width}px footer boundary`);
      if (expected.image) {
        closeEnough(metrics.image.x, expected.image.x, 2, `${width}px image x`);
        closeEnough(metrics.image.y, expected.image.y, 2, `${width}px image y`);
        closeEnough(metrics.image.width, expected.image.width, 2, `${width}px image width`);
        closeEnough(metrics.image.height, expected.image.height, 2, `${width}px image height`);
      }
      if (expected.images) {
        metrics.images.forEach((imageMetrics, index) => {
          for (const property of ['x', 'y', 'width', 'height']) {
            closeEnough(imageMetrics[property], expected.images[index][property], 2, `${width}px image ${index + 1} ${property}`);
          }
        });
        metrics.buttons.forEach((button, index) => {
          for (const property of ['x', 'y', 'width', 'height']) {
            closeEnough(button[property], expected.buttons[index][property], 2, `${width}px button ${index + 1} ${property}`);
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
