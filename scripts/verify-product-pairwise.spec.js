const { chromium } = require('playwright');

const sourceUrl = process.env.PRODUCT_SOURCE_URL || 'https://theobroma.one/catalog/tproduct/281858081192-kakao-poroshok-naturalnii';
const localUrl = process.env.PRODUCT_LOCAL_URL || 'http://localhost:8080/product/theobroma-cacao-200/';
const viewportWidth = Number(process.env.PRODUCT_WIDTH || 390);
const isCacaoProduct = /\/product\/theobroma-cacao-/.test(localUrl);

async function waitForProduct(page, side) {
  await page.goto(side === 'source' ? sourceUrl : localUrl, {
    waitUntil: 'domcontentloaded',
    timeout: 45_000,
  });
  await page.waitForSelector(
    side === 'source' ? '.t-store__prod-popup__slider' : '.commerce-modal.is-open .product-detail-image',
    { state: 'visible', timeout: 20_000 },
  );
  await page.evaluate(() => document.fonts?.ready);
}

async function main() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: viewportWidth, height: 932 } });
    const source = await context.newPage();
    const local = await context.newPage();
    await waitForProduct(source, 'source');
    await waitForProduct(local, 'local');

    const sourceTopBar = await source.evaluate(() => {
      let element = document.elementFromPoint(195, 20);
      while (element) {
        const color = getComputedStyle(element).backgroundColor;
        if (color !== 'rgba(0, 0, 0, 0)') return color;
        element = element.parentElement;
      }
      return 'rgba(0, 0, 0, 0)';
    });
    const localTopBar = await local.locator('.commerce-modal[data-commerce-type="product"]').evaluate((element) => (
      getComputedStyle(element, '::before').backgroundColor
    ));
    const sourceCopyType = await source.evaluate(() => {
      const root = document.querySelector('.js-store-prod-all-text');
      const candidates = [root, ...root.querySelectorAll('*')]
        .filter((element) => element.textContent.replace(/\s+/g, ' ').trim().length > 30)
        .sort((left, right) => parseFloat(getComputedStyle(right).fontSize) - parseFloat(getComputedStyle(left).fontSize));
      const style = getComputedStyle(candidates[0]);
      return { fontSize: style.fontSize, lineHeight: style.lineHeight };
    });
    const localCopyType = await local.locator('.commerce-modal.is-open .product-detail-copy p').first().evaluate((element) => {
      const style = getComputedStyle(element);
      return { fontSize: style.fontSize, lineHeight: style.lineHeight };
    });
    const sourceDetailsType = await source.getByText('Описание продукта', { exact: true }).last().evaluate((element) => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return { fontSize: style.fontSize, lineHeight: style.lineHeight, y: rect.y, height: rect.height };
    });
    const localDetailsType = await local.locator('.commerce-modal.is-open .product-detail-accordions summary').first().evaluate((element) => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return { fontSize: style.fontSize, lineHeight: style.lineHeight, y: rect.y, height: rect.height };
    });
    const sourceDetailsCopyType = await source.evaluate(() => {
      const candidates = [...document.querySelectorAll('.t-store__tabs__item *')]
        .filter((element) => element.textContent.replace(/\s+/g, ' ').trim().startsWith('Состав:'))
        .sort((left, right) => left.textContent.length - right.textContent.length);
      const element = candidates[0];
      if (!element) return null;
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return { fontSize: style.fontSize, lineHeight: style.lineHeight, y: rect.y, height: rect.height };
    });
    const localDetailsCopyType = await local.locator('.commerce-modal.is-open .product-detail-accordions details > div').first().evaluate((element) => {
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return { fontSize: style.fontSize, lineHeight: style.lineHeight, y: rect.y, height: rect.height };
    });

    if (viewportWidth <= 460 && sourceTopBar !== 'rgb(74, 74, 74)') {
      throw new Error(`Unexpected source mobile product bar: ${sourceTopBar}`);
    }
    if (viewportWidth <= 460 && localTopBar !== sourceTopBar) {
      throw new Error(`Mobile product bar differs: source ${sourceTopBar}, local ${localTopBar}`);
    }
    if (viewportWidth <= 460 && JSON.stringify(localCopyType) !== JSON.stringify(sourceCopyType)) {
      throw new Error(`Mobile product copy typography differs: source ${JSON.stringify(sourceCopyType)}, local ${JSON.stringify(localCopyType)}`);
    }
    for (const key of ['fontSize', 'lineHeight']) {
      if (viewportWidth <= 460 && sourceDetailsType[key] !== localDetailsType[key]) {
        throw new Error(`Mobile product accordion title ${key} differs: source ${sourceDetailsType[key]}, local ${localDetailsType[key]}`);
      }
      if (viewportWidth <= 460 && sourceDetailsCopyType[key] !== localDetailsCopyType[key]) {
        throw new Error(`Mobile product accordion copy ${key} differs: source ${sourceDetailsCopyType[key]}, local ${localDetailsCopyType[key]}`);
      }
    }
    const sourceMetrics = await source.evaluate(() => Object.fromEntries([
      ['image', '.t-store__prod-popup__slider .t-slds__item_active .t-slds__bgimg'],
      ['summary', '.t-store__prod-popup__info'],
      ['copy', '.t-store__prod-popup__text'],
      ['accordions', '.js-store-tabs'],
      ['related', '.t-store__relevants__container'],
    ].map(([name, selector]) => {
      const element = document.querySelector(selector);
      if (!element) return [name, null];
      const rect = element.getBoundingClientRect();
      return [name, { x: rect.x, y: rect.y, width: rect.width, height: rect.height }];
    })));
    const localMetrics = await local.evaluate(() => Object.fromEntries([
      ['image', '.commerce-modal.is-open .product-detail-image'],
      ['summary', '.commerce-modal.is-open .product-detail-summary'],
      ['copy', '.commerce-modal.is-open .product-detail-copy'],
      ['accordions', '.commerce-modal.is-open .product-detail-accordions'],
      ['related', '.commerce-modal.is-open .product-related'],
    ].map(([name, selector]) => {
      const element = document.querySelector(selector);
      if (!element) return [name, null];
      const rect = element.getBoundingClientRect();
      return [name, { x: rect.x, y: rect.y, width: rect.width, height: rect.height }];
    })));

    const relatedVariant = viewportWidth <= 600 ? 'mobile' : (viewportWidth <= 900 ? 'tablet' : 'desktop');
    const sourceRelatedParts = {
      title: await source.locator('.t-store__relevants__title').boundingBox(),
      card: await source.locator('.js-product-relevant').first().boundingBox(),
      button: await source.locator('.js-product-relevant .js-store-prod-btn2').first().boundingBox(),
    };
    const localRelatedParts = {
      title: await local.locator('.commerce-modal.is-open .product-related > h2').boundingBox(),
      card: await local.locator(`.commerce-modal.is-open .product-related-grid-${relatedVariant} article`).first().boundingBox(),
      button: await local.locator(`.commerce-modal.is-open .product-related-grid-${relatedVariant} .product-related-button`).first().boundingBox(),
    };

    console.log(JSON.stringify({ source: sourceMetrics, local: localMetrics }));

    for (const key of ['x', 'y', 'width', 'height']) {
      if (viewportWidth <= 460 && Math.abs(sourceMetrics.image[key] - localMetrics.image[key]) > 0.5) {
        throw new Error(`Mobile product image ${key} differs: source ${sourceMetrics.image[key]}, local ${localMetrics.image[key]}`);
      }
    }
    for (const key of ['y', 'height']) {
      if (viewportWidth <= 460 && Math.abs(sourceMetrics.accordions[key] - localMetrics.accordions[key]) > 2.5) {
        throw new Error(`Mobile product accordion ${key} differs: source ${sourceMetrics.accordions[key]}, local ${localMetrics.accordions[key]}`);
      }
    }
    if (viewportWidth <= 460 && Math.abs(sourceMetrics.related.y - localMetrics.related.y) > 3) {
      throw new Error(`Mobile related-products start differs: source ${sourceMetrics.related.y}, local ${localMetrics.related.y}`);
    }
    if (viewportWidth <= 460) {
      for (const key of ['x', 'y', 'width']) {
        if (Math.abs(sourceRelatedParts.title[key] - localRelatedParts.title[key]) > 1) {
          throw new Error(`Mobile related-products title ${key} differs: source ${sourceRelatedParts.title[key]}, local ${localRelatedParts.title[key]}`);
        }
        if (Math.abs(sourceRelatedParts.card[key] - localRelatedParts.card[key]) > 1) {
          throw new Error(`Mobile related product card ${key} differs: source ${sourceRelatedParts.card[key]}, local ${localRelatedParts.card[key]}`);
        }
      }
      for (const key of ['width', 'height']) {
        if (Math.abs(sourceRelatedParts.button[key] - localRelatedParts.button[key]) > 1) {
          throw new Error(`Mobile related product button ${key} differs: source ${sourceRelatedParts.button[key]}, local ${localRelatedParts.button[key]}`);
        }
      }
    }
    if (viewportWidth >= 601 && viewportWidth <= 900) {
      for (const key of ['x', 'y', 'width', 'height']) {
        if (Math.abs(sourceMetrics.image[key] - localMetrics.image[key]) > 0.5) {
          throw new Error(`Tablet product image ${key} differs: source ${sourceMetrics.image[key]}, local ${localMetrics.image[key]}`);
        }
      }
      if (Math.abs(sourceMetrics.accordions.y - localMetrics.accordions.y) > 2) {
        throw new Error(`Tablet product accordion start differs: source ${sourceMetrics.accordions.y}, local ${localMetrics.accordions.y}`);
      }
      if (Math.abs(sourceMetrics.related.y - localMetrics.related.y) > 3) {
        throw new Error(`Tablet related-products start differs: source ${sourceMetrics.related.y}, local ${localMetrics.related.y}`);
      }
      for (const key of isCacaoProduct ? ['y'] : ['y', 'height']) {
        if (Math.abs(sourceMetrics.summary[key] - localMetrics.summary[key]) > 1.5) {
          throw new Error(`Tablet product summary ${key} differs: source ${sourceMetrics.summary[key]}, local ${localMetrics.summary[key]}`);
        }
      }
      for (const key of ['x', 'y', 'width']) {
        if (Math.abs(sourceRelatedParts.card[key] - localRelatedParts.card[key]) > 1) {
          throw new Error(`Tablet related product card ${key} differs: source ${sourceRelatedParts.card[key]}, local ${localRelatedParts.card[key]}`);
        }
      }
    }
    if (viewportWidth >= 901) {
      for (const key of ['x', 'y', 'width', 'height']) {
        if (Math.abs(sourceMetrics.image[key] - localMetrics.image[key]) > 0.5) {
          throw new Error(`Desktop product image ${key} differs: source ${sourceMetrics.image[key]}, local ${localMetrics.image[key]}`);
        }
      }
      for (const key of ['fontSize', 'lineHeight']) {
        if (sourceDetailsType[key] !== localDetailsType[key]) {
          throw new Error(`Desktop product accordion title ${key} differs: source ${sourceDetailsType[key]}, local ${localDetailsType[key]}`);
        }
        if (sourceDetailsCopyType[key] !== localDetailsCopyType[key]) {
          throw new Error(`Desktop product accordion copy ${key} differs: source ${sourceDetailsCopyType[key]}, local ${localDetailsCopyType[key]}`);
        }
      }
      if (Math.abs(sourceMetrics.accordions.y - localMetrics.accordions.y) > 1) {
        throw new Error(`Desktop product accordion start differs: source ${sourceMetrics.accordions.y}, local ${localMetrics.accordions.y}`);
      }
      if (Math.abs(sourceMetrics.related.y - localMetrics.related.y) > 3) {
        throw new Error(`Desktop related-products start differs: source ${sourceMetrics.related.y}, local ${localMetrics.related.y}`);
      }
      if (Math.abs(sourceMetrics.related.height - localMetrics.related.height) > 0.5) {
        throw new Error(`Desktop related-products height differs: source ${sourceMetrics.related.height}, local ${localMetrics.related.height}`);
      }
    }

    console.log(`PASS product mobile top bar: ${localTopBar}`);
    console.log(JSON.stringify({ sourceDetailsType, localDetailsType, sourceDetailsCopyType, localDetailsCopyType }));
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
