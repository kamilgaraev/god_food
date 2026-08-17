const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeRoot = path.resolve(__dirname, '..', 'wp-content', 'themes', 'theobroma');
const styles = [
  fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8'),
  fs.readFileSync(path.join(themeRoot, 'assets', 'css', 'home-redesign.css'), 'utf8'),
].join('\n');

async function assertHoverKeepsVerticalPosition(page, selector) {
  const button = page.locator(selector);
  const before = await button.boundingBox();

  await button.hover();
  await page.waitForTimeout(250);

  const after = await button.boundingBox();
  assert.ok(before && after, `${selector}: button bounds are unavailable`);
  assert.equal(after.y, before.y, `${selector}: hover moved the button vertically`);
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({ viewport: { width: 800, height: 600 } });
    await page.setContent(`
      <style>${styles}</style>
      <main>
        <a class="button" href="#general">General button</a>
        <a class="home-button" href="#home">Home button</a>
      </main>
    `);

    await assertHoverKeepsVerticalPosition(page, '.button');
    await page.mouse.move(0, 0);
    await page.waitForTimeout(250);
    await assertHoverKeepsVerticalPosition(page, '.home-button');

    console.log('Button hover position contract verified');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
