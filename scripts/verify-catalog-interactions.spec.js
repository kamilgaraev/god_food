const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const themeRoot = path.join(root, 'wp-content', 'themes', 'theobroma');
const style = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8');
const homeStyle = fs.readFileSync(path.join(themeRoot, 'assets', 'css', 'home-redesign.css'), 'utf8');
const catalogScriptPath = path.join(themeRoot, 'assets', 'js', 'catalog-filters.js');
const catalogScript = fs.existsSync(catalogScriptPath) ? fs.readFileSync(catalogScriptPath, 'utf8') : '';

function catalogHtml(group, label) {
  return `<!doctype html><html><body>
    <main class="shop-page catalog-page catalog-group-${group}">
      <div class="shop-shell">
        <nav class="catalog-filters" aria-label="Категории товаров">
          <a class="${group === 'chocolate-200g' ? 'is-active' : ''}" href="https://example.test/catalog/">Шоколад 200г</a>
          <a class="${group === 'chocolate-100g' ? 'is-active' : ''}" href="https://example.test/catalog/?product_group=chocolate-100g">Шоколад 100г</a>
        </nav>
        <ul class="products">
          <li class="product"><a class="woocommerce-loop-product__link" href="/product/${group}"><span class="catalog-product-image"><img alt="${label}"></span><h2>${label}</h2></a></li>
        </ul>
        <article class="home-product-card">
          <a class="home-product-card__image" href="/product/home"><img alt="Товар на главной"></a>
        </article>
      </div>
    </main>
  </body></html>`;
}

