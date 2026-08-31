const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = [
  { width: 390, height: 844, columns: 1 },
  { width: 768, height: 1024, columns: 2 },
  { width: 1440, height: 1000, columns: 3 },
];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const testCase of cases) {
      const page = await browser.newPage({ viewport: testCase });
      await page.goto('http://localhost:8080/media/', { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      await page.locator('.media-card').last().scrollIntoViewIfNeeded();
      await page.waitForFunction(() => [...document.querySelectorAll('.media-card-image img')].every((image) => image.complete && image.naturalWidth > 0));

      const metrics = await page.evaluate(() => {
        const grid = document.querySelector('.media-grid');
        const cards = [...document.querySelectorAll('.media-card')];
        const images = [...document.querySelectorAll('.media-card-image img')];
        const intro = document.querySelector('.media-intro').getBoundingClientRect();
        const firstHeading = document.querySelector('.media-card h2');
        const firstCardStyle = getComputedStyle(cards[0]);
        const firstImageStyle = getComputedStyle(document.querySelector('.media-card-image'));
        const firstArrowStyle = getComputedStyle(document.querySelector('.media-card-arrow'));
        return {
          scrollWidth: document.documentElement.scrollWidth,
          cardCount: cards.length,
          columnCount: getComputedStyle(grid).gridTemplateColumns.split(' ').length,
          introCentered: Math.abs((intro.left + intro.width / 2) - innerWidth / 2),
          headingFont: getComputedStyle(firstHeading).fontFamily,
          imageRatios: images.map((image) => image.getBoundingClientRect().width / image.getBoundingClientRect().height),
          imageSources: images.map((image) => image.currentSrc),
          intrinsicSizes: images.map((image) => ({ width: image.naturalWidth, height: image.naturalHeight })),
          loading: images.map((image) => image.loading),
          links: document.querySelectorAll('.media-card > .media-card-link').length,
          cardRadius: parseFloat(firstCardStyle.borderTopLeftRadius),
          cardBorderWidth: parseFloat(firstCardStyle.borderTopWidth),
          imageRadius: parseFloat(firstImageStyle.borderTopLeftRadius),
          arrowRadius: parseFloat(firstArrowStyle.borderTopLeftRadius),
        };
      });

      assert.equal(metrics.scrollWidth, testCase.width, `${testCase.width}px: horizontal overflow`);
      assert.equal(metrics.cardCount, 4, `${testCase.width}px: expected four media cards`);
      assert.equal(metrics.columnCount, testCase.columns, `${testCase.width}px: incorrect editorial grid`);
      assert.ok(metrics.introCentered < 1, `${testCase.width}px: intro must be centered`);
      assert.match(metrics.headingFont, /Cormorant/i, `${testCase.width}px: card headings must use the editorial face`);
      assert.equal(metrics.links, metrics.cardCount, `${testCase.width}px: each card must be one clear link target`);
      assert.ok(metrics.cardRadius >= 16, `${testCase.width}px: cards need soft brand-consistent rounding`);
      assert.ok(metrics.cardBorderWidth >= 1, `${testCase.width}px: cards need a defined warm edge`);
      assert.ok(metrics.imageRadius >= 16, `${testCase.width}px: image frame must follow the card rounding`);
      assert.ok(metrics.arrowRadius >= 20, `${testCase.width}px: reading action must use the site's pill language`);
      metrics.imageRatios.forEach((ratio, index) => assert.ok(Math.abs(ratio - 4 / 3) < 0.01, `${testCase.width}px: image ${index + 1} must use a 4:3 frame`));
      metrics.intrinsicSizes.forEach((size, index) => {
        assert.equal(size.width / size.height, 4 / 3, `${testCase.width}px: image ${index + 1} must use the generated 4:3 derivative (${metrics.imageSources[index]})`);
        assert.ok(size.width <= 480, `${testCase.width}px: image ${index + 1} derivative is larger than required`);
      });
      assert.equal(metrics.loading[0], 'eager', `${testCase.width}px: first image should be prioritized`);
      metrics.loading.slice(1).forEach((loading, index) => assert.equal(loading, 'lazy', `${testCase.width}px: image ${index + 2} should load lazily`));
      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Media listing editorial layout verified at 390, 768 and 1440px.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
