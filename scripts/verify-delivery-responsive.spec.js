const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const cases = {
      390: { height: 2194, breadcrumb: { x: 71.90625, y: 153.890625, width: 246.234375, height: 20.71875 }, decor: { x: 0, y: 101.46875, width: 106.046875, height: 142.75 }, title: { x: 47.53125, y: 192.90625, width: 294.984375, height: 73.125 }, lead: { x: 45.09375, y: 278.234375, width: 301.078125, height: 78.015625 }, accordion: { x: 20, y: 392, width: 350, height: 487.5 }, footerY: 879.5 },
      430: { height: 2369, breadcrumb: { x: 79.28125, y: 170.390625, width: 271.484375, height: 22.84375 }, decor: { x: 0, y: 112.59375, width: 116.921875, height: 157.390625 }, title: { x: 52.40625, y: 213.40625, width: 325.234375, height: 80.625 }, lead: { x: 49.71875, y: 307.484375, width: 331.953125, height: 86.015625 }, accordion: { x: 20, y: 433, width: 390, height: 487.5 }, footerY: 920.5 },
      768: { height: 1720, accordion: { x: 84, y: 390, width: 600, height: 531 }, footerY: 921 },
    };
    for (const [widthKey, expected] of Object.entries(cases)) {
      const width = Number(widthKey);
      const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : (width === 430 ? 932 : 1024) }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto('http://localhost:8080/delivery/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const rect = (selector) => {
          const box = document.querySelector(selector).getBoundingClientRect();
          return { x: box.x, y: box.y + scrollY, width: box.width, height: box.height };
        };
        return {
          height: document.documentElement.scrollHeight,
          scrollWidth: document.documentElement.scrollWidth,
          title: rect('.delivery-page > h1'),
          lead: rect('.delivery-lead'),
          breadcrumb: rect('.delivery-breadcrumb'),
          decor: rect('.delivery-decor'),
          accordion: rect('.delivery-accordion'),
          footerY: rect('.site-footer').y,
        };
      });
      assert.equal(metrics.scrollWidth, width, `${width}px: horizontal overflow`);
      closeEnough(metrics.height, expected.height, 2, `${width}px document height`);
      closeEnough(metrics.footerY, expected.footerY, 1, `${width}px footer boundary`);
      for (const [metric, target] of Object.entries(expected.accordion)) closeEnough(metrics.accordion[metric], target, 1, `${width}px accordion ${metric}`);
      if (expected.title) {
        for (const [metric, target] of Object.entries(expected.breadcrumb)) closeEnough(metrics.breadcrumb[metric], target, 1, `${width}px breadcrumb ${metric}`);
        for (const [metric, target] of Object.entries(expected.decor)) closeEnough(metrics.decor[metric], target, 1, `${width}px decor ${metric}`);
        for (const [metric, target] of Object.entries(expected.title)) closeEnough(metrics.title[metric], target, 1, `${width}px title ${metric}`);
        for (const [metric, target] of Object.entries(expected.lead)) closeEnough(metrics.lead[metric], target, 1, `${width}px lead ${metric}`);
      }
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
