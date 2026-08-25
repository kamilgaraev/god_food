const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = path.resolve(__dirname, '../wp-content/themes/theobroma/style.css');

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [430, 570, 600]) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
      await page.setContent(`
        <main class="recipes-page">
          <section class="recipes-intro">
            <div class="recipe-grid">
              <a class="recipe-card" href="#recipe">
                <h2>Какао классический</h2>
                <span class="recipe-image"></span>
                <p>Простой и вкусный рецепт какао.</p>
              </a>
            </div>
          </section>
        </main>
      `);
      await page.addStyleTag({ path: stylesheet });

      const metrics = await page.locator('.recipe-card').evaluate((card) => {
        const cardRect = card.getBoundingClientRect();
        const insets = (selector) => {
          const rect = card.querySelector(selector).getBoundingClientRect();
          return { left: rect.left - cardRect.left, right: cardRect.right - rect.right };
        };
        return { image: insets('.recipe-image'), copy: insets('p') };
      });

      closeEnough(metrics.image.right, metrics.image.left, 0.5, `${width}px image horizontal inset`);
      closeEnough(metrics.copy.right, metrics.copy.left, 0.5, `${width}px copy horizontal inset`);
      await page.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
