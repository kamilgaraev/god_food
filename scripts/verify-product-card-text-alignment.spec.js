const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = process.env.BASE_URL || 'http://localhost:8080';
const sourceStyles = fs.readFileSync(path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css'), 'utf8');
const injectSourceStyles = process.argv.includes('--inject-source-styles');

function assertAligned(actual, expected, message) {
  assert.ok(Math.abs(actual - expected) <= 0.5, `${message} (${actual} vs ${expected}).`);
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    if (injectSourceStyles) {
      console.log('Product card text alignment: explicit source-styles injection mode.');
    }

    for (const width of [768, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
      try {
        await page.goto(new URL('/catalog/', baseUrl).href, { waitUntil: 'networkidle' });
        if (injectSourceStyles) {
          await page.addStyleTag({ content: sourceStyles });
        }

        const cards = page.locator('ul.products.home-product-grid > .home-product-card');
        await assert.doesNotReject(() => cards.nth(1).waitFor(), `${width}px catalog must render at least two cards.`);
        await cards.nth(0).locator('h3 a').evaluate((title) => { title.textContent = 'Какао'; });
        await cards.nth(1).locator('h3 a').evaluate((title) => { title.textContent = '68% горький шоколад 200г'; });

        const metrics = await cards.evaluateAll((items) => items.slice(0, 2).map((card) => {
          const title = card.querySelector('h3');
          const titleLink = title.querySelector('a');
          const price = card.querySelector('.home-product-card__price');
          const titleStyle = getComputedStyle(title);
          const baseline = (element) => {
            const marker = document.createElement('span');
            marker.style.cssText = 'display:inline-block;width:0;height:0;padding:0;border:0;';
            element.prepend(marker);
            const position = marker.getBoundingClientRect().top;
            marker.remove();
            return position;
          };
          const bounds = (selector) => {
            const rect = card.querySelector(selector).getBoundingClientRect();
            return { top: rect.top, bottom: rect.bottom };
          };
          return {
            heading: bounds('.home-product-card__heading'),
            description: bounds(':scope > p'),
            button: bounds('.home-product-card__button'),
            titleBaseline: baseline(titleLink),
            priceBaseline: baseline(price),
            titleClientHeight: title.clientHeight,
            titleScrollHeight: title.scrollHeight,
            titleOverflowY: titleStyle.overflowY,
            titleLineClamp: titleStyle.webkitLineClamp,
          };
        }));

        assertAligned(metrics[0].heading.top, metrics[1].heading.top, `${width}px title rows must start together`);
        assertAligned(metrics[0].heading.bottom, metrics[1].heading.bottom, `${width}px title rows must reserve a consistent height`);
        assertAligned(metrics[0].description.top, metrics[1].description.top, `${width}px descriptions must start on one reading line`);
        assertAligned(metrics[0].button.bottom, metrics[1].button.bottom, `${width}px actions must share a baseline`);
        metrics.forEach((card, index) => {
          assertAligned(card.titleBaseline, card.priceBaseline, `${width}px card ${index + 1} price must align with the first title baseline`);
          const titleFits = card.titleScrollHeight <= card.titleClientHeight + 1;
          assert.ok(titleFits || card.titleOverflowY === 'visible', `${width}px card ${index + 1} title overflow must remain visible.`);
          assert.equal(card.titleLineClamp, 'none', `${width}px card ${index + 1} title must not be line-clamped.`);
        });
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }

  console.log('Product card text alignment verified.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
