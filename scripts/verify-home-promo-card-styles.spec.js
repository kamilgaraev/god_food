const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css'),
  'utf8',
);

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(`
      <section class="home-promo-grid">
        <article class="home-promo-card home-promo-card--gift"></article>
        <article class="home-promo-card home-promo-card--where"></article>
      </section>
    `);
    await page.addStyleTag({ content: stylesheet });

    const cardStyles = await page.locator('.home-promo-card').evaluateAll((cards) =>
      cards.map((card) => {
        const style = getComputedStyle(card);
        return {
          borderTopWidth: style.borderTopWidth,
          borderRadius: style.borderRadius,
          backgroundColor: style.backgroundColor,
        };
      }),
    );

    assert.deepEqual(cardStyles, [
      {
        borderTopWidth: '0px',
        borderRadius: '0px',
        backgroundColor: 'rgb(241, 230, 213)',
      },
      {
        borderTopWidth: '0px',
        borderRadius: '0px',
        backgroundColor: 'rgb(255, 255, 255)',
      },
    ]);
  } finally {
    await browser.close();
  }

  console.log('Homepage promo cards are square, borderless, and use the requested backgrounds');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
