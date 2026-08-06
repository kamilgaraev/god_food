const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');

const widths = [390, 768, 1440, 2560];
const outputDir = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'corporate-gifts');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  fs.mkdirSync(outputDir, { recursive: true });

  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      await page.goto('http://localhost:8080/corporate-gifts/', { waitUntil: 'networkidle' });

      const result = await page.evaluate(() => ({
        overflow: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - innerWidth,
        showcase: document.querySelectorAll('.corporate-gifts-showcase [data-product-modal-link]').length,
        branding: document.querySelectorAll('.corporate-gifts-branding article').length,
        cases: document.querySelectorAll('.corporate-gifts-cases article').length,
        minimum: Boolean(document.querySelector('.corporate-gifts-minimum')),
        form: Boolean(document.querySelector('#corporate-request form [name="company"]')),
      }));

      if (result.overflow > 1) throw new Error(`${width}px overflow: ${result.overflow}px`);
      if (result.showcase < 3) throw new Error(`${width}px showcase has ${result.showcase} products`);
      if (result.branding < 3) throw new Error(`${width}px branding has ${result.branding} variants`);
      if (result.cases < 3) throw new Error(`${width}px cases has ${result.cases} entries`);
      if (!result.minimum || !result.form) throw new Error(`${width}px required corporate content is missing`);

      await page.screenshot({ path: path.join(outputDir, `corporate-gifts-${width}.png`), fullPage: true });

      await page.locator('.corporate-gifts-showcase [data-product-modal-link]').first().click();
      await page.locator('.commerce-modal[data-commerce-type="product"].is-open').waitFor();
      const close = width <= 600 ? '.commerce-modal-back' : '.commerce-modal-close';
      await page.locator(`.commerce-modal[data-commerce-type="product"] ${close}`).click();
      await page.locator('.commerce-modal[data-commerce-type="product"].is-open').waitFor({ state: 'detached' });
      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log(`Corporate gifts verified at: ${widths.join(', ')}px`);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
