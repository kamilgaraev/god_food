const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = process.env.BASE_URL || 'http://localhost:8080';
const branchStyles = fs.readFileSync(path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css'), 'utf8');
const viewportWidths = [390, 768, 1280];
const catalogPaths = [
  '/catalog/',
  '/catalog/?product_group=chocolate-100g',
  '/catalog/?product_group=chocolate-30g',
  '/catalog/?product_group=cacao',
  '/catalog/?product_group=chia',
];
const injectSourceStyles = process.argv.includes('--inject-source-styles');

async function applySourceStyles(page) {
  if (injectSourceStyles) {
    await page.addStyleTag({ content: branchStyles });
  }
}

async function cardMetrics(page, selector) {
  return page.locator(selector).first().evaluate((card) => {
    const rect = (element) => {
      const bounds = element.getBoundingClientRect();
      return { width: bounds.width, height: bounds.height };
    };
    const style = (element, properties) => {
      const computed = getComputedStyle(element);
      return Object.fromEntries(properties.map((property) => [property, computed[property]]));
    };
    const imageLink = card.querySelector('.home-product-card__image');
    const image = imageLink.querySelector('img');
    const heading = card.querySelector('.home-product-card__heading');
    const title = heading.querySelector('h3');
    const price = card.querySelector('.home-product-card__price');
    const description = card.querySelector(':scope > p');
    const button = card.querySelector('.home-product-card__button');
    const grid = card.parentElement;

    return {
      cardClass: card.className,
      card: rect(card),
      cardStyle: style(card, ['display', 'flexDirection', 'minHeight', 'padding', 'paddingInline']),
      imageLink: rect(imageLink),
      imageLinkStyle: style(imageLink, ['aspectRatio', 'borderRadius']),
      image: rect(image),
      imageStyle: style(image, ['objectFit', 'margin']),
      headingStyle: style(heading, ['display', 'alignItems', 'justifyContent', 'gap', 'marginTop']),
      titleStyle: style(title, ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'textTransform', 'margin', 'padding']),
      priceStyle: style(price, ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'color']),
      descriptionStyle: style(description, ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'minHeight', 'margin']),
      buttonStyle: style(button, ['display', 'height', 'minHeight', 'marginTop', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'borderRadius']),
      grid: rect(grid),
      gridColumns: getComputedStyle(grid).gridTemplateColumns,
      gridStyle: style(grid, ['display', 'gap', 'gridAutoRows']),
      documentWidth: document.documentElement.scrollWidth,
      viewportWidth: document.documentElement.clientWidth,
    };
  });
}

function comparable(metrics) {
  return {
    cardStyle: metrics.cardStyle,
    imageLinkStyle: metrics.imageLinkStyle,
    imageStyle: metrics.imageStyle,
    headingStyle: metrics.headingStyle,
    titleStyle: metrics.titleStyle,
    priceStyle: metrics.priceStyle,
    descriptionStyle: metrics.descriptionStyle,
    buttonStyle: metrics.buttonStyle,
    gridStyle: metrics.gridStyle,
  };
}

function columnWidths(value) {
  return value.split(/\s+/).map((width) => Number.parseFloat(width));
}

function assertClose(actual, expected, message, tolerance = 0.1) {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${message} (${actual} vs ${expected}).`);
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    if (injectSourceStyles) {
      console.log('Product card parity: explicit source-styles injection mode.');
    }

    for (const width of viewportWidths) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
      try {
        await page.goto(baseUrl, { waitUntil: 'networkidle' });
        await applySourceStyles(page);
        const home = await cardMetrics(page, '#catalog .home-product-card');

        for (const catalogPath of catalogPaths) {
          const context = `${width}px ${catalogPath}`;
          await page.goto(new URL(catalogPath, baseUrl).href, { waitUntil: 'networkidle' });
          await applySourceStyles(page);
          const catalog = await cardMetrics(page, 'ul.products.home-product-grid > .home-product-card');

          assert.match(catalog.cardClass, /\bhome-product-card\b/, `${context} must render the canonical card class.`);
          assert.deepEqual(comparable(catalog), comparable(home), `${context} card computed styles must exactly match the homepage reference.`);
          assertClose(catalog.card.width, home.card.width, `${context} card width must match the homepage reference`);
          const oneTextLine = Number.parseFloat(home.titleStyle.lineHeight) + 0.5;
          assertClose(catalog.card.height, home.card.height, `${context} card height may differ by at most one content line`, oneTextLine);
          assertClose(catalog.grid.width, home.grid.width, `${context} grid width must match the homepage reference`);
          const catalogColumns = columnWidths(catalog.gridColumns);
          const homeColumns = columnWidths(home.gridColumns);
          assert.equal(catalogColumns.length, homeColumns.length, `${context} grid column count must match the homepage reference.`);
          catalogColumns.forEach((column, index) => assertClose(column, homeColumns[index], `${context} grid column ${index + 1} must match the homepage reference`));
          assertClose(catalog.imageLink.width, home.imageLink.width, `${context} image frame width must match the homepage reference`);
          assertClose(catalog.imageLink.height, home.imageLink.height, `${context} image frame height must match the homepage reference`);
          assertClose(catalog.image.width, home.image.width, `${context} image width must match the homepage reference`);
          assertClose(catalog.image.height, home.image.height, `${context} image height must match the homepage reference`);
          assert.ok(Math.abs(catalog.image.height - catalog.imageLink.height) <= 0.5, `${context} image must fill the canonical image frame without a blank strip.`);
          assert.ok(catalog.documentWidth <= catalog.viewportWidth, `${context} must not cause horizontal overflow.`);
        }
      } finally {
        await page.close();
      }
    }
  } finally {
    await browser.close();
  }

  console.log('Runtime product card parity verified.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