function scaleFromTransform(transform) {
  if (transform === 'none') return 1;
  const match = transform.match(/^matrix\(([^,]+)/);
  return match ? Number.parseFloat(match[1]) : Number.NaN;
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  let page;

  try {
    page = await browser.newPage({ viewport: { width: 1510, height: 900 } });
    page.setDefaultTimeout(5000);
    let delayNextCatalogFetch = false;
    let releaseCatalogFetch = () => {};
    await page.route('https://example.test/catalog/**', async (route) => {
      if (!['document', 'fetch'].includes(route.request().resourceType())) {
        await route.abort('blockedbyclient');
        return;
      }
      const url = new URL(route.request().url());
      const group = url.searchParams.get('product_group') || 'chocolate-200g';
      const label = group === 'chocolate-100g' ? 'Товар 100г' : 'Товар 200г';
      if (delayNextCatalogFetch && route.request().resourceType() === 'fetch') {
        delayNextCatalogFetch = false;
        await new Promise((resolve) => { releaseCatalogFetch = resolve; });
      }
      await route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: catalogHtml(group, label) });
    });

    await page.goto('https://example.test/catalog/');
    await page.addStyleTag({ content: `${style}\n${homeStyle}` });

    const productLink = page.locator('.woocommerce-loop-product__link').first();
    const productImageFrame = productLink.locator('.catalog-product-image');
    const productImage = productLink.locator('img');
    const frameBeforeHover = await productImageFrame.boundingBox();
    assert.equal(await productImageFrame.evaluate((frame) => getComputedStyle(frame).overflow), 'hidden', 'catalog image animation must be clipped inside a fixed frame');
    await productLink.focus();
    assert.equal(await productLink.evaluate((link) => getComputedStyle(link).outlineStyle), 'none', 'catalog card link must not show a focus outline');
    await productLink.hover();
    await page.waitForTimeout(100);
    const earlyScale = scaleFromTransform(await productImage.evaluate((image) => getComputedStyle(image).transform));
    assert.ok(earlyScale > 1 && earlyScale < 1.04, `hover must ease into scale(1.04), received ${earlyScale}`);
    await page.waitForTimeout(700);
    const finalScale = scaleFromTransform(await productImage.evaluate((image) => getComputedStyle(image).transform));
    assert.ok(Math.abs(finalScale - 1.04) < 0.001, `hover must finish at scale(1.04), received ${finalScale}`);
    assert.deepEqual(await productImageFrame.boundingBox(), frameBeforeHover, 'catalog image frame must stay fixed during the animation');

    const homeProductLink = page.locator('.home-product-card__image');
    const homeFrameBeforeHover = await homeProductLink.boundingBox();
    await homeProductLink.focus();
    assert.equal(await homeProductLink.evaluate((link) => getComputedStyle(link).outlineStyle), 'none', 'homepage card image link must not show a focus outline');
    await homeProductLink.hover();
    await page.waitForTimeout(400);
    assert.ok(Math.abs(scaleFromTransform(await homeProductLink.locator('img').evaluate((image) => getComputedStyle(image).transform)) - 1.025) < 0.001, 'homepage image must animate inside its frame');
    const homeFrameAfterHover = await homeProductLink.boundingBox();
    assert.deepEqual(
      { width: homeFrameAfterHover.width, height: homeFrameAfterHover.height },
      { width: homeFrameBeforeHover.width, height: homeFrameBeforeHover.height },
      'homepage image frame must stay fixed during the animation',
    );

    if (catalogScript) await page.addScriptTag({ content: catalogScript });
    await page.evaluate(() => { window.__catalogDocumentMarker = 'same-document'; });
    let navigationRequests = 0;
    page.on('request', (request) => {
      if (request.isNavigationRequest() && request.frame() === page.mainFrame()) navigationRequests += 1;
    });

    await page.evaluate(() => {
      window.__catalogMotionSamples = [];
      document.addEventListener('theobroma:catalog-updated', () => {
        const products = document.querySelector('.catalog-page ul.products');
        const style = getComputedStyle(products);
        window.__catalogMotionSamples.push({ opacity: style.opacity, transform: style.transform });
      });
    });
    delayNextCatalogFetch = true;
    await page.getByRole('link', { name: 'Шоколад 100г' }).click();
    await page.locator('.catalog-page[aria-busy="true"]').waitFor();
    assert.equal(await page.getByRole('link', { name: 'Шоколад 100г' }).getAttribute('aria-current'), 'page', 'clicked filter must become active before the response arrives');
    assert.equal(await page.getByRole('link', { name: 'Шоколад 200г' }).getAttribute('aria-current'), null, 'previous filter must deactivate immediately');
    assert.match(await page.getByRole('link', { name: 'Шоколад 100г' }).evaluate((link) => getComputedStyle(link).transitionDuration), /0\.22s/, 'filter color transition must stay lightweight');
    releaseCatalogFetch();
    await page.getByText('Товар 100г').waitFor();
    await page.waitForFunction(() => window.__catalogMotionSamples.length === 1);
    assert.deepEqual(await page.evaluate(() => window.__catalogMotionSamples[0]), { opacity: '0', transform: 'matrix(1, 0, 0, 1, 0, 8)' }, 'new product grid must enter from a subtle offset');
    await page.waitForTimeout(350);
    assert.deepEqual(
      await page.locator('.catalog-page ul.products').evaluate((products) => {
        const style = getComputedStyle(products);
        return { opacity: style.opacity, transform: style.transform };
      }),
      { opacity: '1', transform: 'none' },
      'product grid must settle without a lingering transform',
    );
    assert.equal(navigationRequests, 0, 'catalog filter must update without a document navigation request');
    assert.equal(await page.evaluate(() => window.__catalogDocumentMarker), 'same-document');
    assert.equal(page.url(), 'https://example.test/catalog/?product_group=chocolate-100g');
    assert.equal(await page.locator('.catalog-filters .is-active').textContent(), 'Шоколад 100г');
    assert.equal(await page.locator('.catalog-filters .is-active').getAttribute('aria-current'), 'page');

    await page.emulateMedia({ reducedMotion: 'reduce' });
    assert.equal(await page.locator('.catalog-filters .is-active').evaluate((link) => getComputedStyle(link).transitionDuration), '0s');
    assert.equal(await page.locator('.catalog-page ul.products').evaluate((products) => getComputedStyle(products).transitionDuration), '0s');
    await page.emulateMedia({ reducedMotion: 'no-preference' });

    await page.evaluate(() => history.back());
    await page.getByText('Товар 200г').waitFor();
    assert.equal(navigationRequests, 0, 'browser history must restore the catalog without a document navigation request');
    assert.equal(await page.evaluate(() => window.__catalogDocumentMarker), 'same-document');
    assert.equal(page.url(), 'https://example.test/catalog/');

    delayNextCatalogFetch = true;
    await page.getByRole('link', { name: 'Шоколад 100г' }).click();
    await page.locator('.catalog-page[aria-busy="true"]').waitFor();
    await page.evaluate(() => {
      window.history.pushState({ theobromaModal: 'product' }, '', '/product/chocolate-200g');
      const dialog = document.createElement('div');
      dialog.className = 'commerce-modal is-open';
      dialog.setAttribute('role', 'dialog');
      dialog.tabIndex = -1;
      dialog.textContent = 'Карточка товара';
      document.body.append(dialog);
      dialog.focus();
    });
    releaseCatalogFetch();
    await page.locator('.catalog-page[aria-busy="true"]').waitFor({ state: 'detached' });

    assert.equal(page.url(), 'https://example.test/product/chocolate-200g');
    assert.deepEqual(await page.evaluate(() => window.history.state), { theobromaModal: 'product' });
    assert.equal(await page.evaluate(() => document.activeElement?.getAttribute('role')), 'dialog');
    assert.equal(await page.getByText('Товар 200г').isVisible(), true, 'late catalog response must not replace content behind a product modal');

  } finally {
    if (page) await page.close();
    await browser.close();
  }

  console.log('Catalog interaction contract verified');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
