const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const template = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/template-parts/home/product-card.php'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/assets/css/home-redesign.css'), 'utf8');

const headingPosition = template.indexOf('class="home-product-card__heading"');
const descriptionPosition = template.indexOf('<p><?php echo esc_html(wp_strip_all_tags($product->get_short_description())); ?></p>');
const pricePosition = template.indexOf('class="home-product-card__price"');
const buttonPosition = template.indexOf('<?php echo $button_html;');

assert.ok(headingPosition >= 0 && descriptionPosition >= 0 && pricePosition >= 0 && buttonPosition >= 0, 'Canonical card elements must exist.');
assert.ok(headingPosition < descriptionPosition, 'Product title must precede the description.');
assert.ok(descriptionPosition < pricePosition, 'Product price must follow the description.');
assert.ok(pricePosition < buttonPosition, 'Product price must sit immediately before the action.');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of [390, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
      try {
        await page.setContent(`
          <article class="home-product-card">
            <a class="home-product-card__image"><img alt="" /></a>
            <div class="home-product-card__heading"><h3><a>Горький шоколад на кокосовом сахаре с кориандром</a></h3></div>
            <p>На кокосовом сахаре с кориандром</p>
            <div class="home-product-card__purchase">
              <span class="home-product-card__price">772₽</span>
              <a class="home-product-card__button">В корзину</a>
            </div>
          </article>
        `);
        await page.addStyleTag({ content: styles });

        const metrics = await page.locator('.home-product-card').evaluate((card) => {
          const bounds = (selector) => {
            const rect = card.querySelector(selector).getBoundingClientRect();
            return { top: rect.top, bottom: rect.bottom };
          };
          const priceStyle = getComputedStyle(card.querySelector('.home-product-card__price'));
          return {
            description: bounds(':scope > p'),
            price: bounds('.home-product-card__price'),
            button: bounds('.home-product-card__button'),
            priceFontSize: Number.parseFloat(priceStyle.fontSize),
            priceFontWeight: Number.parseInt(priceStyle.fontWeight, 10),
            priceLineHeight: Number.parseFloat(priceStyle.lineHeight),
          };
        });

        assert.ok(metrics.price.top >= metrics.description.bottom, `${width}px price must follow the description.`);
        assert.ok(metrics.button.top >= metrics.price.bottom, `${width}px price must precede the action.`);
        assert.ok(metrics.priceFontSize >= 16, `${width}px price must be readable at a glance.`);
        assert.ok(metrics.priceFontWeight >= 500, `${width}px price must carry enough visual weight.`);
        assert.ok(metrics.priceLineHeight >= metrics.priceFontSize, `${width}px price line height must not clip glyphs.`);
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }

  console.log('Product card price hierarchy verified.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
