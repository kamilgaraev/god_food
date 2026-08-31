const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.join(__dirname, '../wp-content/themes/theobroma/style.css'),
  'utf8',
);

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of [390, 800, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 700 } });
      await page.setContent(`
        <style>${stylesheet}</style>
        <body class="logged-in woocommerce-account">
          <main class="shop-page"><div class="shop-shell"><div class="woocommerce">
            <div class="woocommerce-MyAccount-content">
              <section class="theobroma-address-book">
                <p class="theobroma-address-book__lead">Сохраните адрес, куда нужно доставлять ваши заказы.</p>
                <article class="theobroma-address-card">
                  <header><div><small>Основной адрес</small><h2>Адрес доставки</h2></div><a class="button" href="#">Добавить адрес доставки</a></header>
                  <p class="theobroma-address-card__empty">Адрес пока не указан.</p>
                </article>
              </section>
            </div>
          </div></div></main>
        </body>
      `);

      const metrics = await page.evaluate(() => {
        const content = document.querySelector('.woocommerce-MyAccount-content').getBoundingClientRect();
        const book = document.querySelector('.theobroma-address-book').getBoundingClientRect();
        const card = document.querySelector('.theobroma-address-card').getBoundingClientRect();
        const action = document.querySelector('.theobroma-address-card .button').getBoundingClientRect();
        const cardStyle = getComputedStyle(document.querySelector('.theobroma-address-card'));
        const actionStyle = getComputedStyle(document.querySelector('.theobroma-address-card .button'));
        return {
          contentWidth: content.width,
          bookWidth: book.width,
          cardWidth: card.width,
          cardContentWidth: card.width
            - parseFloat(cardStyle.paddingLeft)
            - parseFloat(cardStyle.paddingRight)
            - parseFloat(cardStyle.borderLeftWidth)
            - parseFloat(cardStyle.borderRightWidth),
          cardBorderWidth: cardStyle.borderTopWidth,
          cardBackground: cardStyle.backgroundColor,
          actionWidth: action.width,
          actionHeight: action.height,
          actionDisplay: actionStyle.display,
          actionAlignItems: actionStyle.alignItems,
          actionJustifyContent: actionStyle.justifyContent,
          actionBackground: actionStyle.backgroundColor,
          overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
      });

      assert.ok(metrics.bookWidth <= metrics.contentWidth + 0.5, `${width}px: address book must fit the account panel`);
      assert.ok(Math.abs(metrics.cardWidth - metrics.bookWidth) <= 0.5, `${width}px: delivery card must use the available width`);
      assert.equal(metrics.cardBorderWidth, '1px', `${width}px: delivery card must have a crisp boundary`);
      assert.equal(metrics.cardBackground, 'rgb(252, 249, 247)', `${width}px: delivery card must use the light account surface`);
      assert.ok(metrics.actionHeight >= 40, `${width}px: address action must have at least a 40px hit area`);
      assert.ok(['flex', 'inline-flex'].includes(metrics.actionDisplay), `${width}px: address action must use flexbox`);
      assert.equal(metrics.actionAlignItems, 'center', `${width}px: address action text must be vertically centered`);
      assert.equal(metrics.actionJustifyContent, 'center', `${width}px: address action text must be horizontally centered`);
      assert.equal(metrics.actionBackground, 'rgb(176, 144, 61)', `${width}px: address action must use the brand color`);
      if (width <= 600) {
        assert.ok(Math.abs(metrics.actionWidth - metrics.cardContentWidth) <= 1, `${width}px: mobile action must span the card content width (content ${metrics.cardContentWidth}px, action ${metrics.actionWidth}px)`);
      }
      assert.ok(metrics.overflow <= 1, `${width}px: address page must not overflow horizontally`);

      await page.close();
    }

    console.log('Account delivery-address layout verified across responsive widths.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
