const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'style.css'),
  'utf8',
);

function assertClose(actual, expected, message) {
  if (Math.abs(actual - expected) > 1) {
    throw new Error(`${message}: expected ${expected}px, received ${actual}px`);
  }
}

async function footerMetrics(browser, viewportWidth) {
  const page = await browser.newPage({ viewport: { width: viewportWidth, height: 900 } });
  await page.setContent(`
    <style>${stylesheet}</style>
    <footer class="site-footer">
      <div class="footer-shell">
        <div class="footer-map"><h3>Карта сайта</h3><ul><li>Каталог</li><li>Где купить</li></ul></div>
        <div class="footer-logo"></div>
        <div class="footer-phones"><span>+7 499 755 54 90</span><span>+7 800 444 70 54</span></div>
        <div class="footer-card footer-address">Адрес фабрики:<br>Московская обл., Наро-Фоминский г.о.</div>
        <div class="footer-media"></div>
        <div class="footer-card footer-mail"><strong>info@theobroma.msk.ru</strong><small>Коммерческие предложения и любые другие вопросы</small></div>
        <div class="footer-card footer-mail"><strong>opt@theobroma.msk.ru</strong><small>Запросы по оптовым покупкам</small></div>
        <div class="footer-card footer-mail"><strong>press@theobroma.msk.ru</strong><small>По вопросам сотрудничества со СМИ</small></div>
      </div>
      <div class="copyright"></div>
    </footer>
  `);

  const metrics = await page.evaluate(() => {
    const bounds = (selector) => {
      const rect = document.querySelector(selector).getBoundingClientRect();
      return { left: rect.left, right: rect.right, width: rect.width };
    };

    return {
      rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
      shell: bounds('.footer-shell'),
      map: bounds('.footer-map'),
      phones: bounds('.footer-phones'),
      contentOverflow: Array.from(document.querySelectorAll('.footer-card')).some((card) => {
        const cardRect = card.getBoundingClientRect();
        return Array.from(card.children).some((child) => {
          const childRect = child.getBoundingClientRect();
          return childRect.left < cardRect.left - 1 || childRect.right > cardRect.right + 1
            || childRect.top < cardRect.top - 1 || childRect.bottom > cardRect.bottom + 1;
        });
      }),
    };
  });

  await page.close();
  return metrics;
}

async function footerMediaButtonColors(browser) {
  const page = await browser.newPage({ viewport: { width: 390, height: 300 } });
  await page.setContent(`
    <style>${stylesheet}</style>
    <footer class="site-footer">
      <a class="footer-media-button" href="#media">Медиа о нас</a>
    </footer>
  `);

  const button = page.locator('.footer-media-button');
  const defaultBackground = await button.evaluate((element) => getComputedStyle(element).backgroundColor);
  await button.hover();
  const hoverBackground = await button.evaluate((element) => getComputedStyle(element).backgroundColor);

  await page.close();
  return { defaultBackground, hoverBackground };
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const buttonColors = await footerMediaButtonColors(browser);
    if (buttonColors.hoverBackground !== buttonColors.defaultBackground) {
      throw new Error(`footer media button must keep its background on hover: expected ${buttonColors.defaultBackground}, received ${buttonColors.hoverBackground}`);
    }

    const mobile = await footerMetrics(browser, 390);
    assertClose(mobile.shell.left, 0, '390px footer shell must reach the left viewport edge');
    assertClose(mobile.shell.right, 390, '390px footer shell must reach the right viewport edge');
    assertClose(mobile.map.left, 20, '390px footer content must keep a 20px left inset');
    assertClose(mobile.map.right, 370, '390px footer content must keep a 20px right inset');

    const tablet = await footerMetrics(browser, 768);
    const tabletInset = 2.5 * tablet.rootFontSize;
    assertClose(tablet.shell.left, tabletInset, '768px footer shell must keep a fluid 2.5rem left inset');
    assertClose(tablet.shell.right, 768 - tabletInset, '768px footer shell must keep a fluid 2.5rem right inset');
    assertClose(tablet.map.width, tablet.phones.width, 'tablet footer columns must have equal widths');
    assertClose(tablet.map.left - tablet.shell.left, tablet.shell.right - tablet.phones.right, 'tablet footer outer insets must be equal');

    for (const width of [461, 509, 550, 599, 600]) {
      const narrow = await footerMetrics(browser, width);
      assertClose(narrow.map.left, narrow.phones.left, `${width}px footer cards must use the mobile single-column composition`);
      assertClose(narrow.map.width, narrow.phones.width, `${width}px footer cards must keep one shared content width`);
      if (narrow.map.left < 20 || narrow.map.right > width - 20) {
        throw new Error(`${width}px footer content must stay inside 20px viewport insets`);
      }
      if (narrow.contentOverflow) {
        throw new Error(`${width}px footer text must stay inside its cards`);
      }
    }

    const tabletBoundary = await footerMetrics(browser, 601);
    if (Math.abs(tabletBoundary.map.left - tabletBoundary.phones.left) <= 1) {
      throw new Error('601px footer must switch to the tablet two-column composition');
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
