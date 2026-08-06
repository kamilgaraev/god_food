const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

const assertRect = (actual, expected, label) => {
  for (const property of ['x', 'y', 'width', 'height']) {
    closeEnough(actual[property], expected[property], 2, `${label} ${property}`);
  }
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 768, height: 1024 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    await page.goto('http://localhost:8080/', { waitUntil: 'networkidle' });
    await page.evaluate(async () => document.fonts?.ready);
    const metrics = await page.evaluate(() => {
      const rect = (selector) => {
        const bounds = document.querySelector(selector).getBoundingClientRect();
        return { x: bounds.x, y: bounds.y + scrollY, width: bounds.width, height: bounds.height };
      };
      return {
        height: document.documentElement.scrollHeight,
        scrollWidth: document.documentElement.scrollWidth,
        catalogHeading: rect('#catalog .section-heading h2'),
        story: rect('.story'),
        storyHeading: rect('.story h2'),
        award: rect('.about-award'),
        values: [...document.querySelectorAll('.value')].map((element) => {
          const bounds = element.getBoundingClientRect();
          return { x: bounds.x, y: bounds.y + scrollY, width: bounds.width, height: bounds.height };
        }),
        reviewsHeading: rect('.reviews .section-heading h2'),
        reviews: [...document.querySelectorAll('.review')].slice(0, 2).map((element) => {
          const bounds = element.getBoundingClientRect();
          return { x: bounds.x, y: bounds.y + scrollY, width: bounds.width, height: bounds.height };
        }),
        decorations: [...document.querySelectorAll('.home-decor i')].map((element) => {
          const bounds = element.getBoundingClientRect();
          return { x: bounds.x, y: bounds.y + scrollY, width: bounds.width, height: bounds.height };
        }),
      };
    });
    assert.equal(metrics.scrollWidth, 768, '768px horizontal overflow');
    closeEnough(metrics.height, 4777, 2, '768px document height');
    assertRect(metrics.catalogHeading, { x: 191, y: 764, width: 386, height: 88 }, 'catalog heading');
    assertRect(metrics.story, { x: 84, y: 2090.90625, width: 600, height: 202 }, 'story');
    assertRect(metrics.storyHeading, { x: 171, y: 2120.90625, width: 426, height: 58 }, 'story heading');
    assertRect(metrics.award, { x: 142, y: 2332.90625, width: 174, height: 171 }, 'award');
    [
      { x: 394, y: 2312.90625, width: 290, height: 210 },
      { x: 84, y: 2542.90625, width: 290, height: 224 },
      { x: 394, y: 2542.90625, width: 290, height: 224 },
    ].forEach((expected, index) => assertRect(metrics.values[index], expected, `value ${index + 1}`));
    assertRect(metrics.reviewsHeading, { x: 158, y: 2846.90625, width: 453, height: 88 }, 'reviews heading');
    [
      { x: 84, y: 2974.90625, width: 260, height: 347 },
      { x: 364, y: 2974.90625, width: 260, height: 347 },
    ].forEach((expected, index) => assertRect(metrics.reviews[index], expected, `review ${index + 1}`));
    [
      { x: 586, y: 654, width: 182, height: 229.578125 },
      { x: 0, y: 1865.90625, width: 160, height: 215 },
      { x: 619, y: 2709.90625, width: 149, height: 289.625 },
      { x: 0, y: 3346.90625, width: 136, height: 269.484375 },
    ].forEach((expected, index) => assertRect(metrics.decorations[index], expected, `decoration ${index + 1}`));
    await context.close();
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
