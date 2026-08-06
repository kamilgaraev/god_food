const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  policy: {
    390: { height: 6094, footerY: 4779.609375, content: { x: 20, y: 288, width: 350, height: 4485.609375 } },
    430: { height: 5770, footerY: 4322.484375, content: { x: 20, y: 318, width: 390, height: 3998.484375 } },
    768: { height: 4913, footerY: 4114, content: { x: 84, y: 364, width: 600, height: 3744.328125 } },
  },
  agreement: {
    390: { height: 6905, footerY: 5591.484375, content: { x: 20, y: 288, width: 350, height: 5297.484375 } },
    430: { height: 6501, footerY: 5053.171875, content: { x: 20, y: 318, width: 390, height: 4729.171875 } },
    768: { height: 5632, footerY: 4833, content: { x: 84, y: 364, width: 600, height: 4463.4375 } },
  },
  oferta: {
    390: { height: 6738, footerY: 5424.40625, content: { x: 20, y: 263, width: 350, height: 5155.40625 } },
    430: { height: 6411, footerY: 4963.28125, content: { x: 20, y: 289, width: 390, height: 4668.28125 } },
    768: { height: 5613, footerY: 4814, content: { x: 84, y: 320, width: 600, height: 4488.234375 } },
  },
};

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const [slug, widths] of Object.entries(cases)) {
      for (const [widthKey, expected] of Object.entries(widths)) {
        const width = Number(widthKey);
        const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : (width === 430 ? 932 : 1024) }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        await page.goto(`http://localhost:8080/${slug}/`, { waitUntil: 'networkidle' });
        await page.evaluate(async () => document.fonts?.ready);
        const metrics = await page.evaluate(() => {
          const content = document.querySelector('.legal-content').getBoundingClientRect();
          const footer = document.querySelector('.site-footer').getBoundingClientRect();
          return {
            height: document.documentElement.scrollHeight,
            scrollWidth: document.documentElement.scrollWidth,
            footerY: footer.y + scrollY,
            content: {
              x: content.x,
              y: content.y + scrollY,
              width: content.width,
              height: content.height,
            },
          };
        });
        assert.equal(metrics.scrollWidth, width, `${slug} ${width}px: horizontal overflow`);
        closeEnough(metrics.height, expected.height, 2, `${slug} ${width}px document height`);
        closeEnough(metrics.footerY, expected.footerY, 2, `${slug} ${width}px footer boundary`);
        for (const dimension of ['x', 'y', 'width', 'height']) {
          closeEnough(metrics.content[dimension], expected.content[dimension], 2, `${slug} ${width}px content ${dimension}`);
        }
        await context.close();
      }
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
