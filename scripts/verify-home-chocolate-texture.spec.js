const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const ROOT = path.resolve(__dirname, '..');
const HOME_CSS = path.join(ROOT, 'wp-content/themes/theobroma/assets/css/home-redesign.css');
const TEXTURE = path.join(ROOT, 'wp-content/themes/theobroma/assets/images/chocolate-texture.webp');
const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
];

function colorDistance(image, background, index) {
  return Math.abs(image[index] - background[index])
    + Math.abs(image[index + 1] - background[index + 1])
    + Math.abs(image[index + 2] - background[index + 2]);
}

function missingInkRatio(actualBuffer, referenceBuffer, backgroundBuffer, bottomBandStart) {
  const actual = PNG.sync.read(actualBuffer);
  const reference = PNG.sync.read(referenceBuffer);
  const background = PNG.sync.read(backgroundBuffer);
  let referenceInk = 0;
  let missingInk = 0;
  for (let y = bottomBandStart; y < actual.height; y += 1) {
    for (let x = 0; x < actual.width; x += 1) {
      const index = (y * actual.width + x) * 4;
      if (colorDistance(reference.data, background.data, index) < 120) continue;
      referenceInk += 1;
      if (colorDistance(actual.data, background.data, index) < 40) missingInk += 1;
    }
  }

  if (referenceInk === 0) throw new Error('Reference heading contains no measurable bottom ink');
  return { referenceInk, missingInk, ratio: missingInk / referenceInk };
}

async function headingCaptureArea(page) {
  return page.locator('.home-hero h1').evaluate((heading) => {
    const rect = heading.getBoundingClientRect();
    const style = getComputedStyle(heading);
    const fontSize = parseFloat(style.fontSize);
    const paddingBottom = parseFloat(style.paddingBottom);
    return {
      clip: {
        x: 0,
        y: Math.max(0, Math.floor(rect.top)),
        width: document.documentElement.clientWidth,
        height: Math.ceil(rect.height + fontSize * 0.18),
      },
      bottomBandStart: Math.floor((rect.height - paddingBottom) * 0.72),
    };
  });
}

async function run() {
  const css = fs.readFileSync(HOME_CSS, 'utf8');
  const texture = fs.readFileSync(TEXTURE);
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const viewport of VIEWPORTS) {
      const page = await browser.newPage({ viewport, reducedMotion: 'reduce' });
      await page.route('**/home-redesign.css*', (route) => route.fulfill({ contentType: 'text/css', body: css }));
      await page.route('**/chocolate-texture.webp*', (route) => route.fulfill({ contentType: 'image/webp', body: texture }));
      await page.goto(BASE_URL, { waitUntil: 'networkidle' });
      await page.addStyleTag({ content: '.cookie-notice { display:none !important; }' });
      await page.evaluate(() => document.fonts.ready);

      const captureArea = await headingCaptureArea(page);
      const actual = await page.screenshot({ clip: captureArea.clip });
      await page.addStyleTag({ content: '.home-hero h1 { background:none !important; color:#542719 !important; -webkit-text-fill-color:#542719 !important; -webkit-text-stroke:0 !important; filter:none !important; }' });
      const reference = await page.screenshot({ clip: captureArea.clip });
      await page.addStyleTag({ content: '.home-hero h1 { visibility:hidden !important; }' });
      const background = await page.screenshot({ clip: captureArea.clip });
      const result = missingInkRatio(actual, reference, background, captureArea.bottomBandStart);

      console.log(`${viewport.name}: missing ${result.missingInk}/${result.referenceInk} bottom-ink pixels (${(result.ratio * 100).toFixed(2)}%)`);
      if (result.ratio > 0.015) {
        throw new Error(`${viewport.name} textured heading clips ${(result.ratio * 100).toFixed(2)}% of the reference glyph ink near the baseline`);
      }
      await page.close();
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
