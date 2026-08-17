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

function composite(foreground, background) {
  return {
    red: foreground.red * foreground.alpha + background.red * (1 - foreground.alpha),
    green: foreground.green * foreground.alpha + background.green * (1 - foreground.alpha),
    blue: foreground.blue * foreground.alpha + background.blue * (1 - foreground.alpha),
    alpha: 1,
  };
}

function luminance(color) {
  const channel = (value) => {
    const normalized = value / 255;
    return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * channel(color.red) + 0.7152 * channel(color.green) + 0.0722 * channel(color.blue);
}

function contrast(first, second) {
  const brightest = Math.max(luminance(first), luminance(second));
  const darkest = Math.min(luminance(first), luminance(second));
  return (brightest + 0.05) / (darkest + 0.05);
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
      const imageStyle = getComputedStyle(badge.closest('.home-product-card__image'));
      return {
        color: style.color,
        backgroundColor: style.backgroundColor,
        imageBackgroundColor: imageStyle.backgroundColor,
        fontSize: parseFloat(style.fontSize),
        paddingInline: parseFloat(style.paddingLeft) + parseFloat(style.paddingRight),
      };
    }));

    for (const metric of metrics) {
      const foreground = parseColor(metric.color);
      const badgeBackground = parseColor(metric.backgroundColor);
      const imageBackground = parseColor(metric.imageBackgroundColor);
      const effectiveBackground = composite(badgeBackground, imageBackground);
      assert.ok(
        contrast(foreground, effectiveBackground) >= 4.5,
        `Bestseller badge contrast must be at least 4.5:1; received ${contrast(foreground, effectiveBackground).toFixed(2)}:1`,
      );
      assert.ok(badgeBackground.alpha >= 0.9, 'Bestseller badge must not depend on the product image for contrast');
      assert.ok(metric.fontSize >= 9, `Bestseller badge text must be at least 9px; received ${metric.fontSize}px`);
      assert.ok(metric.paddingInline >= 12, 'Bestseller badge must have a visible padded background');
    }
  } finally {
    await browser.close();
  }

  console.log('Homepage bestseller badge stays readable on light and dark product images');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
