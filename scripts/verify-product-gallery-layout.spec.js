const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const theme = path.resolve(__dirname, '../wp-content/themes/theobroma');
const css = fs.readFileSync(path.join(theme, 'style.css'), 'utf8');
const script = fs.readFileSync(path.join(theme, 'assets/js/commerce-modals.js'), 'utf8');
const start = script.indexOf('    const bindProductGallery = () => {');
assert.ok(start >= 0, 'Gallery initializer must exist');
const end = script.indexOf('\n    };', start);
assert.ok(end > start);
const galleryScript = script.slice(start, end + '\n    };'.length);

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    await page.route('https://gallery.test/**', (route) => route.fulfill({
      contentType: 'image/svg+xml',
      body: '<svg xmlns="http://www.w3.org/2000/svg" width="560" height="745"><rect width="560" height="745" fill="#baaa96"/></svg>',
    }));
    for (const width of [390, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const type of ['simple', 'variable']) {
        for (const count of [1, 4, 9]) {
          const label = `${width}px ${type} ${count} photos`;
          const buttons = Array.from({ length: count }, (_, i) => `<button type="button" data-product-gallery-image="https://gallery.test/${i}.svg"><img src="https://gallery.test/${i}.svg" alt=""></button>`).join('');
          await page.setContent(`<style>${css}</style><div class="commerce-modal-product"><main class="product-detail-page product-detail-type-${type}">
            <section class="product-detail-hero"><div class="product-detail-gallery">
              <figure class="product-detail-image"><button class="product-detail-zoom-trigger" type="button"><img data-product-main-image src="https://gallery.test/0.svg" width="560" height="745"></button></figure>
              ${count > 1 ? `<div class="product-detail-thumbnails">${buttons}</div>` : ''}
            </div><div class="product-detail-summary"><h1>Шоколад</h1><div class="product-detail-copy"><p>Описание вкуса.</p><p>Ручная работа.</p></div></div></section>
            <section class="product-detail-accordions"><details open><summary>Описание продукта</summary><div>Состав и свойства.</div></details></section>
          </main></div>`);
          await page.addScriptTag({ content: `(() => { const content = document.querySelector('.commerce-modal-product');\n${galleryScript}\nbindProductGallery(); })();` });
          const checkLayout = async () => {
            const gallery = await page.locator('.product-detail-gallery').boundingBox();
            const accordion = await page.locator('.product-detail-accordions').boundingBox();
            assert.ok(accordion.y >= gallery.y + gallery.height, `${label}: description overlaps gallery`);
            if (count > 1) {
              const thumbs = await page.locator('.product-detail-thumbnails').boundingBox();
              assert.ok(thumbs.x >= gallery.x - 1 && thumbs.x + thumbs.width <= gallery.x + gallery.width + 1, `${label}: thumbnails overflow gallery`);
              assert.ok(accordion.y >= thumbs.y + thumbs.height, `${label}: description overlaps thumbnails`);
            }
          };
          await checkLayout();
          if (count > 1) {
            const last = page.locator('[data-product-gallery-image]').last();
            await last.focus();
            await page.keyboard.press('Enter');
            assert.equal(await page.locator('[data-product-main-image]').getAttribute('src'), `https://gallery.test/${count - 1}.svg`, label);
            assert.equal(await page.locator('[data-product-gallery-image].is-active').count(), 1, label);
            await checkLayout();
          }
        }
      }
    }
    console.log('Product galleries: 1, 4, 9 images; simple and variable; 390, 768, 1440px; keyboard switching passed.');
  } finally {
    await browser.close();
  }
})().catch((error) => { console.error(error); process.exitCode = 1; });
