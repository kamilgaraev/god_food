const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  390: { height: 3451, titleY: 192.90625, image: { x: 20, y: 356, width: 350, height: 269.21875 }, footerY: 2136.75 },
  430: { height: 3745, titleY: 213.40625, image: { x: 20, y: 393, width: 390, height: 300 }, footerY: 2296.875 },
  768: { height: 3627, titleY: 226, image: { x: 84, y: 390, width: 600, height: 461.515625 }, footerY: 2828 },
};

const expectedImageRatios = [1080 / 1350, 1080 / 1350, 1080 / 1350, 900 / 600];

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
      await page.goto('http://localhost:8080/media/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const title = document.querySelector('.media-page > h1').getBoundingClientRect();
        const image = document.querySelector('.media-card-image').getBoundingClientRect();
        const images = [...document.querySelectorAll('.media-card-image img')].map((item) => ({
          currentSrc: item.currentSrc,
          naturalRatio: item.naturalWidth / item.naturalHeight,
        }));
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        return {
          height: document.documentElement.scrollHeight,
          scrollWidth: document.documentElement.scrollWidth,
          titleY: title.y + scrollY,
          image: { x: image.x, y: image.y + scrollY, width: image.width, height: image.height },
          images,
          footerY: footer.y + scrollY,
        };
      });
      assert.equal(metrics.scrollWidth, width, `${width}px: horizontal overflow`);
      closeEnough(metrics.height, expected.height, 2, `${width}px document height`);
      closeEnough(metrics.titleY, expected.titleY, 2, `${width}px title position`);
      closeEnough(metrics.image.x, expected.image.x, 2, `${width}px card x`);
      closeEnough(metrics.image.y, expected.image.y, 2, `${width}px card y`);
      closeEnough(metrics.image.width, expected.image.width, 2, `${width}px card width`);
      closeEnough(metrics.image.height, expected.image.height, width === 768 ? 0.1 : 2, `${width}px card height`);
      closeEnough(metrics.footerY, expected.footerY, 2, `${width}px footer boundary`);
      assert.equal(metrics.images.length, expectedImageRatios.length, `${width}px media image count`);
      metrics.images.forEach((item, index) => {
        closeEnough(item.naturalRatio, expectedImageRatios[index], 0.001, `${width}px image ${index + 1} source aspect ratio`);
        assert.ok(!/-480x360\.(?:jpe?g|webp)(?:\?|$)/i.test(item.currentSrc), `${width}px image ${index + 1} must not use the hard-cropped media thumbnail`);
      });
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
