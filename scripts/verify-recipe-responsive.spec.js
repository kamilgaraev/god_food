const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'style.css'),
  'utf8',
);

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

async function assertDesktopIngredientCardSticks(browser) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost:8080/recipe/classic/', { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: stylesheet });
  await page.evaluate(async () => document.fonts?.ready);

  const ingredientCard = page.locator('.recipe-ingredients');
  const styles = await ingredientCard.evaluate((element) => ({
    position: getComputedStyle(element).position,
    top: getComputedStyle(element).top,
    introOverflow: getComputedStyle(element.closest('.recipe-detail-intro')).overflow,
  }));
  await page.evaluate(() => window.scrollTo(0, 500));
  const firstTop = await ingredientCard.evaluate((element) => element.getBoundingClientRect().top);
  await page.evaluate(() => window.scrollTo(0, 550));
  const secondTop = await ingredientCard.evaluate((element) => element.getBoundingClientRect().top);

  assert.ok(firstTop >= 0, `desktop ingredient card must remain visible while scrolling, got top ${firstTop}px with ${JSON.stringify(styles)}`);
  closeEnough(secondTop, firstTop, 1, 'desktop ingredient card must stay fixed while the method column scrolls');

  await page.setViewportSize({ width: 768, height: 1024 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.addStyleTag({ content: stylesheet });
  const tabletPosition = await page.locator('.recipe-ingredients').evaluate((element) => getComputedStyle(element).position);
  assert.equal(tabletPosition, 'static', 'single-column tablet ingredient card must scroll normally');
  await page.close();
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    await assertDesktopIngredientCardSticks(browser);

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
