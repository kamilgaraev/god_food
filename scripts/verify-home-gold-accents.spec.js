const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css'),
  'utf8',
);
const gold = 'rgb(176, 144, 61)';

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
    await page.setContent(`
      <div class="floating-actions"><a class="header-cart">Корзина</a></div>
      <a class="home-button home-button--secondary">Подробнее</a>
      <span class="home-product-card__badge">Бестселлер</span>
      <a class="home-product-card__button added">В корзине</a>
      <article class="home-promo-card home-promo-card--gift"><h2>Подарки</h2><a class="home-button">Подробнее</a></article>
      <div class="home-cacao__tabs"><button aria-selected="true">70%</button></div>
    `);
    await page.addStyleTag({ content: stylesheet });
    await page.locator('.home-button--secondary').hover();
    await page.waitForTimeout(300);

    const selectors = [
      '.floating-actions .header-cart',
      '.home-button--secondary',
      '.home-product-card__badge',
      '.home-product-card__button.added',
      '.home-cacao__tabs button[aria-selected="true"]',
    ];
    for (const selector of selectors) {
      const background = await page.locator(selector).evaluate((element) => getComputedStyle(element).backgroundColor);
      assert.equal(background, gold, `${selector} must use the primary button gold`);
    }

    const giftButton = await page.locator('.home-promo-card--gift .home-button').evaluate((element) => {
      const style = getComputedStyle(element);
      return { background: style.backgroundColor, color: style.color };
    });
    assert.equal(giftButton.background, gold, 'Gift card action button must use the primary gold');
    assert.equal(giftButton.color, 'rgb(255, 255, 255)', 'Gold action button must keep white button text');
    const giftBackground = await page.locator('.home-promo-card--gift').evaluate((element) => getComputedStyle(element).backgroundColor);
    assert.equal(giftBackground, 'rgb(42, 26, 16)', 'Non-interactive gift card surface must keep its existing color');
  } finally {
    await browser.close();
  }

  console.log('Homepage accent surfaces consistently use the primary button gold');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
