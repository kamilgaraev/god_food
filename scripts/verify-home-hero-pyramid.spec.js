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

function overlapArea(a, b) {
  if (!overlaps(a, b)) return 0;
  return (Math.min(a.right, b.right) - Math.max(a.left, b.left))
    * (Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
}

function renderHeroDocument() {
  return `<body class="home"><main>${renderHeroAssets(hero)}</main><style>${styles}</style></body>`;
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
    await page.setContent(renderHeroDocument());
    await page.addScriptTag({ content: script });

    const pyramid = page.locator('.home-chocolate-pyramid');
    await pyramid.click();
    assert.equal(await pyramid.getAttribute('data-state'), 'anticipating',
      'Clicking the pyramid must start with a short anticipation beat');
    assert.equal(await pyramid.getAttribute('aria-busy'), 'true',
      'The pyramid must expose its animation state to assistive technology');
    await pyramid.click();
    assert.equal(await pyramid.getAttribute('data-state'), 'anticipating',
      'Repeated clicks must not restart an animation already in progress');

    await page.waitForFunction(() => document.querySelector('.home-chocolate-pyramid')?.dataset.state === 'collapsed', null, { timeout: 800 });
    await page.waitForFunction(() => document.querySelector('.home-chocolate-pyramid')?.dataset.state === 'reassembling', null, { timeout: 4200 });
    await page.waitForTimeout(2250);
    assert.equal(await pyramid.getAttribute('data-state'), 'reassembling',
      'The pyramid must stay busy until the last delayed piece has finished reassembling');
    await page.waitForFunction(() => document.querySelector('.home-chocolate-pyramid')?.dataset.state === 'idle', null, { timeout: 3000 });
    assert.equal(await pyramid.getAttribute('aria-busy'), 'false',
      'The pyramid must automatically return to its idle state');

    const reducedPage = await browser.newPage({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    await reducedPage.setContent(renderHeroDocument());
    await reducedPage.addScriptTag({ content: script });
    const reducedPyramid = reducedPage.locator('.home-chocolate-pyramid');
    await reducedPyramid.click();
    assert.notEqual(await reducedPyramid.getAttribute('data-state'), 'collapsed',
      'Reduced-motion users must not see the pieces jump to their collapsed positions');
    await reducedPage.close();

    for (const width of [320, 390, 600, 601, 768, 1199, 1200, 1440, 2560, 3200]) {
      const layoutPage = await browser.newPage({ viewport: { width, height: 900 } });
      await layoutPage.setContent(renderHeroDocument());
      const scale = await layoutPage.evaluate(() => ({
        heroHeight: document.querySelector('.home-hero').getBoundingClientRect().height,
        pyramidWidth: document.querySelector('.home-chocolate-pyramid').getBoundingClientRect().width,
        fallDurationMs: parseFloat(getComputedStyle(document.querySelector('.home-chocolate-pyramid__piece')).transitionDuration) * 1000,
      }));
      const minimumPyramidWidth = width <= 600
        ? Math.min(285, width * 0.72)
        : (width < 1200 ? 300 : Math.min(650, width * 0.34));
      const minimumHeroHeight = width <= 600 ? 480 : (width < 1200 ? 448 : 430);
      assert(scale.pyramidWidth >= minimumPyramidWidth,
        `${width}px pyramid must be visually dominant (expected at least ${minimumPyramidWidth}px, got ${scale.pyramidWidth}px)`);
      assert(scale.heroHeight >= minimumHeroHeight,
        `${width}px hero must provide enough stage height for the larger pyramid (expected at least ${minimumHeroHeight}px, got ${scale.heroHeight}px)`);
      assert(scale.fallDurationMs >= 1350 && scale.fallDurationMs <= 1600,
        `${width}px collapse must remain weighty and readable (expected 1350-1600ms, got ${scale.fallDurationMs}ms)`);
      await layoutPage.locator('.home-chocolate-pyramid').evaluate((node) => { node.dataset.state = 'collapsed'; });
      for (const [elapsed, wait] of [[450, 450], [1000, 550], [1550, 550]]) {
        await layoutPage.waitForTimeout(wait);
        const geometry = await layoutPage.evaluate(() => ({
          pieces: Array.from(document.querySelectorAll('.home-chocolate-pyramid__piece'), (node) => node.getBoundingClientRect().toJSON()),
          content: Array.from(document.querySelectorAll('.home-hero__lead > p, .home-hero__actions a, .home-hero__trust > div'), (node) => node.getBoundingClientRect().toJSON()),
        }));
        const collisions = geometry.pieces.flatMap((piece, pieceIndex) => geometry.content.flatMap((content, contentIndex) => (
          overlaps(piece, content) ? [{ piece: pieceIndex + 1, content: contentIndex + 1, area: overlapArea(piece, content) }] : []
        )));
        assert.deepEqual(collisions, [], `${width}px chocolate pieces must not overlap hero content at ${elapsed}ms of collapse`);
      }

      if (width === 1440) {
        const assetMetrics = await layoutPage.evaluate(async () => {
          const images = Array.from(document.querySelectorAll('.home-chocolate-pyramid__piece img'));
          await Promise.all(images.map((image) => image.decode()));

          const metrics = images.map((image) => {
            const canvas = document.createElement('canvas');
            canvas.width = image.naturalWidth;
            canvas.height = image.naturalHeight;
            const context = canvas.getContext('2d', { willReadFrequently: true });
            context.drawImage(image, 0, 0);
            const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
            const visited = new Uint8Array(canvas.width * canvas.height);
            let meaningfulComponents = 0;
            let greenSpillPixels = 0;

            for (let index = 0; index < pixels.length; index += 4) {
              const red = pixels[index];
              const green = pixels[index + 1];
              const blue = pixels[index + 2];
              const alpha = pixels[index + 3];
              if (alpha >= 20 && green > red * 1.3 && green > blue * 1.3) greenSpillPixels += 1;
            }

            for (let start = 0; start < visited.length; start += 1) {
              if (visited[start] || pixels[start * 4 + 3] < 20) continue;
              visited[start] = 1;
              const queue = [start];
              let area = 0;

              for (let cursor = 0; cursor < queue.length; cursor += 1) {
                const current = queue[cursor];
                area += 1;
                const x = current % canvas.width;
                const neighbors = [
                  current - canvas.width,
                  current + canvas.width,
                  x > 0 ? current - 1 : -1,
                  x + 1 < canvas.width ? current + 1 : -1,
                ];
                for (const neighbor of neighbors) {
                  if (neighbor < 0 || neighbor >= visited.length || visited[neighbor]
                    || pixels[neighbor * 4 + 3] < 20) continue;
                  visited[neighbor] = 1;
                  queue.push(neighbor);
                }
              }

              if (area >= 100) meaningfulComponents += 1;
            }

            return {
              components: meaningfulComponents,
              dimensions: [image.naturalWidth, image.naturalHeight],
              declaredDimensions: [Number(image.getAttribute('width')), Number(image.getAttribute('height'))],
              greenSpillPixels,
            };
          });
          return {
            componentCounts: metrics.map(({ components }) => components),
            dimensions: metrics.map(({ dimensions }) => dimensions),
            declaredDimensions: metrics.map(({ declaredDimensions }) => declaredDimensions),
            greenSpillPixels: metrics.map(({ greenSpillPixels }) => greenSpillPixels),
          };
        });
        assert.deepEqual(assetMetrics.componentCounts, [1, 1, 1, 1, 1, 1, 1],
          `Each chocolate asset must contain one connected piece without floating fragments (got ${assetMetrics.componentCounts.join(', ')})`);
        assert.deepEqual(assetMetrics.dimensions.map((dimensions) => Math.max(...dimensions)), Array(7).fill(420),
          `The redesigned chocolate pieces must use consistently detailed 420px cutouts (got ${assetMetrics.dimensions.map((dimensions) => dimensions.join('x')).join(', ')})`);
        assert.deepEqual(assetMetrics.declaredDimensions, assetMetrics.dimensions,
          'Hero image width and height attributes must match the generated cutouts');
        assert.deepEqual(assetMetrics.greenSpillPixels, [0, 0, 0, 0, 0, 0, 0],
          `Chocolate cutouts must not retain chroma-key spill (got ${assetMetrics.greenSpillPixels.join(', ')})`);
      }
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
