const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeRoot = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma');
const stylesheet = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8');
const siteScript = fs.readFileSync(path.join(themeRoot, 'assets', 'js', 'site-header.js'), 'utf8');

async function verifyCarousel(browser, width) {
  const page = await browser.newPage({
    viewport: { width, height: 900 },
    reducedMotion: 'reduce',
  });
  const reviews = Array.from({ length: 7 }, (_, index) => `<article class="review"><p>Отзыв ${index + 1}</p><strong>Автор</strong></article>`).join('');
  await page.setContent(`
    <style>${stylesheet}</style>
    <section class="reviews">
      <div class="reviews-stage">
        <div class="section-heading">
          <h2>Отзывы о наших продуктах</h2>
          <div class="review-controls">
            <button type="button" data-review-direction="-1">Назад</button>
            <button type="button" data-review-direction="1">Вперёд</button>
          </div>
        </div>
        <div class="review-grid">${reviews}</div>
      </div>
    </section>
  `);
  await page.addScriptTag({ content: siteScript });

  const grid = page.locator('.review-grid');
  const next = page.locator('[data-review-direction="1"]');
  const previous = page.locator('[data-review-direction="-1"]');
  const layout = await grid.evaluate((element) => {
    const cards = [...element.querySelectorAll('.review')];
    return {
      clientHeight: element.clientHeight,
      scrollHeight: element.scrollHeight,
      cardTops: cards.map((card) => card.offsetTop),
    };
  });
  assert.equal(layout.scrollHeight, layout.clientHeight, `${width}px carousel must not have vertical overflow`);
  assert.equal(new Set(layout.cardTops).size, 1, `${width}px carousel must keep every review in one row`);

  const before = await grid.evaluate((element) => element.scrollLeft);
  await next.click();
  const afterNext = await grid.evaluate((element) => ({
    scrollLeft: element.scrollLeft,
    transform: getComputedStyle(element).transform,
  }));
  assert(afterNext.scrollLeft > before, `${width}px next review button must advance the native scroll position`);
  assert.equal(afterNext.transform, 'none', `${width}px carousel must not translate the scroll container itself`);

  await previous.click();
  const afterPrevious = await grid.evaluate((element) => element.scrollLeft);
  assert(afterPrevious < afterNext.scrollLeft, `${width}px previous review button must move the carousel back`);

  for (let step = 1; step <= 8; step += 1) {
    await next.click();
    await page.waitForTimeout(100);
    const nearestReview = await grid.evaluate((element) => {
      const cards = [...element.querySelectorAll('.review')];
      const nearest = cards.reduce((closest, card) => (
        Math.abs(card.offsetLeft - element.scrollLeft) < Math.abs(closest.offsetLeft - element.scrollLeft)
          ? card
          : closest
      ));
      return nearest.querySelector('p')?.textContent?.trim();
    });
    const expectedReview = `Отзыв ${(step % 7) + 1}`;
    assert.equal(nearestReview, expectedReview, `${width}px carousel must loop after the last review`);
  }
  await page.close();
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    await verifyCarousel(browser, 768);
    await verifyCarousel(browser, 390);
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
