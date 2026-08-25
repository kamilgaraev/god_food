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
    if (!error.message.includes("Executable doesn't exist")) throw error;
    return chromium.launch({ channel: 'chrome', headless: true });
  }
}

async function accountRadii(page) {
  return page.evaluate(() => {
    const radius = (selector) => getComputedStyle(document.querySelector(selector)).borderRadius;

    return {
      navigation: radius('.woocommerce-MyAccount-navigation ul'),
      content: radius('.woocommerce-MyAccount-content'),
      card: radius('.account-dashboard-grid a'),
    };
  });
}

(async () => {
  const browser = await launchBrowser();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  try {
    await page.setContent(`
      <style>${themeCss}</style>
      <body class="logged-in woocommerce-account">
        <main class="shop-page"><div class="shop-shell"><div class="woocommerce">
          <nav class="woocommerce-MyAccount-navigation"><ul><li class="is-active"><a href="#">Главная</a></li></ul></nav>
          <div class="woocommerce-MyAccount-content">
            <section class="theobroma-account-dashboard">
              <div class="account-dashboard-grid"><a href="#"><span>Заказы</span><small>История покупок</small></a></div>
            </section>
          </div>
        </div></div></main>
      </body>
    `);

    assert.deepEqual(await accountRadii(page), {
      navigation: '0px',
      content: '0px',
      card: '0px',
    });

    await page.setViewportSize({ width: 390, height: 844 });
    assert.deepEqual(await accountRadii(page), {
      navigation: '0px',
      content: '0px',
      card: '0px',
    });
  } finally {
    await browser.close();
  }

  console.log('Account layout uses square corners at desktop and mobile sizes.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
