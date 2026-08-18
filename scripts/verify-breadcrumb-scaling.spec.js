const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = (process.env.THEOBROMA_URL || 'http://localhost:8080').replace(/\/$/, '');
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
const layoutRoutes = [
  { path: '/catalog/', breadcrumb: '.catalog-breadcrumb' },
  { path: '/recipes/', breadcrumb: '.recipes-breadcrumb' },
  { path: '/marketplace/', breadcrumb: '.marketplace-breadcrumb' },
  { path: '/buy/', breadcrumb: '.buy-breadcrumb' },
  { path: '/cooperation/', breadcrumb: '.cooperation-breadcrumb' },
  { path: '/delivery/', breadcrumb: '.delivery-breadcrumb' },
  { path: '/media/', breadcrumb: '.media-breadcrumb' },
  { path: '/policy/', breadcrumb: '.legal-breadcrumb' },
  { path: '/recipe/classic/', breadcrumb: '.recipe-detail-breadcrumb' },
];

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

    for (const width of [390, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      try {
        for (const route of layoutRoutes) {
          const response = await page.goto(`${baseUrl}${route.path}`, { waitUntil: 'domcontentloaded' });
          assert.ok(response && response.ok(), `${route.path}: HTTP ${response?.status() || 'none'}`);
          const metrics = await page.evaluate((breadcrumbSelector) => {
            const header = document.querySelector('.site-header');
            const breadcrumb = document.querySelector(breadcrumbSelector);
            const title = document.querySelector('main h1');
            assertElements(header, breadcrumb, title);
            const breadcrumbText = breadcrumb.querySelector('a, strong') || breadcrumb;
            return {
              rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
              headerBottom: header.getBoundingClientRect().bottom,
              breadcrumbTop: breadcrumbText.getBoundingClientRect().top,
              titleTop: title.getBoundingClientRect().top,
            };

            function assertElements(...elements) {
              if (elements.some((element) => !element)) throw new Error(`Missing layout element for ${breadcrumbSelector}`);
            }
          }, route.breadcrumb);
          const topGap = metrics.breadcrumbTop - metrics.headerBottom;
          const titleGap = metrics.titleTop - metrics.breadcrumbTop;
          assert.ok(
            topGap >= metrics.rootFontSize * 3.5 && topGap <= metrics.rootFontSize * 6,
            `${width}px ${route.path}: breadcrumb top gap must stay within 3.5–6rem; received ${(topGap / metrics.rootFontSize).toFixed(3)}rem`,
          );
          assert.ok(
            titleGap >= metrics.rootFontSize * 1.5 && titleGap <= metrics.rootFontSize * 4.25,
            `${width}px ${route.path}: breadcrumb-to-title rhythm must stay within 1.5–4.25rem; received ${(titleGap / metrics.rootFontSize).toFixed(3)}rem`,
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
