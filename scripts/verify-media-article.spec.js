const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const url = process.env.THEOBROMA_URL || 'http://localhost:8080/kak-vybrat-nastoyashchiy-shokolad-dlya-rebenka/';
const cases = [
  { width: 390, height: 844, coverRatio: 4 / 3, productColumns: 1 },
  { width: 768, height: 1024, coverRatio: 16 / 9, productColumns: 2 },
  { width: 1440, height: 1000, coverRatio: 16 / 9, productColumns: 3 },
];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const testCase of cases) {
      const page = await browser.newPage({ viewport: testCase });
      await page.goto(url, { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);

      assert.equal(await page.locator('.site-header').count(), 1, `${testCase.width}px: shared header is missing`);
      assert.equal(await page.locator('.site-footer').count(), 1, `${testCase.width}px: shared footer is missing`);
      assert.equal(await page.locator('.media-article-header').count(), 0, `${testCase.width}px: legacy article bar must be removed`);
      assert.equal(await page.locator('.media-article-kicker').count(), 0, `${testCase.width}px: editorial byline must not clutter the article hero`);
      assert.equal(await page.locator('.media-article-products').count(), 1, `${testCase.width}px: related products section is missing`);
      assert.equal(await page.locator('.media-article-products [data-product-modal-link]').count(), 6, `${testCase.width}px: product card links are incomplete`);
      assert.equal(await page.locator('.media-article-related article').count(), 3, `${testCase.width}px: three related articles are required`);
      assert.equal(await page.evaluate(() => [...document.querySelectorAll('.media-article-related a[href]')]
        .some((link) => new URL(link.href).pathname === location.pathname)), false, `${testCase.width}px: current article must not be recommended`);

      const metrics = await page.evaluate(() => {
        const cover = document.querySelector('.media-article-cover').getBoundingClientRect();
        const copy = document.querySelector('.media-article-copy').getBoundingClientRect();
        const copyStyle = getComputedStyle(document.querySelector('.media-article-copy'));
        const hero = document.querySelector('.media-article-hero').getBoundingClientRect();
        const source = document.querySelector('.media-article-source');
        const products = document.querySelector('.media-article-products');
        const productGrid = document.querySelector('.media-article-products-grid');
        const coverStyle = getComputedStyle(document.querySelector('.media-article-cover'));
        const relatedStyle = getComputedStyle(document.querySelector('.media-article-related-grid article'));
        const sourceStyle = getComputedStyle(source);
        const materialsLinkStyle = getComputedStyle(document.querySelector('.media-article-meta a'));
        return {
          scrollWidth: document.documentElement.scrollWidth,
          coverRatio: cover.width / cover.height,
          copyWidth: copy.width,
          copyFontSize: parseFloat(copyStyle.fontSize),
          copyLineHeight: parseFloat(copyStyle.lineHeight),
          heroCentered: Math.abs((hero.left + hero.width / 2) - innerWidth / 2),
          sourceBeforeProducts: Boolean(source.compareDocumentPosition(products) & Node.DOCUMENT_POSITION_FOLLOWING),
          productColumns: getComputedStyle(productGrid).gridTemplateColumns.split(' ').length,
          coverRadius: parseFloat(coverStyle.borderTopLeftRadius),
          relatedRadius: parseFloat(relatedStyle.borderTopLeftRadius),
          sourceRadius: parseFloat(sourceStyle.borderTopLeftRadius),
          materialsLetterSpacing: parseFloat(materialsLinkStyle.letterSpacing) || 0,
          materialsTextTransform: materialsLinkStyle.textTransform,
        };
      });

      assert.equal(metrics.scrollWidth, testCase.width, `${testCase.width}px: horizontal overflow`);
      assert.ok(Math.abs(metrics.coverRatio - testCase.coverRatio) < 0.02, `${testCase.width}px: incorrect cover ratio`);
      assert.ok(metrics.copyWidth <= 740, `${testCase.width}px: reading measure is too wide`);
      assert.ok(metrics.copyLineHeight / metrics.copyFontSize >= 1.7, `${testCase.width}px: article line-height is too tight`);
      assert.ok(metrics.heroCentered < 1, `${testCase.width}px: article hero must be centered`);
      assert.equal(metrics.sourceBeforeProducts, true, `${testCase.width}px: source must precede related content`);
      assert.equal(metrics.productColumns, testCase.productColumns, `${testCase.width}px: related product grid is not centered and complete`);
      assert.ok(metrics.coverRadius >= 16, `${testCase.width}px: article cover needs soft rounding`);
      assert.ok(metrics.relatedRadius >= 12, `${testCase.width}px: related article cards need rounding`);
      assert.ok(metrics.sourceRadius >= 20, `${testCase.width}px: source action must retain the site's pill shape`);
      assert.ok(Math.abs(metrics.materialsLetterSpacing) < 0.2, `${testCase.width}px: all-materials link must use normal text spacing`);
      assert.equal(metrics.materialsTextTransform, 'none', `${testCase.width}px: all-materials link must keep natural casing`);
      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log('Media article editorial layout verified at 390, 768 and 1440px.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
