const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css'),
  'utf8',
);

function parseColor(value) {
  const channels = value.match(/[\d.]+/g).map(Number);
  return {
    red: channels[0],
    green: channels[1],
    blue: channels[2],
    alpha: channels[3] ?? 1,
  };
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 600 } });
    await page.setContent(`
      <article class="home-product-card case-light">
        <a class="home-product-card__image"><span class="home-product-card__badge">Бестселлер</span></a>
      </article>
      <article class="home-product-card case-dark">
        <a class="home-product-card__image"><span class="home-product-card__badge">Бестселлер</span></a>
      </article>
    `);
    await page.addStyleTag({ content: stylesheet });
    await page.addStyleTag({ content: `
      .home-product-card__image { width: 18rem; }
      .case-light .home-product-card__image { background: #e7d8c8; }
      .case-dark .home-product-card__image { background: #26395c; }
    ` });

    const metrics = await page.locator('.home-product-card__badge').evaluateAll((badges) => badges.map((badge) => {
      const style = getComputedStyle(badge);
      return {
        color: style.color,
        backgroundColor: style.backgroundColor,
        fontSize: parseFloat(style.fontSize),
        paddingInline: parseFloat(style.paddingLeft) + parseFloat(style.paddingRight),
      };
    }));

    for (const metric of metrics) {
      const foreground = parseColor(metric.color);
      const badgeBackground = parseColor(metric.backgroundColor);
      assert.deepEqual(
        [badgeBackground.red, badgeBackground.green, badgeBackground.blue],
        [176, 144, 61],
        'Bestseller badge must use the primary button gold',
      );
      assert.deepEqual(
        [foreground.red, foreground.green, foreground.blue, foreground.alpha],
        [255, 255, 255, 1],
        'Bestseller badge text must be white',
      );
      assert.ok(badgeBackground.alpha >= 0.9, 'Bestseller badge must not depend on the product image for contrast');
      assert.ok(metric.fontSize >= 9, `Bestseller badge text must be at least 9px; received ${metric.fontSize}px`);
      assert.ok(metric.paddingInline >= 12, 'Bestseller badge must have a visible padded background');
    }
  } finally {
    await browser.close();
  }

  console.log('Homepage bestseller badge uses white text on its gold background');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
