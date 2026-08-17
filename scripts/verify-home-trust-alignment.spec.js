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
        <div><strong>ГИ 35</strong><span>вместо 70</span></div>
        <div><strong>4,9</strong><span>1 200 отзывов</span></div>
      </div>
    `);
    await page.addStyleTag({ content: themeCssWithEmbeddedFonts() });
    await page.addStyleTag({ content: homeCss });
    await page.addStyleTag({ content: `
      html, body { margin: 0; background: #fff; }
      .home-hero__trust { position: static !important; width: max-content; }
      .home-hero__trust span { color: transparent !important; }
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
    const image = PNG.sync.read(await trust.screenshot());
    return regions.map((region) => inkBounds(image, region));
  } finally {
    await page.close();
  }
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const width of viewports) {
      const [first, second] = await metricsAt(browser, width);
      results.push({ width, first, second });
    }
    for (const { width, first, second } of results) {
      assert.ok(
        Math.abs(first.height - second.height) <= 1,
        `${width}px: visible trust values must have equal heights; received ${JSON.stringify({ first, second })}`,
      );
      assert.ok(
        Math.abs(first.top - second.top) <= 1,
        `${width}px: visible trust values must align vertically; received ${JSON.stringify({ first, second })}`,
      );
      assert.ok(
        Math.abs(first.bottom - second.bottom) <= 1,
        `${width}px: visible trust values must share a bottom edge; received ${JSON.stringify({ first, second })}`,
      );
    }
  } finally {
    await browser.close();
  }

  console.log('Home hero trust metrics are optically aligned across responsive viewports');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
