const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeCss = fs.readFileSync(
  path.join(__dirname, '../wp-content/themes/theobroma/style.css'),
  'utf8',
);

async function launchBrowser() {
  try {
    return await chromium.launch({ headless: true });
  } catch (error) {
    if (!error.message.includes("Executable doesn't exist")) {
      throw error;
    }

    return chromium.launch({ channel: 'chrome', headless: true });
  }
}

(async () => {
  const browser = await launchBrowser();
  const page = await browser.newPage({ viewport: { width: 883, height: 223 } });

  try {
    await page.setContent(`
      <style>
        .woocommerce-info { position: relative; }
        .woocommerce-info::after { content: ""; display: table; clear: both; }
        .woocommerce-info .button { float: right; }
      </style>
      <style>${themeCss}</style>
      <body class="logged-in woocommerce-account">
        <div class="woocommerce-MyAccount-content">
          <div class="woocommerce-info"><span class="notice-copy">Заказов ещё не создано.</span> <a class="button" href="#">Просмотр товаров</a></div>
        </div>
      </body>
    `);

    const metrics = await page.locator('.woocommerce-info .button').evaluate((button) => {
      const buttonRect = button.getBoundingClientRect();
      const labelRange = document.createRange();
      labelRange.selectNodeContents(button);
      const labelRect = labelRange.getBoundingClientRect();
      const notice = button.parentElement;
      const noticeRect = notice.querySelector('.notice-copy').getBoundingClientRect();
      const noticeBox = notice.getBoundingClientRect();

      return {
        buttonCenter: buttonRect.top + buttonRect.height / 2,
        labelCenter: labelRect.top + labelRect.height / 2,
        noticeTextCenter: noticeRect.top + noticeRect.height / 2,
        noticeDisplay: getComputedStyle(notice).display,
        noticeAlignItems: getComputedStyle(notice).alignItems,
        noticeBox: { top: noticeBox.top, height: noticeBox.height },
        noticeTextBox: { top: noticeRect.top, height: noticeRect.height },
        buttonBox: { top: buttonRect.top, height: buttonRect.height },
        display: getComputedStyle(button).display,
        float: getComputedStyle(button).float,
      };
    });

    assert.ok(
      Math.abs(metrics.buttonCenter - metrics.labelCenter) <= 1,
      `product link label must be vertically centered; centers differ by ${Math.abs(metrics.buttonCenter - metrics.labelCenter)}px`,
    );
    assert.ok(
      Math.abs(metrics.buttonCenter - metrics.noticeTextCenter) <= 1,
      `notice text and product link must share a center line; received ${JSON.stringify(metrics)}`,
    );
    assert.equal(metrics.float, 'right', 'product link must remain on the right side of the notice');
  } finally {
    await browser.close();
  }

  console.log('Account empty-state alignment verification passed.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
