const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeRoot = path.resolve(__dirname, '../wp-content/themes/theobroma');
const homepage = fs.readFileSync(path.join(themeRoot, 'index.php'), 'utf8');
const script = fs.readFileSync(path.join(themeRoot, 'assets/js/homepage.js'), 'utf8');
const hero = homepage.match(/<section class="home-hero"[\s\S]*?<\/section>/)?.[0];
const styles = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8')
  + fs.readFileSync(path.join(themeRoot, 'assets/css/home-redesign.css'), 'utf8');

function renderHeroAssets(markup) {
  return markup.replace(/src="<\?php[^>]*piece-(\d)\.webp[^>]*\?>"/g, (_match, piece) => {
    const image = fs.readFileSync(path.join(themeRoot, `assets/images/hero-chocolate-pieces/piece-${piece}.webp`));
    return `src="data:image/webp;base64,${image.toString('base64')}"`;
  }).replace(/<\?php[\s\S]*?\?>/g, '#');
}

function overlaps(a, b) {
  return a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
}

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
    await page.waitForTimeout(1620);
    assert.equal(await pyramid.getAttribute('data-state'), 'reassembling',
      'The pyramid must stay busy until the last delayed piece has finished reassembling');
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

    for (const width of [390, 1440, 2560]) {
      const layoutPage = await browser.newPage({ viewport: { width, height: 900 } });
      await layoutPage.setContent(`<body class="home"><main>${renderHeroAssets(hero)}</main><style>${styles}</style></body>`);
      await layoutPage.locator('.home-chocolate-pyramid').evaluate((node) => { node.dataset.state = 'collapsed'; });
      await layoutPage.waitForTimeout(1150);
      const geometry = await layoutPage.evaluate(() => ({
        pieces: Array.from(document.querySelectorAll('.home-chocolate-pyramid__piece'), (node) => node.getBoundingClientRect().toJSON()),
        content: Array.from(document.querySelectorAll('.home-hero__lead > p, .home-hero__actions a, .home-hero__trust > div'), (node) => node.getBoundingClientRect().toJSON()),
      }));
      const collisions = geometry.pieces.flatMap((piece, pieceIndex) => geometry.content.flatMap((content, contentIndex) => (
        overlaps(piece, content) ? [{ piece: pieceIndex + 1, content: contentIndex + 1 }] : []
      )));
      assert.deepEqual(collisions, [], `${width}px collapsed chocolate pieces must not overlap hero copy, CTAs, or trust metrics`);
      await layoutPage.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Hero chocolate pyramid collapses on click and automatically reassembles');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
