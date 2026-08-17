const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheetPath = path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css');
const viewports = [390, 768, 1440];

async function metricsAt(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 400 } });
  try {
    await page.setContent(`
      <div class="home-hero__trust">
        <div><strong>ГИ 35</strong><span>вместо 70</span></div>
        <div><strong>4,9</strong><span>1 200 отзывов</span></div>
      </div>
    `);
    await page.addStyleTag({ path: stylesheetPath });

    return await page.locator('.home-hero__trust > div').evaluateAll((items) => items.map((item) => {
      const value = item.querySelector('strong');
      const label = item.querySelector('span');
      return {
        valueFontSize: parseFloat(getComputedStyle(value).fontSize),
        valueTop: value.getBoundingClientRect().top,
        labelTop: label.getBoundingClientRect().top,
      };
    }));
  } finally {
    await page.close();
  }
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of viewports) {
      const [first, second] = await metricsAt(browser, width);
      assert.ok(
        Math.abs(first.valueFontSize - second.valueFontSize) <= 0.01,
        `${width}px: hero trust values must use the same font size`,
      );
      assert.ok(
        Math.abs(first.valueTop - second.valueTop) <= 0.5,
        `${width}px: hero trust values must align vertically`,
      );
      assert.ok(
        Math.abs(first.labelTop - second.labelTop) <= 0.5,
        `${width}px: hero trust labels must align vertically`,
      );
    }
  } finally {
    await browser.close();
  }

  console.log('Home hero trust metrics are aligned across responsive viewports');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
