const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  390: {
    classic: { height: 4664, headingY: 1419.5625, contactY: 2817, methodImage: { x: 42.65625, y: 1149.8125, width: 304.75, height: 173.09375 }, productImageYs: [1529.265625, 1915.6875, 2302.125], productImage: { x: 18.28125, width: 353.5, height: 270.609375 } },
    marshmallow: { height: 5223, headingY: 1978.5625, contactY: 3376, methodImage: { x: 42.65625, y: 1708.125, width: 304.75, height: 173.09375 } },
    banana: { height: 5104, headingY: 1859.5625, contactY: 3257, methodImage: { x: 42.65625, y: 1589.875, width: 304.75, height: 173.09375 } },
  },
  430: {
    classic: { height: 5143, headingY: 1566.3125, contactY: 3107 },
    marshmallow: { height: 5759, headingY: 2182.3125, contactY: 3723 },
    banana: { height: 5628, headingY: 2051.3125, contactY: 3592 },
  },
  768: {
    classic: { height: 3751, headingY: 1473, contactY: 2533, methodY: 707, methodHeight: 686 },
    marshmallow: { height: 4228, headingY: 1950, contactY: 3010, methodY: 805, methodHeight: 1065 },
    banana: { height: 4095, headingY: 1817, contactY: 2877, methodY: 756, methodHeight: 981 },
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
        const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : (width === 430 ? 932 : 1024) }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        await page.goto(`http://localhost:8080/recipe/${slug}/`, { waitUntil: 'networkidle' });
        await page.evaluate(async () => document.fonts?.ready);

        const metrics = await page.evaluate(() => {
          const heading = document.querySelector('.recipe-product-promo > h2').getBoundingClientRect();
          const method = document.querySelector('.recipe-method').getBoundingClientRect();
          const contact = document.querySelector('section.contact').getBoundingClientRect();
          const images = [...document.querySelectorAll('.recipe-method > img,.recipe-product-grid img')];
          const rect = (element) => {
            const box = element.getBoundingClientRect();
            return { x: box.x, y: box.y + scrollY, width: box.width, height: box.height };
          };
          const productHeading = document.querySelector('.recipe-product-promo > h2');
          const contactHeading = document.querySelector('.contact-card h2');
          return {
            height: document.documentElement.scrollHeight,
            scrollWidth: document.documentElement.scrollWidth,
            headingY: heading.y + scrollY,
            methodY: method.y + scrollY,
            methodHeight: method.height,
            contactY: contact.y + scrollY,
            productHeadingVisibility: getComputedStyle(productHeading).visibility,
            contactHeadingVisibility: getComputedStyle(contactHeading).visibility,
            methodImage: rect(document.querySelector('.recipe-method > img')),
            productImages: [...document.querySelectorAll('.recipe-product-grid img')].map(rect),
            imagesLoaded: images.every((image) => image.complete && image.naturalWidth > 0 && image.getBoundingClientRect().height > 0),
          };
        });

        assert.equal(metrics.scrollWidth, width, `${width}px ${slug}: horizontal overflow`);
        assert.equal(metrics.imagesLoaded, true, `${width}px ${slug}: recipe images are not loaded and visible`);
        closeEnough(metrics.height, expected.height, 2, `${width}px ${slug} document height`);
        closeEnough(metrics.headingY, expected.headingY, 2, `${width}px ${slug} product heading`);
        closeEnough(metrics.contactY, expected.contactY, 2, `${width}px ${slug} contact boundary`);
        assert.equal(metrics.productHeadingVisibility, width <= 430 ? 'hidden' : 'visible', `${width}px ${slug}: product heading visibility`);
        assert.equal(metrics.contactHeadingVisibility, width <= 430 ? 'hidden' : 'visible', `${width}px ${slug}: contact heading visibility`);
        if (expected.methodY !== undefined) {
          closeEnough(metrics.methodY, expected.methodY, 2, `${width}px ${slug} method position`);
          closeEnough(metrics.methodHeight, expected.methodHeight, 2, `${width}px ${slug} method height`);
        }
        if (expected.methodImage) {
          for (const [metric, target] of Object.entries(expected.methodImage)) closeEnough(metrics.methodImage[metric], target, 1, `${width}px ${slug} method image ${metric}`);
          if (expected.productImage) {
            metrics.productImages.forEach((image, index) => {
              closeEnough(image.x, expected.productImage.x, 1, `${width}px ${slug} product image ${index + 1} x`);
              closeEnough(image.y, expected.productImageYs[index], 1, `${width}px ${slug} product image ${index + 1} y`);
              closeEnough(image.width, expected.productImage.width, 1, `${width}px ${slug} product image ${index + 1} width`);
              closeEnough(image.height, expected.productImage.height, 1, `${width}px ${slug} product image ${index + 1} height`);
            });
          }
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
