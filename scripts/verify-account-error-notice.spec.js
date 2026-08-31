const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeCss = fs.readFileSync(
  path.join(__dirname, '../wp-content/themes/theobroma/style.css'),
  'utf8',
);

const woocommerceNoticeCss = `
  .woocommerce-error {
    position: relative;
    width: auto;
    margin: 0 0 2em;
    padding: 1em 2em 1em 3.5em;
    border-top: 3px solid #b81c23;
    background: #f7f6f7;
    color: #515151;
    list-style: none outside;
  }
  .woocommerce-error::before {
    content: "!";
    position: absolute;
    top: 1em;
    left: 1.5em;
    width: 1em;
    font: 700 16px/1 sans-serif;
    text-align: center;
  }
`;

async function launchBrowser() {
  try {
    return await chromium.launch({ headless: true });
  } catch (error) {
    if (!error.message.includes("Executable doesn't exist")) throw error;
    return chromium.launch({ channel: 'chrome', headless: true });
  }
}

async function noticeMetrics(page, selector) {
  return page.locator(selector).evaluate((notice) => {
    const noticeRect = notice.getBoundingClientRect();
    const prefixRect = notice.querySelector('strong').getBoundingClientRect();
    const pseudo = getComputedStyle(notice, '::before');
    const style = getComputedStyle(notice);

    return {
      width: noticeRect.width,
      leftInset: prefixRect.left - noticeRect.left,
      iconLeft: Number.parseFloat(pseudo.left),
      iconWidth: Number.parseFloat(pseudo.width),
      borderTopWidth: style.borderTopWidth,
      borderLeftWidth: style.borderLeftWidth,
      documentWidth: document.documentElement.scrollWidth,
      viewportWidth: document.documentElement.clientWidth,
    };
  });
}

(async () => {
  const browser = await launchBrowser();
  const page = await browser.newPage({ viewport: { width: 1400, height: 700 } });

  try {
    await page.setContent(`
      <style>${woocommerceNoticeCss}</style>
      <style>${themeCss}</style>
      <body class="woocommerce-account">
        <main class="shop-page">
          <div class="shop-shell">
            <div class="woocommerce">
              <ul class="woocommerce-error" role="alert">
                <li><strong>Ошибка:</strong> Введённый вами пароль пользователя kamilgaraev неверен. Забыли пароль?</li>
              </ul>
            </div>
          </div>
        </main>
        <div class="account-modal">
          <section class="account-modal-panel">
            <div class="account-modal-body">
              <div class="account-modal-notices">
                <ul class="woocommerce-error" role="alert">
                  <li><strong>Ошибка:</strong> Введённый вами пароль пользователя kamilgaraev неверен. Забыли пароль?</li>
                </ul>
              </div>
            </div>
          </section>
        </div>
      </body>
    `);

    const pageNotice = await noticeMetrics(page, '.shop-shell > .woocommerce > .woocommerce-error');
    const modalNotice = await noticeMetrics(page, '.account-modal-notices .woocommerce-error');

    assert.ok(
      pageNotice.width <= 832.5,
      `account error must stay compact instead of spanning the content column; received ${pageNotice.width}px`,
    );
    assert.equal(pageNotice.borderTopWidth, '0px', 'account error must not use WooCommerce top-stripe styling');
    assert.equal(pageNotice.borderLeftWidth, '3px', 'account error must keep a compact error accent');
    assert.ok(
      modalNotice.leftInset >= modalNotice.iconLeft + modalNotice.iconWidth + 8,
      `modal icon must not overlap the error prefix; received ${JSON.stringify(modalNotice)}`,
    );
    await page.setViewportSize({ width: 360, height: 700 });
    const narrowNotice = await noticeMetrics(page, '.shop-shell > .woocommerce > .woocommerce-error');
    assert.equal(
      narrowNotice.documentWidth,
      narrowNotice.viewportWidth,
      `account error must not create horizontal overflow; received ${JSON.stringify(narrowNotice)}`,
    );
  } finally {
    await browser.close();
  }

  console.log('Account error notices stay compact, readable and overlap-free.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
