const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const legacyStyles = fs.readFileSync(path.resolve(__dirname, '../wp-content/themes/theobroma/style.css'), 'utf8');
const sharedStyles = fs.readFileSync(path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css'), 'utf8');
const cardMarkup = `
  <article class="home-product-card" data-product-id="1">
    <a class="home-product-card__image" href="#"><img alt="" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='318'/%3E"></a>
    <div class="home-product-card__heading"><h3><a href="#">Горький шоколад с кориандром</a></h3></div>
    <p>На кокосовом сахаре с кориандром</p>
    <div class="home-product-card__purchase">
      <span class="home-product-card__price">772р.</span>
      <a class="home-product-card__button" href="#">В корзину</a>
    </div>
  </article>`;

async function cardPresentation(page, selector) {
  return page.locator(selector).first().evaluate((card) => {
    const computed = (element, properties) => {
      const styles = getComputedStyle(element);
      return Object.fromEntries(properties.map((property) => [property, styles[property]]));
    };
    const title = card.querySelector('h3');

    return {
      cardClass: card.className,
      card: computed(card, ['display', 'flexDirection', 'minHeight', 'padding', 'backgroundColor', 'textAlign']),
      image: computed(card.querySelector('.home-product-card__image'), ['aspectRatio', 'borderRadius']),
      title: computed(title, ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'textTransform', 'margin', 'padding']),
      description: computed(card.querySelector(':scope > p'), ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'margin']),
      price: computed(card.querySelector('.home-product-card__price'), ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'color']),
      button: computed(card.querySelector('.home-product-card__button'), ['display', 'minHeight', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'borderRadius']),
      documentWidth: document.documentElement.scrollWidth,
      viewportWidth: document.documentElement.clientWidth,
    };
  });
}

function comparable(presentation) {
  const { cardClass, documentWidth, viewportWidth, ...styles } = presentation;
  return styles;
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of [390, 768, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
      try {
        await page.setContent(`
          <style>${legacyStyles}</style>
          <style>${sharedStyles}</style>
          <section id="catalog" class="home-catalog"><div class="products home-product-grid">${cardMarkup}</div></section>
          <section class="corporate-gifts-showcase"><div class="corporate-gifts-showcase-grid home-product-grid">${cardMarkup}</div></section>
        `);
        const home = await cardPresentation(page, '#catalog .home-product-card');
        const corporate = await cardPresentation(page, '.corporate-gifts-showcase-grid.home-product-grid > .home-product-card');
        const context = `${width}px corporate product card`;

        assert.match(corporate.cardClass, /\bhome-product-card\b/, `${context} must use the shared homepage component.`);
        assert.deepEqual(comparable(corporate), comparable(home), `${context} presentation must match the homepage reference.`);
        assert.equal(corporate.title.textTransform, 'none', `${context} name must preserve its original letter case.`);
        assert.ok(corporate.documentWidth <= corporate.viewportWidth, `${context} must not create horizontal overflow.`);
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }

  console.log('Corporate product cards match the homepage reference.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
