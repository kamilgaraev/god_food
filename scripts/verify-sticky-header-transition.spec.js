const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const cssPath = path.join(root, 'wp-content/themes/theobroma/style.css');
const scriptPath = path.join(root, 'wp-content/themes/theobroma/assets/js/site-header.js');
const css = fs.readFileSync(cssPath, 'utf8');

assert.match(css, /\.nav-sticky \.nav\s*\{[^}]*animation:\s*theobroma-sticky-nav-in\s+(?:4\d\d|5\d\d)ms/s, 'sticky nav enters with a 400–599ms transition');
assert.match(css, /@keyframes\s+theobroma-sticky-nav-in\s*\{[\s\S]*?translate:\s*0\s+-\d+%[\s\S]*?translate:\s*0\s+0/s, 'sticky nav animation moves smoothly from above');
assert.match(css, /@media\s*\(prefers-reduced-motion:reduce\)[\s\S]*?\.nav-sticky \.nav\s*\{[^}]*animation:\s*none/s, 'sticky animation respects reduced-motion preferences');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'no-preference' });
      await page.setContent('<header class="site-header"><div class="shipping"></div><nav class="nav"><div class="nav-links"></div><a class="brand">Theobroma</a><div class="nav-links"></div></nav><div class="floating-actions"></div></header><main style="height:3000px"></main>');
      await page.addStyleTag({ path: cssPath });
      await page.addScriptTag({ path: scriptPath });
      await page.evaluate(() => scrollTo(0, 100));
      await page.waitForTimeout(40);
      const early = await page.locator('.nav').evaluate((nav) => {
        const animation = nav.getAnimations()[0];
        return { duration: animation?.effect?.getTiming().duration, currentTime: animation?.currentTime, translate: getComputedStyle(nav).translate };
      });
      await page.waitForTimeout(120);
      const later = await page.locator('.nav').evaluate((nav) => ({ currentTime: nav.getAnimations()[0]?.currentTime, translate: getComputedStyle(nav).translate }));
      assert.equal(early.duration, 480, `${width}px: sticky animation duration matches the smooth transition contract`);
      assert.ok(later.currentTime > early.currentTime, `${width}px: sticky animation progresses over time`);
      assert.notEqual(later.translate, early.translate, `${width}px: sticky header moves continuously instead of snapping`);
      await page.close();
    }

    const reducedPage = await browser.newPage({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await reducedPage.setContent('<body class="nav-sticky"><nav class="nav"></nav></body>');
    await reducedPage.addStyleTag({ path: cssPath });
    assert.equal(await reducedPage.locator('.nav').evaluate((nav) => getComputedStyle(nav).animationName), 'none');
    await reducedPage.close();
  } finally {
    await browser.close();
  }
  console.log('Sticky header transition verified in Chromium');
})().catch((error) => { console.error(error); process.exitCode = 1; });
