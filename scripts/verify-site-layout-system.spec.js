const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const baseUrl = (process.env.THEOBROMA_URL || 'http://localhost:8080').replace(/\/$/, '');
const viewports = [
  { name: 'narrow-mobile', width: 320 },
  { name: 'mobile', width: 390 },
  { name: 'wide-mobile', width: 521 },
  { name: 'mobile-boundary', width: 600 },
  { name: 'tablet', width: 900 },
  { name: 'wide-tablet', width: 1199 },
  { name: 'desktop-boundary', width: 1200 },
  { name: 'desktop', width: 2048 },
  { name: 'ultrawide', width: 3200 },
];

const pages = [
  { path: '/', exact: ['.home-product-grid', '.home-cacao__shell', '.home-composition__shell'], mobileExact: ['.home-cacao__tabs', '.story', '.value:first-child', '.reviews-stage', '.contact-card', '.form-grid'], mobileLeft: ['.review:first-child'], contained: ['.home-hero__lead > p', '.home-hero__actions', '.home-section-heading h2', '.home-section-heading > a', '.home-promo-card:first-child', '.home-promo-card:last-child'] },
  { path: '/catalog/', exact: ['.catalog-page .shop-shell'], contained: ['.catalog-page ul.products'] },
  { path: '/recipes/', exact: ['.recipe-grid'] },
  { path: '/recipe/classic/', exact: ['.recipe-detail-columns', '.recipe-product-promo'] },
  { path: '/marketplace/', exact: ['.market-grid'] },
  { path: '/buy/', contained: ['.buy-tabs', '.buy-location'] },
  { path: '/cooperation/', contained: ['.cooperation-form', '.cooperation-benefits'] },
  { path: '/delivery/', exact: ['.delivery-accordion'] },
  { path: '/media/', exact: ['.media-grid'] },
  { path: '/policy/', exact: ['.legal-content'] },
  { path: '/corporate-gifts/', exact: ['.corporate-gifts-showcase', '.corporate-gifts-branding', '.corporate-gifts-cases', '.corporate-gifts-minimum', '.corporate-gifts-benefits', '.corporate-gifts-request'] },
  { path: '/my-account/', exact: ['.shop-shell'] },
  { path: '/cart/', exact: ['.shop-shell'] },
  { path: '/checkout/', exact: ['.shop-shell'] },
];

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const failures = [];
  try {
    for (const viewport of viewports) {
      for (const entry of pages) {
        const page = await browser.newPage({ viewport: { width: viewport.width, height: 1100 }, reducedMotion: 'reduce' });
        await page.goto(`${baseUrl}${entry.path}`, { waitUntil: 'domcontentloaded' });
        await page.evaluate(() => document.fonts.ready);
        const mobileExact = viewport.width <= 600 ? (entry.mobileExact || []) : [];
        const mobileLeft = viewport.width <= 600 ? (entry.mobileLeft || []) : [];
        const exactSelectors = [...(entry.exact || []), ...mobileExact];
        const selectors = [...exactSelectors, ...mobileLeft, ...(entry.contained || [])];
        const metrics = await page.evaluate((selectors) => {
          const root = document.documentElement;
          const probe = document.createElement('div');
          probe.style.cssText = 'position:fixed;visibility:hidden;width:min(var(--layout-container),calc(100vw - 2 * var(--layout-gutter)));height:1px';
          document.body.appendChild(probe);
          const expectedWidth = probe.getBoundingClientRect().width;
          probe.remove();
          const expectedLeft = (root.clientWidth - expectedWidth) / 2;
          const boxes = selectors.map((selector) => {
            const element = document.querySelector(selector);
            if (!element) return { selector, missing: true };
            const box = element.getBoundingClientRect();
            return { selector, left: box.left, right: box.right, width: box.width };
          });
          const phone = document.querySelector('.phone-field');
          let phoneParts = null;
          if (phone) {
            const input = phone.querySelector('input[type="tel"]');
            const fieldBox = phone.getBoundingClientRect();
            const inputBox = input.getBoundingClientRect();
            phoneParts = {
              field: { left: fieldBox.left, right: fieldBox.right },
              input: { left: inputBox.left, right: inputBox.right },
              hasCountryControl: Boolean(phone.querySelector('.phone-flag,.phone-triangle,.phone-code')),
            };
          }
          return {
            expectedLeft,
            expectedRight: expectedLeft + expectedWidth,
            overflow: root.scrollWidth - root.clientWidth,
            boxes,
            phoneParts,
          };
        }, selectors);
        for (const box of metrics.boxes) {
          if (box.missing) {
            failures.push(`${viewport.name} ${entry.path} missing ${box.selector}`);
            continue;
          }
          if (box.left < metrics.expectedLeft - 2 || box.right > metrics.expectedRight + 2) {
            failures.push(`${viewport.name} ${entry.path} ${box.selector} escapes shared container: ${box.left.toFixed(1)}..${box.right.toFixed(1)} vs ${metrics.expectedLeft.toFixed(1)}..${metrics.expectedRight.toFixed(1)}`);
          }
          if (exactSelectors.includes(box.selector) && (Math.abs(box.left - metrics.expectedLeft) > 2 || Math.abs(box.right - metrics.expectedRight) > 2)) {
            failures.push(`${viewport.name} ${entry.path} ${box.selector} does not use the shared container exactly: ${box.left.toFixed(1)}..${box.right.toFixed(1)} vs ${metrics.expectedLeft.toFixed(1)}..${metrics.expectedRight.toFixed(1)}`);
          }
          if (mobileLeft.includes(box.selector) && Math.abs(box.left - metrics.expectedLeft) > 2) {
            failures.push(`${viewport.name} ${entry.path} ${box.selector} does not start on the shared container: ${box.left.toFixed(1)} vs ${metrics.expectedLeft.toFixed(1)}`);
          }
        }
        if (metrics.overflow > 1) failures.push(`${viewport.name} ${entry.path} horizontal overflow ${metrics.overflow}px`);
        if (metrics.phoneParts) {
          const { field, input, hasCountryControl } = metrics.phoneParts;
          if (hasCountryControl || input.left < field.left - 1 || input.right > field.right + 1) {
            failures.push(`${viewport.name} ${entry.path} phone input must fill its field without a country control`);
          }
        }
        await page.close();
      }
    }
    assert.deepEqual(failures, [], `Shared layout regressions:\n- ${failures.join('\n- ')}`);
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
