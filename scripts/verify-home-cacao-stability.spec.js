const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const cssPath = path.join(root, 'wp-content/themes/theobroma/assets/css/home-redesign.css');
const scriptPath = path.join(root, 'wp-content/themes/theobroma/assets/js/homepage.js');
const image = 'data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20width=%22420%22%20height=%22420%22/%3E';
const options = [
  { percent: 59, label: 'Мягкий', description: 'Мягкий шоколад с ягодными, ореховыми и фруктовыми сочетаниями.', fact: 'Нежный вкус для знакомства с шоколадом.', price: 'от 219₽' },
  { percent: 65, label: 'Пряный', description: 'Тёплый вкус какао с выразительной нотой натуральной корицы.', fact: 'Корица раскрывается в долгом послевкусии.', price: 'от 225₽' },
  { percent: 68, label: 'Характерный', description: 'Чистый шоколадный вкус, раскрытый тонким ароматом кориандра.', fact: 'Ароматный шоколад из отборных какао-бобов.', price: 'от 229₽' },
  { percent: 70, label: 'Классический', description: 'Баланс насыщенного какао и деликатной сладости кокосового сахара.', fact: 'Классический вкус без лишних добавок.', price: 'от 229₽' },
  { percent: 80, label: 'Глубокий', description: 'Глубокий, строгий вкус с долгим шоколадным послевкусием.', fact: 'Максимум какао для ценителей насыщенного вкуса.', price: 'от 239₽' },
];

function tabMarkup(option) {
  const selected = option.percent === 70;
  return `<button
    type="button"
    role="tab"
    id="home-cacao-tab-${option.percent}"
    aria-selected="${selected}"
    tabindex="${selected ? 0 : -1}"
    data-cacao-option="${option.percent}"
    data-title="${option.label} ${option.percent}%"
    data-description="${option.description}"
    data-fact="${option.fact}"
    data-price="${option.price}"
    data-url="/catalog?cacao_percentage=${option.percent}"
    data-image="${image}"
    data-image-alt="Шоколад ${option.percent}%"
  ><strong>${option.percent}%</strong><span>${option.label}</span></button>`;
}

function assertStable(actual, expected, message) {
  assert.ok(Math.abs(actual - expected) <= 0.5, `${message}: ${actual.toFixed(2)}px vs ${expected.toFixed(2)}px`);
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({
      viewport: { width: 1440, height: 900 },
      reducedMotion: 'no-preference',
    });

    const initial = options.find((option) => option.percent === 70);
    await page.setContent(`
      <!doctype html>
      <main class="home">
        <section class="home-cacao">
          <div class="home-cacao__shell">
            <div class="home-cacao__selector">
              <h2>Ваш процент какао</h2>
              <p class="home-cacao__intro">Выберите насыщенность шоколада.</p>
              <div class="home-cacao__tabs" role="tablist">${options.map(tabMarkup).join('')}</div>
            </div>
            <div class="home-cacao__panel" role="tabpanel" aria-labelledby="home-cacao-tab-70" data-cacao-panel>
              <div class="home-cacao__image-wrap"><img src="${image}" alt="Шоколад 70%"></div>
              <div class="home-cacao__copy">
                <h3 data-cacao-title>${initial.label} ${initial.percent}%</h3>
                <p class="home-cacao__description" data-cacao-description>${initial.description}</p>
                <p class="home-cacao__fact" data-cacao-fact>${initial.fact}</p>
                <div class="home-cacao__buy"><a class="home-button" href="#">Купить</a><strong>${initial.price}</strong></div>
              </div>
            </div>
          </div>
        </section>
        <section data-stable-section>Следующая секция</section>
      </main>
    `);
    await page.addStyleTag({ path: cssPath });
    await page.addScriptTag({ path: scriptPath });

    const panel = page.locator('[data-cacao-panel]');
    const stableSection = page.locator('[data-stable-section]');
    const initialPanelTop = await panel.evaluate((element) => element.getBoundingClientRect().top);
    const initialSectionTop = await stableSection.evaluate((element) => element.getBoundingClientRect().top);
    const initialScrollY = await page.evaluate(() => window.scrollY);

    for (const option of options) {
      const tab = page.locator(`[data-cacao-option="${option.percent}"]`);
      await tab.click();
      await page.evaluate(() => new Promise((resolve) => window.setTimeout(resolve, 50)));

      assert.equal(await panel.evaluate((element) => element.classList.contains('is-changing')), true, `${option.percent}% must enter the changing state`);
      assertStable(await panel.evaluate((element) => element.getBoundingClientRect().top), initialPanelTop, `${option.percent}% moves the panel during the transition`);
      assertStable(await stableSection.evaluate((element) => element.getBoundingClientRect().top), initialSectionTop, `${option.percent}% moves the following section during the transition`);
      assert.equal(await page.evaluate(() => window.scrollY), initialScrollY, `${option.percent}% changes the page scroll position`);

      await page.waitForFunction(
        (percent) => {
          const currentPanel = document.querySelector('[data-cacao-panel]');
          const currentTitle = document.querySelector('[data-cacao-title]');
          return currentPanel && !currentPanel.classList.contains('is-changing') && currentTitle && currentTitle.textContent.endsWith(`${percent}%`);
        },
        option.percent,
      );
      await page.waitForTimeout(350);

      assert.equal(await tab.getAttribute('aria-selected'), 'true', `${option.percent}% must become the selected tab`);
      assert.equal(await panel.getAttribute('aria-labelledby'), `home-cacao-tab-${option.percent}`);
      assertStable(await panel.evaluate((element) => element.getBoundingClientRect().top), initialPanelTop, `${option.percent}% moves the settled panel`);
      assertStable(await stableSection.evaluate((element) => element.getBoundingClientRect().top), initialSectionTop, `${option.percent}% moves the settled following section`);
      assert.equal(await page.evaluate(() => window.scrollY), initialScrollY, `${option.percent}% changes scroll after settling`);
    }

    await page.close();
  } finally {
    await browser.close();
  }

  console.log('All cacao options switch without moving the panel, page, or following section.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
