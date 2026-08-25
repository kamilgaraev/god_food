const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const themeCss = fs.readFileSync(
  path.resolve(__dirname, '../wp-content/themes/theobroma/style.css'),
  'utf8',
);

const viewports = [
  { width: 320, height: 720 },
  { width: 390, height: 844 },
  { width: 600, height: 900 },
  { width: 601, height: 900 },
  { width: 768, height: 1024 },
  { width: 900, height: 900 },
  { width: 1100, height: 900 },
  { width: 1199, height: 900 },
  { width: 1200, height: 900 },
  { width: 1209, height: 900 },
  { width: 1440, height: 900 },
  { width: 1920, height: 1080 },
  { width: 2560, height: 1440 },
  { width: 3840, height: 2160 },
];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const viewport of viewports) {
      const page = await browser.newPage({ viewport });
      await page.setContent(`
        <!doctype html>
        <html lang="ru">
          <head><style>${themeCss}</style></head>
          <body>
            <header class="site-header">
              <a class="shipping" href="/delivery">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18'/%3E" width="18" height="18" alt="">
                <span>Бесплатная доставка от 2500 рублей</span>
              </a>
            </header>
          </body>
        </html>
      `);

      const metrics = await page.locator('.shipping').evaluate((shipping) => {
        const icon = shipping.querySelector('img').getBoundingClientRect();
        const text = shipping.querySelector('span').getBoundingClientRect();
        const contentLeft = Math.min(icon.left, text.left);
        const contentRight = Math.max(icon.right, text.right);

        return {
          contentCenter: (contentLeft + contentRight) / 2,
          viewportCenter: document.documentElement.clientWidth / 2,
          iconRight: icon.right,
          textLeft: text.left,
          scrollWidth: document.documentElement.scrollWidth,
          viewportWidth: document.documentElement.clientWidth,
        };
      });

      assert.ok(
        Math.abs(metrics.contentCenter - metrics.viewportCenter) <= 1,
        `${viewport.width}x${viewport.height}: shipping content center drifted by ${Math.abs(metrics.contentCenter - metrics.viewportCenter).toFixed(2)}px`,
      );
      assert.ok(metrics.textLeft > metrics.iconRight, `${viewport.width}x${viewport.height}: shipping icon overlaps the text`);
      assert.equal(metrics.scrollWidth, metrics.viewportWidth, `${viewport.width}x${viewport.height}: shipping banner causes horizontal overflow`);

      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Shipping banner remains centered across responsive ranges');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
