const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  390: {
    classic: { height: 4664, headingY: 1419.5625, contactY: 2817 },
    marshmallow: { height: 5223, headingY: 1978.5625, contactY: 3376 },
    banana: { height: 5104, headingY: 1859.5625, contactY: 3257 },
  },
  430: {
    classic: { height: 5143, headingY: 1566.3125, contactY: 3107 },
    marshmallow: { height: 5759, headingY: 2182.3125, contactY: 3723 },
    banana: { height: 5628, headingY: 2051.3125, contactY: 3592 },
  },
};

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const [widthKey, recipes] of Object.entries(cases)) {
      const width = Number(widthKey);
      for (const [slug, expected] of Object.entries(recipes)) {
        const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : 932 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        await page.goto(`http://localhost:8080/recipe/${slug}/`, { waitUntil: 'networkidle' });
        await page.evaluate(async () => document.fonts?.ready);

        const metrics = await page.evaluate(() => {
          const heading = document.querySelector('.recipe-product-promo > h2').getBoundingClientRect();
          const contact = document.querySelector('section.contact').getBoundingClientRect();
          const images = [...document.querySelectorAll('.recipe-method > img,.recipe-product-grid img')];
          return {
            height: document.documentElement.scrollHeight,
            scrollWidth: document.documentElement.scrollWidth,
            headingY: heading.y + scrollY,
            contactY: contact.y + scrollY,
            imagesLoaded: images.every((image) => image.complete && image.naturalWidth > 0 && image.getBoundingClientRect().height > 0),
          };
        });

        assert.equal(metrics.scrollWidth, width, `${width}px ${slug}: horizontal overflow`);
        assert.equal(metrics.imagesLoaded, true, `${width}px ${slug}: recipe images are not loaded and visible`);
        closeEnough(metrics.height, expected.height, 2, `${width}px ${slug} document height`);
        closeEnough(metrics.headingY, expected.headingY, 2, `${width}px ${slug} product heading`);
        closeEnough(metrics.contactY, expected.contactY, 2, `${width}px ${slug} contact boundary`);
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
