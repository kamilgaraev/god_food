const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const HOME_CSS = path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css');
const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
];

async function run() {
  const css = fs.readFileSync(HOME_CSS, 'utf8');
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const viewport of VIEWPORTS) {
      const page = await browser.newPage({ viewport, reducedMotion: 'reduce' });
      await page.route('**/home-redesign.css*', (route) => route.fulfill({ contentType: 'text/css', body: css }));
      await page.goto(BASE_URL, { waitUntil: 'networkidle' });
      await page.evaluate(() => document.fonts.ready);

      const colors = await page.evaluate(() => ({
        eyebrow: getComputedStyle(document.querySelector('.home-eyebrow')).color,
        primaryButton: getComputedStyle(document.querySelector('.home-button--primary')).backgroundColor,
      }));

      console.log(`${viewport.name}: eyebrow=${colors.eyebrow}; primary-button=${colors.primaryButton}`);
      if (colors.eyebrow !== colors.primaryButton) {
        throw new Error(`${viewport.name} homepage eyebrow must use the primary button color`);
      }
      await page.close();
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
