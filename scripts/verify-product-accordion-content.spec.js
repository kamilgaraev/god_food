const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = [
  {
    slug: 'theobroma-200-68-coriander',
    summaries: ['Описание продукта', 'Польза кокосового сахара'],
  },
  {
    slug: 'theobroma-chia-250',
    summaries: ['Описание продукта', 'Полезные свойства семян чиа'],
  },
  {
    slug: 'theobroma-cacao-100',
    summaries: ['Описание продукта'],
  },
];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 430, height: 932 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    for (const testCase of cases) {
      await page.goto(`http://localhost:8080/product/${testCase.slug}/`, { waitUntil: 'networkidle' });
      const accordions = page.locator('.product-detail-accordions:visible details');
      const summaries = (await accordions.locator('summary').allTextContents()).map((text) => text.replace(/\s+/g, ' ').trim());
      assert.deepEqual(summaries, testCase.summaries, `${testCase.slug}: accordion structure differs from the source product`);
      for (let index = 0; index < await accordions.count(); index += 1) {
        assert.ok(((await accordions.nth(index).locator('div').textContent()) || '').trim().length > 0, `${testCase.slug}: accordion ${index + 1} is empty`);
      }
    }
    await context.close();
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
