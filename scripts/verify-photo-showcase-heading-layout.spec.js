const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });

  try {
    await page.setContent(`
      <section class="theobroma-photo-showcase theobroma-photo-showcase--home">
        <header class="theobroma-photo-showcase__intro">
          <h2>Шоколад, который хочется рассмотреть ближе</h2>
          <p class="theobroma-photo-showcase__description">Живые фактуры, натуральные ингредиенты и ручная работа.</p>
        </header>
      </section>
      <section class="theobroma-photo-showcase theobroma-photo-showcase--corporate">
        <header class="theobroma-photo-showcase__intro">
          <h2>Подарки, которые запоминают</h2>
          <p class="theobroma-photo-showcase__description">От первого эскиза до готового набора.</p>
        </header>
      </section>
    `);
    await page.addStyleTag({ path: path.join(root, 'wp-content/plugins/theobroma-photo-showcases/assets/frontend.css') });

    const layouts = await page.locator('.theobroma-photo-showcase__intro').evaluateAll((headers) => headers.map((header) => {
      const title = header.querySelector('h2');
      const description = header.querySelector('p');
      const titleBox = title.getBoundingClientRect();
      const descriptionBox = description.getBoundingClientRect();
      return {
        display: getComputedStyle(header).display,
        titleTransform: getComputedStyle(title).textTransform,
        titleSize: parseFloat(getComputedStyle(title).fontSize),
        descriptionSize: parseFloat(getComputedStyle(description).fontSize),
        descriptionMarginTop: parseFloat(getComputedStyle(description).marginTop),
        alignedLeft: Math.abs(titleBox.left - descriptionBox.left) < 1,
        descriptionBelow: descriptionBox.top >= titleBox.bottom,
      };
    }));

    for (const layout of layouts) {
      if (layout.display !== 'block') throw new Error('Showcase intro still uses a split-column layout.');
      if (layout.titleTransform !== 'none') throw new Error('Showcase title is still forced to uppercase.');
      if (!layout.alignedLeft || !layout.descriptionBelow) throw new Error('Description is not aligned directly below the heading.');
      if (Math.abs(layout.titleSize - 55.68) > 0.2) throw new Error(`Heading does not match the cacao section scale: ${layout.titleSize}px.`);
      if (layout.descriptionSize !== 12 || layout.descriptionMarginTop !== 16) throw new Error('Description does not match the cacao section typography and spacing.');
    }

    process.stdout.write('Photo showcase headings match neighboring sections without uppercase or split copy.\n');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exit(1);
});
