const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  390: {
    classic: {},
    marshmallow: {},
    banana: {},
  },
  430: {
    classic: {},
    marshmallow: {},
    banana: {},
  },
  768: {
    classic: {},
    marshmallow: {},
    banana: {},
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
            method: rect(document.querySelector('.recipe-method')),
            productImages: [...document.querySelectorAll('.recipe-product-grid img')].map(rect),
            imagesLoaded: images.every((image) => image.complete && image.naturalWidth > 0 && image.getBoundingClientRect().height > 0),
          };
        });

        assert.equal(metrics.scrollWidth, width, `${width}px ${slug}: horizontal overflow`);
        assert.equal(metrics.imagesLoaded, true, `${width}px ${slug}: recipe images are not loaded and visible`);
        if (expected.height !== undefined) closeEnough(metrics.height, expected.height, 2, `${width}px ${slug} document height`);
        if (expected.headingY !== undefined) closeEnough(metrics.headingY, expected.headingY, 2, `${width}px ${slug} product heading`);
        if (expected.contactY !== undefined) closeEnough(metrics.contactY, expected.contactY, 2, `${width}px ${slug} contact boundary`);
        assert.equal(metrics.productHeadingVisibility, width <= 430 ? 'hidden' : 'visible', `${width}px ${slug}: product heading visibility`);
        assert.equal(metrics.contactHeadingVisibility, width <= 430 ? 'hidden' : 'visible', `${width}px ${slug}: contact heading visibility`);
        assert.ok(metrics.methodImage.x >= metrics.method.x - 1, `${width}px ${slug}: method image starts inside its card`);
        assert.ok(metrics.methodImage.x + metrics.methodImage.width <= metrics.method.x + metrics.method.width + 1, `${width}px ${slug}: method image ends inside its card`);
        assert.ok(metrics.methodImage.y >= metrics.method.y - 1, `${width}px ${slug}: method image starts inside its card vertically`);
        assert.ok(metrics.methodImage.y + metrics.methodImage.height <= metrics.method.y + metrics.method.height + 1, `${width}px ${slug}: method image ends inside its card vertically`);
        assert.ok(metrics.headingY > metrics.method.y + metrics.method.height, `${width}px ${slug}: product section follows the method card`);
        assert.ok(metrics.contactY > metrics.headingY, `${width}px ${slug}: contact section follows the product section`);
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
