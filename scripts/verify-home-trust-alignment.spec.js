const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');

const themePath = path.resolve(__dirname, '../wp-content/themes/theobroma');
const homeCss = fs.readFileSync(path.join(themePath, 'assets/css/home-redesign.css'), 'utf8');
const viewports = [390, 768, 1440];

function themeCssWithEmbeddedFonts() {
  return fs.readFileSync(path.join(themePath, 'style.css'), 'utf8').replace(
    /url\('assets\/fonts\/([^']+)'\)/g,
    (_, fileName) => {
      const font = fs.readFileSync(path.join(themePath, 'assets/fonts', fileName)).toString('base64');
      return `url('data:font/woff2;base64,${font}')`;
    },
  );
}

function inkBounds(image, region) {
  let top = image.height;
  let right = 0;
  let bottom = 0;
  let left = image.width;

  for (let y = 0; y < image.height; y += 1) {
    for (let x = Math.max(0, Math.floor(region.left)); x < Math.min(image.width, Math.ceil(region.right)); x += 1) {
      const offset = (image.width * y + x) * 4;
      const isInk = image.data[offset + 3] > 0
        && image.data[offset] < 100
        && image.data[offset + 1] < 100
        && image.data[offset + 2] < 100;
      if (!isInk) continue;
      top = Math.min(top, y);
      right = Math.max(right, x);
      bottom = Math.max(bottom, y);
      left = Math.min(left, x);
    }
  }

  assert.notEqual(top, image.height, 'Expected visible trust-metric ink');
  return { top, right, bottom, left, height: bottom - top + 1 };
}

async function metricsAt(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 400 } });
  try {
    await page.setContent(`
      <div class="home-hero__trust">
        <div><strong><b data-metric-part>ГИ</b> <b data-metric-part>35</b></strong><span>вместо 70</span></div>
        <div><strong>4,9</strong><span>1 200 отзывов</span></div>
      </div>
    `);
    await page.addStyleTag({ content: themeCssWithEmbeddedFonts() });
    await page.addStyleTag({ content: homeCss });
    await page.addStyleTag({ content: `
      html, body { margin: 0; background: #fff; }
      .home-hero__trust { position: static !important; width: max-content; }
      .home-hero__trust span { color: transparent !important; }
      .home-hero__trust [data-metric-part] { font: inherit; }
    ` });
    await page.evaluate(() => document.fonts.ready);

    const trust = page.locator('.home-hero__trust');
    const regions = await trust.locator(':scope > div').evaluateAll((items) => {
      const parent = items[0].parentElement.getBoundingClientRect();
      return items.map((item) => {
        const box = item.getBoundingClientRect();
        return { left: box.left - parent.left, right: box.right - parent.left };
      });
    });
    const partRegions = await trust.locator('[data-metric-part]').evaluateAll((items) => {
      const parent = items[0].closest('.home-hero__trust').getBoundingClientRect();
      return items.map((item) => {
        const box = item.getBoundingClientRect();
        return { left: box.left - parent.left, right: box.right - parent.left };
      });
    });
    const layout = await trust.evaluate((element) => ({
      fontSizes: Array.from(element.querySelectorAll(':scope > div > strong'), (item) => parseFloat(getComputedStyle(item).fontSize)),
      labelTops: Array.from(element.querySelectorAll(':scope > div > span'), (item) => item.getBoundingClientRect().top),
      labelFontSizes: Array.from(element.querySelectorAll(':scope > div > span'), (item) => parseFloat(getComputedStyle(item).fontSize)),
      labelTextTransforms: Array.from(element.querySelectorAll(':scope > div > span'), (item) => getComputedStyle(item).textTransform),
      labelLetterSpacings: Array.from(element.querySelectorAll(':scope > div > span'), (item) => getComputedStyle(item).letterSpacing),
    }));
    const image = PNG.sync.read(await trust.screenshot());
    return {
      groups: regions.map((region) => inkBounds(image, region)),
      firstParts: partRegions.map((region) => inkBounds(image, region)),
      layout,
    };
  } finally {
    await page.close();
  }
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const width of viewports) {
      const { groups: [first, second], firstParts: [letters, digits], layout } = await metricsAt(browser, width);
      results.push({ width, first, second, letters, digits, layout });
    }
    for (const { width, first, second, letters, digits, layout } of results) {
      assert.ok(
        Math.abs(letters.top - digits.top) <= 1 && Math.abs(letters.bottom - digits.bottom) <= 1,
        `${width}px: 35 must share the letter cap line and baseline; received ${JSON.stringify({ letters, digits })}`,
      );
      assert.ok(
        Math.abs(layout.fontSizes[0] - layout.fontSizes[1]) <= 0.01,
        `${width}px: trust values must use the same font size; received ${JSON.stringify(layout.fontSizes)}`,
      );
      assert.ok(
        Math.abs(first.top - second.top) <= 1,
        `${width}px: visible trust values must align vertically; received ${JSON.stringify({ first, second })}`,
      );
      assert.ok(
        Math.abs(layout.labelTops[0] - layout.labelTops[1]) <= 0.5,
        `${width}px: trust labels must align vertically; received ${JSON.stringify(layout.labelTops)}`,
      );
      assert.deepEqual(
        layout.labelTextTransforms,
        ['none', 'none'],
        `${width}px: trust labels must preserve their original case`,
      );
      assert.deepEqual(
        layout.labelLetterSpacings,
        ['normal', 'normal'],
        `${width}px: trust labels must use normal letter spacing`,
      );
      assert.ok(
        Math.abs(layout.labelFontSizes[0] - layout.labelFontSizes[1]) <= 0.01 && layout.labelFontSizes[0] >= 8.5,
        `${width}px: trust labels must share a readable responsive size; received ${JSON.stringify(layout.labelFontSizes)}`,
      );
    }
    const responsiveLabelSizes = results.map(({ layout }) => layout.labelFontSizes[0]);
    assert.ok(
      responsiveLabelSizes[1] >= responsiveLabelSizes[0] + 0.5
        && responsiveLabelSizes[2] >= responsiveLabelSizes[1] + 0.5,
      `Trust labels must scale from mobile to desktop; received ${JSON.stringify(responsiveLabelSizes)}`,
    );
  } finally {
    await browser.close();
  }

  console.log('Home hero trust metrics are optically aligned across responsive viewports');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
