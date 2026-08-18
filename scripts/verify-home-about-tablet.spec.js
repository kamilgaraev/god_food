const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeRoot = path.resolve(__dirname, '../wp-content/themes/theobroma');
const style = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8');
const homeStyle = fs.readFileSync(path.join(themeRoot, 'assets/css/home-redesign.css'), 'utf8');

function withEmbeddedFonts(css) {
  const fonts = [
    'cormorant-cyrillic-variable.woff2',
    'cormorant-latin-variable.woff2',
    'montserrat-cyrillic.woff2',
    'montserrat-latin.woff2',
  ];

  return fonts.reduce((result, font) => {
    const data = fs.readFileSync(path.join(themeRoot, 'assets/fonts', font)).toString('base64');
    return result.replaceAll(`assets/fonts/${font}`, `data:font/woff2;base64,${data}`);
  }, css);
}

const markup = `
  <section class="feature"><div class="about-stage">
    <img class="about-award" alt="">
    <div class="story">
      <h2><em>Theobroma</em> — абсолютно<br>натуральный шоколад</h2>
      <p>Компания Theobroma Пища Богов — российский бренд, который<br>бережно сочетает вековые кулинарные традиции с современными<br>технологиями в создании натурального шоколада и какао.</p>
    </div>
    <div class="values">
      <article class="value"><img alt=""><div><h3>Признание экспертов</h3><p>Продукт года по версии WorldFood Moscow</p><p>Лауреат премии «Здоровое питание» (2015)</p></div></article>
      <article class="value"><img alt=""><div><h3>Натуральный состав</h3><p>Бережно сохраняем природные свойства какао и используем только чистые ингредиенты без лишних добавок.</p></div></article>
      <article class="value"><img alt=""><div><h3>Без белого сахара</h3><p>Мы используем кокосовый сахар — природный источник минералов: калия, магния, цинка и железа.</p></div></article>
    </div>
  </div></section>
  <p class="home-cacao__description">Описание вкуса шоколада</p>
`;

function contained(inner, outer, tolerance = 1) {
  return inner.left >= outer.left - tolerance && inner.right <= outer.right + tolerance;
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [601, 649, 768, 1199]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
      await page.setContent(markup);
      await page.addStyleTag({ content: withEmbeddedFonts(style) });
      await page.addStyleTag({ content: homeStyle });
      await page.evaluate(() => document.fonts.ready);

      const metrics = await page.evaluate(() => {
        const rect = (element) => {
          const box = element.getBoundingClientRect();
          return { left: box.left, right: box.right };
        };
        const textRect = (element) => {
          const range = document.createRange();
          range.selectNodeContents(element);
          return rect(range);
        };
        const story = document.querySelector('.story');
        const storyCopy = story.querySelector('p');
        const reference = document.querySelector('.home-cacao__description');
        return {
          story: rect(story),
          storyCopy: rect(storyCopy),
          storyText: textRect(storyCopy),
          storyFontSize: parseFloat(getComputedStyle(storyCopy).fontSize),
          referenceFontSize: parseFloat(getComputedStyle(reference).fontSize),
          values: Array.from(document.querySelectorAll('.value'), (card) => ({
            card: rect(card),
            inner: rect(card.querySelector('div')),
            content: Array.from(card.querySelectorAll('h3,p'), (element) => ({
              box: rect(element),
              text: textRect(element),
            })),
            copyFontSize: parseFloat(getComputedStyle(card.querySelector('p')).fontSize),
          })),
        };
      });

      assert(contained(metrics.storyCopy, metrics.story), `${width}px story copy box must stay inside its card`);
      assert(contained(metrics.storyText, metrics.story), `${width}px story text must stay inside its card`);
      assert.equal(metrics.storyFontSize, metrics.referenceFontSize, `${width}px story copy must use the shared homepage description scale`);
      for (const value of metrics.values) {
        assert(contained(value.inner, value.card), `${width}px value content must stay inside its card`);
        assert.equal(value.copyFontSize, metrics.referenceFontSize, `${width}px value copy must use the shared homepage description scale`);
        for (const content of value.content) {
          assert(contained(content.box, value.card), `${width}px value content box must stay inside its card`);
          assert(contained(content.text, value.card), `${width}px value text must stay inside its card`);
        }
      }

      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Homepage about copy stays contained and follows the shared tablet type scale');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
