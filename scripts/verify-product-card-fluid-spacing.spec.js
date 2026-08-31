const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const legacyStyles = fs.readFileSync(path.resolve(__dirname, '../wp-content/themes/theobroma/style.css'), 'utf8');
const sourceStyles = fs.readFileSync(path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css'), 'utf8');

const cardMarkup = (title, description, tag = 'article') => `
  <${tag} class="product home-product-card">
    <a class="home-product-card__image" href="#"><img alt="" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='318'/%3E"></a>
    <div class="home-product-card__heading"><h3><a href="#">${title}</a></h3></div>
    <p>${description}</p>
    <div class="home-product-card__purchase">
      <span class="home-product-card__price">500р.</span>
      <a class="home-product-card__button" href="#">В корзину</a>
    </div>
  </${tag}>`;

const cardsMarkup = (tag) => `
  ${cardMarkup('Какао', 'Без сахара', tag)}
  ${cardMarkup('Молочный шоколад с малиной и цельным фундуком 100г', 'Нежный шоколад с натуральной сублимированной малиной, кусочками цельного фундука и ароматными какао-бобами ручной обжарки', tag)}`;

const contexts = [
  {
    name: 'homepage',
    selector: '#catalog .home-product-grid > .home-product-card',
    markup: `<section id="catalog" class="home-catalog"><div class="products home-product-grid">${cardsMarkup()}</div></section>`,
  },
  {
    name: 'catalog',
    selector: 'ul.products.home-product-grid > .home-product-card',
    markup: `<main class="catalog-page"><ul class="products home-product-grid">${cardsMarkup('li')}</ul></main>`,
  },
  {
    name: 'related products',
    selector: '.product-related-grid.home-product-grid > .home-product-card',
    markup: `<section class="product-related"><div class="product-related-grid home-product-grid">${cardsMarkup()}</div></section>`,
  },
  {
    name: 'corporate gifts',
    selector: '.corporate-gifts-showcase-grid.home-product-grid > .home-product-card',
    markup: `<section class="corporate-gifts-showcase"><div class="corporate-gifts-showcase-grid home-product-grid">${cardsMarkup()}</div></section>`,
  },
  {
    name: 'media article',
    selector: '.media-article-products-grid.home-product-grid > .home-product-card',
    markup: `<main class="media-article"><div class="media-article-products-grid home-product-grid">${cardsMarkup()}</div></main>`,
  },
  {
    name: 'recipe',
    selector: '.recipe-product-grid.home-product-grid > .home-product-card',
    markup: `<section class="recipe-product-promo"><div class="recipe-product-grid home-product-grid">${cardsMarkup()}</div></section>`,
  },
  {
    name: 'commerce modal recommendations',
    selector: '.commerce-modal-product .product-related-grid.home-product-grid > .home-product-card',
    markup: `<section class="commerce-modal-product"><div class="product-related-grid home-product-grid">${cardsMarkup()}</div></section>`,
  },
];

function assertClose(actual, expected, message, tolerance = 1) {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${message} (${actual} vs ${expected}).`);
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of [390, 768, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
      try {
        for (const context of contexts) {
          await page.setContent(`<style>${legacyStyles}</style><style>${sourceStyles}</style>${context.markup}`);

          const cards = page.locator(context.selector);
          const label = `${width}px ${context.name}`;
          await assert.doesNotReject(() => cards.nth(1).waitFor(), `${label} must render at least two cards.`);

        const metrics = await cards.evaluateAll((items) => items.slice(0, 2).map((card) => {
          const bounds = (selector) => {
            const rect = card.querySelector(selector).getBoundingClientRect();
            return { top: rect.top, bottom: rect.bottom, height: rect.height };
          };
          const title = card.querySelector('h3');
          const description = card.querySelector(':scope > p');
          const titleStyle = getComputedStyle(title);
          const descriptionStyle = getComputedStyle(description);

          return {
            heading: bounds('.home-product-card__heading'),
            title: bounds('h3'),
            description: bounds(':scope > p'),
            price: bounds('.home-product-card__price'),
            button: bounds('.home-product-card__button'),
            titleLineHeight: Number.parseFloat(titleStyle.lineHeight),
            descriptionLineHeight: Number.parseFloat(descriptionStyle.lineHeight),
          };
        }));

          assertClose(metrics[0].title.height, metrics[0].titleLineHeight, `${label} one-line title must use exactly one text line`);
          assert.ok(metrics[1].title.height >= metrics[1].titleLineHeight * 1.9, `${label} longer title must grow to at least two lines.`);
          assertClose(metrics[0].description.height, metrics[0].descriptionLineHeight, `${label} one-line description must use exactly one text line`);
          assert.ok(metrics[1].description.height >= metrics[1].descriptionLineHeight * 1.9, `${label} longer description must grow to at least two lines.`);

        metrics.forEach((card, index) => {
          const descriptionGap = card.description.top - card.heading.bottom;
          const purchaseGap = card.price.top - card.description.bottom;
            assert.ok(descriptionGap >= 0 && descriptionGap <= 12, `${label} card ${index + 1} description must follow its title without a reserved row (${descriptionGap}px).`);
            assert.ok(purchaseGap >= 0, `${label} card ${index + 1} purchase block must not overlap its description (${purchaseGap}px).`);
        });

          if (width > 600) {
            assertClose(metrics[0].price.top, metrics[1].price.top, `${label} prices must align across the row`, 0.5);
            assertClose(metrics[0].button.top, metrics[1].button.top, `${label} buttons must align across the row`, 0.5);
          }
        }
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }

  console.log('Responsive product card fluid spacing verified.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
