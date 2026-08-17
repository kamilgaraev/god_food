const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'style.css'),
  'utf8',
);

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

async function verifyRecipe(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  await page.goto('http://localhost:8080/recipe/classic/', { waitUntil: 'networkidle' });
  await page.evaluate(async () => document.fonts?.ready);

  const metrics = await page.evaluate(() => {
    const method = document.querySelector('.recipe-method').getBoundingClientRect();
    const image = document.querySelector('.recipe-method > img').getBoundingClientRect();
    return {
      scrollWidth: document.documentElement.scrollWidth,
      method: { left: method.left, right: method.right, top: method.top, bottom: method.bottom },
      image: { left: image.left, right: image.right, top: image.top, bottom: image.bottom },
    };
  });

  assert.equal(metrics.scrollWidth, width, `${width}px recipe must not overflow horizontally`);
  assert.ok(metrics.image.left >= metrics.method.left - 1, `${width}px recipe image must start inside its card`);
  assert.ok(metrics.image.right <= metrics.method.right + 1, `${width}px recipe image must end inside its card`);
  assert.ok(metrics.image.top >= metrics.method.top - 1, `${width}px recipe image must start inside its card vertically`);
  assert.ok(metrics.image.bottom <= metrics.method.bottom + 1, `${width}px recipe image must end inside its card vertically`);
  await page.close();
}

async function verifyCooperation(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  await page.goto('http://localhost:8080/cooperation/', { waitUntil: 'networkidle' });
  await page.evaluate(async () => document.fonts?.ready);

  const metrics = await page.evaluate(() => {
    const panel = document.querySelector('.cooperation-form').getBoundingClientRect();
    const form = document.querySelector('.cooperation-form form').getBoundingClientRect();
    const fields = [...document.querySelectorAll('.cooperation-form .form-grid > input,.cooperation-form .phone-field')]
      .map((field) => {
        const rect = field.getBoundingClientRect();
        return { left: rect.left, right: rect.right };
      });
    return {
      panel: { left: panel.left, right: panel.right },
      form: { left: form.left, right: form.right },
      fields,
    };
  });

  const leftInset = metrics.form.left - metrics.panel.left;
  const rightInset = metrics.panel.right - metrics.form.right;
  closeEnough(leftInset, rightInset, 1, `${width}px cooperation form side insets`);
  for (const [index, field] of metrics.fields.entries()) {
    closeEnough(field.left, metrics.form.left, 1, `${width}px cooperation field ${index + 1} left edge`);
    closeEnough(field.right, metrics.form.right, 1, `${width}px cooperation field ${index + 1} right edge`);
  }
  await page.close();
}

async function verifyAccount(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  await page.setContent(`
    <style>${stylesheet}</style>
    <body class="logged-in woocommerce-account">
      <main class="shop-page">
        <div class="shop-shell">
          <div class="woocommerce">
            <nav class="woocommerce-MyAccount-navigation"><ul>
              <li><a href="#">Главная</a></li><li><a href="#">Заказы</a></li><li><a href="#">Бонусы</a></li>
              <li><a href="#">Адреса</a></li><li><a href="#">Профиль</a></li><li><a href="#">Выйти</a></li>
            </ul></nav>
            <div class="woocommerce-MyAccount-content"><div class="woocommerce-info">Заказов ещё не создано.</div></div>
          </div>
        </div>
      </main>
    </body>
  `);

  const metrics = await page.evaluate(() => {
    const shell = document.querySelector('.shop-shell').getBoundingClientRect();
    const root = getComputedStyle(document.documentElement);
    return {
      shell: { left: shell.left, right: shell.right, width: shell.width },
      expectedWidth: innerWidth - 2 * 1.125 * parseFloat(root.fontSize),
    };
  });

  closeEnough(metrics.shell.width, metrics.expectedWidth, 1, `${width}px account shell width`);
  closeEnough(metrics.shell.left, width - metrics.shell.right, 1, `${width}px account shell centering`);
  await page.close();
}

async function verifyFooterIcons(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  await page.setContent(`
    <style>${stylesheet}</style>
    <footer class="site-footer"><div class="footer-shell">
      <div class="footer-phones"><a href="tel:+74997555490">+7 499 755 54 90</a><a href="tel:+78004447054">+7 800 444 70 54</a></div>
      <div class="footer-card footer-address">Адрес фабрики:<br>Московская обл.,<br>Наро-Фоминский г.о.</div>
      <div class="footer-card footer-mail"><strong>info@theobroma.msk.ru</strong><small>Коммерческие предложения</small></div>
      <div class="footer-card footer-mail"><strong>opt@theobroma.msk.ru</strong><small>Оптовые покупки</small></div>
      <div class="footer-card footer-mail"><strong>press@theobroma.msk.ru</strong><small>Сотрудничество со СМИ</small></div>
    </div></footer>
  `);

  const icons = await page.evaluate(() => ['.footer-phones', '.footer-address', '.footer-mail'].map((selector) => {
    const element = document.querySelector(selector);
    const card = element.getBoundingClientRect();
    const pseudo = getComputedStyle(element, '::before');
    return {
      selector,
      cardHeight: card.height,
      content: pseudo.content,
      height: parseFloat(pseudo.height),
      hasArtwork: pseudo.backgroundImage !== 'none' || pseudo.maskImage !== 'none' || pseudo.webkitMaskImage !== 'none',
    };
  }));

  for (const icon of icons) {
    assert.notEqual(icon.content, 'none', `${width}px ${icon.selector} must render an icon`);
    assert.equal(icon.hasArtwork, true, `${width}px ${icon.selector} must have icon artwork`);
    assert.ok(icon.height >= icon.cardHeight - 2, `${width}px ${icon.selector} icon must span the card height`);
  }
  await page.close();
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 509, 600]) {
      await verifyRecipe(browser, width);
      await verifyCooperation(browser, width);
      await verifyAccount(browser, width);
      await verifyFooterIcons(browser, width);
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
