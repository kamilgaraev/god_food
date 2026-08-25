const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeRoot = path.resolve(__dirname, '../wp-content/themes/theobroma');
const homepage = fs.readFileSync(path.join(themeRoot, 'index.php'), 'utf8');
const script = fs.readFileSync(path.join(themeRoot, 'assets/js/homepage.js'), 'utf8');
const hero = homepage.match(/<section class="home-hero"[\s\S]*?<\/section>/)?.[0];

(async () => {
  assert(hero, 'Homepage hero must exist');
  assert.match(hero, /<h1[^>]*class="screen-reader-text"[^>]*>Абсолютно натуральный шоколад<\/h1>/,
    'The visual wordmark must be replaced by an accessible page heading');

  const pyramidMarkup = hero.match(/<button class="home-chocolate-pyramid"[\s\S]*?<\/button>/)?.[0];
  assert(pyramidMarkup, 'Hero must contain the interactive chocolate pyramid');
  assert.equal((pyramidMarkup.match(/class="home-chocolate-pyramid__piece/g) || []).length, 7,
    'The pyramid must contain seven independently animated chocolate pieces');

  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(`<main>${hero.replace(/<\?php[\s\S]*?\?>/g, '#')}</main>`);
    await page.addScriptTag({ content: script });

    const pyramid = page.locator('.home-chocolate-pyramid');
    await pyramid.click();
    assert.equal(await pyramid.getAttribute('data-state'), 'collapsed',
      'Clicking the pyramid must start the collapse');
    assert.equal(await pyramid.getAttribute('aria-busy'), 'true',
      'The pyramid must expose its animation state to assistive technology');

    await page.waitForFunction(() => document.querySelector('.home-chocolate-pyramid')?.dataset.state === 'reassembling', null, { timeout: 4500 });
    await page.waitForFunction(() => document.querySelector('.home-chocolate-pyramid')?.dataset.state === 'idle', null, { timeout: 3000 });
    assert.equal(await pyramid.getAttribute('aria-busy'), 'false',
      'The pyramid must automatically return to its idle state');

    const reducedPage = await browser.newPage({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    await reducedPage.setContent(`<main>${hero.replace(/<\?php[\s\S]*?\?>/g, '#')}</main>`);
    await reducedPage.addScriptTag({ content: script });
    const reducedPyramid = reducedPage.locator('.home-chocolate-pyramid');
    await reducedPyramid.click();
    assert.notEqual(await reducedPyramid.getAttribute('data-state'), 'collapsed',
      'Reduced-motion users must not see the pieces jump to their collapsed positions');
    await reducedPage.close();
  } finally {
    await browser.close();
  }

  console.log('Hero chocolate pyramid collapses on click and automatically reassembles');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
