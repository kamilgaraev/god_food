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
      <nav class="nav-links"><a href="#catalog">Каталог</a></nav>
      <article class="home-promo-card home-promo-card--gift"><h2>Подарки</h2><p>Наборы в крафтовой коробке</p><a class="home-button">Подробнее</a></article>
      <div class="home-cacao__tabs"><button aria-selected="true"><strong>70%</strong><span>Классический</span></button></div>
      <div class="home-cacao__copy"><h3>Классический 70%</h3></div>
      <p class="home-cacao__fact">С вишней и зеленой гречкой</p>
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
    const homeInk = await page.locator('.nav-links a').evaluate((element) => getComputedStyle(element).color);
    assert.equal(homeInk, 'rgb(52, 52, 52)', 'Homepage ink must use the shared neutral charcoal instead of brown');

    const cacaoFact = await page.locator('.home-cacao__fact').evaluate((element) => {
      const style = getComputedStyle(element);
      return {
        color: style.color,
        letterSpacing: style.letterSpacing,
        textTransform: style.textTransform,
      };
    });
    assert.deepEqual(cacaoFact, {
      color: gold,
      letterSpacing: 'normal',
      textTransform: 'uppercase',
    }, 'Cacao flavor note must use compact uppercase gold typography');

    const cacaoProfileTypography = await page.locator('.home-cacao__tabs button span, .home-cacao__copy h3').evaluateAll((elements) => elements.map((element) => {
      const style = getComputedStyle(element);
      return {
        fontFamily: style.fontFamily,
        letterSpacing: style.letterSpacing,
      };
    }));
    assert.deepEqual(cacaoProfileTypography, [
      { fontFamily: 'Montserrat, Arial, sans-serif', letterSpacing: 'normal' },
      { fontFamily: 'Montserrat, Arial, sans-serif', letterSpacing: 'normal' },
    ], 'Cacao profile names must use Montserrat without artificial tracking');

    const cacaoProfileSizes = async () => page.locator('.home-cacao__tabs button span, .home-cacao__copy h3').evaluateAll((elements) => elements.map((element) => parseFloat(getComputedStyle(element).fontSize)));
    const mobileProfileSizes = await cacaoProfileSizes();
    assert(mobileProfileSizes[0] >= 9, `Mobile cacao profile labels must remain legible; received ${mobileProfileSizes[0]}px`);
    assert(mobileProfileSizes[1] >= 40, `Mobile cacao profile title must use the enlarged scale; received ${mobileProfileSizes[1]}px`);

    await page.setViewportSize({ width: 1440, height: 900 });
    const desktopProfileSizes = await cacaoProfileSizes();
    assert(desktopProfileSizes[0] > mobileProfileSizes[0], 'Cacao profile labels must scale up on wider screens');
    assert(desktopProfileSizes[1] > mobileProfileSizes[1], 'Cacao profile title must scale up on wider screens');
    assert(desktopProfileSizes[0] <= 10 && desktopProfileSizes[1] <= 44, 'Cacao profile typography increase must stay restrained');

    const giftCard = await page.locator('.home-promo-card--gift').evaluate((element) => {
      const style = getComputedStyle(element);
      const heading = getComputedStyle(element.querySelector('h2'));
      const copy = getComputedStyle(element.querySelector('p'));
      return {
        background: style.backgroundColor,
        border: style.borderColor,
        heading: heading.color,
        copy: copy.color,
      };
    });
    assert.deepEqual(giftCard, {
      background: 'rgb(241, 230, 213)',
      border: 'rgb(222, 208, 189)',
      heading: 'rgb(52, 52, 52)',
      copy: 'rgb(117, 107, 99)',
    }, 'Gift card must use the established beige and neutral text palette');
  } finally {
    await browser.close();
  }

  console.log('Homepage accent surfaces consistently use the primary button gold');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
