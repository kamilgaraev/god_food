const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'style.css'),
  'utf8',
);
const breadcrumbClasses = [
  'catalog-breadcrumb',
  'recipes-breadcrumb',
  'marketplace-breadcrumb',
  'buy-breadcrumb',
  'cooperation-breadcrumb',
  'delivery-breadcrumb',
  'media-breadcrumb',
  'legal-breadcrumb',
  'recipe-detail-breadcrumb',
];
const widths = [320, 390, 600, 768, 900, 1199, 1200, 1440, 1920, 2560, 3200];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 800 } });
      try {
        await page.setContent(breadcrumbClasses.map((className) => (
          `<nav class="${className}"><a href="#">Главная</a><span>/</span><strong>Раздел</strong></nav>`
        )).join(''));
        await page.addStyleTag({ content: stylesheet });
        const metrics = await page.evaluate((classes) => ({
          rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
          breadcrumbs: Object.fromEntries(classes.map((className) => [
            className,
            parseFloat(getComputedStyle(document.querySelector(`.${className}`)).fontSize),
          ])),
        }), breadcrumbClasses);

        for (const [className, fontSize] of Object.entries(metrics.breadcrumbs)) {
          assert.ok(
            Math.abs(fontSize - metrics.rootFontSize) <= 0.02,
            `${width}px: .${className} must follow the base text size; root ${metrics.rootFontSize}px, breadcrumb ${fontSize}px`,
          );
        }
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }

  console.log('Breadcrumb typography follows the global text scale at every responsive width');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
