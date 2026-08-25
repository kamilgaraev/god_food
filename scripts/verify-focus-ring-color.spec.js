const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const stylesheet = fs.readFileSync(
  path.join(root, 'wp-content', 'themes', 'theobroma', 'style.css'),
  'utf8',
);

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  let page;

  try {
    page = await browser.newPage();
    await page.setContent(`<!doctype html><html><body>
      <nav class="catalog-filters"><a href="#catalog">Категория</a></nav>
      <button type="button">Кнопка</button>
      <details><summary>Подробнее</summary></details>
      <div tabindex="0">Интерактивная область</div>
    </body></html>`);
    await page.addStyleTag({ content: stylesheet });

    const expectedLabels = ['Категория', 'Кнопка', 'Подробнее', 'Интерактивная область'];
    for (const label of expectedLabels) {
      await page.keyboard.press('Tab');
      const focused = page.locator(':focus');
      assert.equal((await focused.textContent()).trim(), label, `${label} must be reachable by keyboard`);
      assert.deepEqual(
        await focused.evaluate((element) => {
          const style = getComputedStyle(element);
          return {
            color: style.outlineColor,
            style: style.outlineStyle,
            width: style.outlineWidth,
          };
        }),
        { color: 'rgb(176, 144, 61)', style: 'solid', width: '2px' },
        `${label} must use the branded gold keyboard focus ring`,
      );
    }
  } finally {
    if (page) await page.close();
    await browser.close();
  }

  console.log('Gold focus ring contract verified');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
