const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = (process.env.THEOBROMA_URL || 'https://theobroma.uit-dev.ru').replace(/\/$/, '');
const css = fs.readFileSync(path.resolve(__dirname, '../wp-content/themes/theobroma/style.css'), 'utf8');
const widths = [390, 601, 953, 1440, 1949];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1100 }, reducedMotion: 'reduce' });
      await page.goto(`${baseUrl}/chocolate-samples/`, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.addStyleTag({ content: css });

      const metrics = await page.evaluate(() => {
        const rect = (selector) => {
          const element = document.querySelector(selector);
          const box = element.getBoundingClientRect();
          const style = getComputedStyle(element);
          return {
            top: box.top + scrollY,
            right: box.right,
            bottom: box.bottom + scrollY,
            left: box.left,
            width: box.width,
            position: style.position,
            color: style.color,
            background: style.backgroundColor,
            borderWidth: style.borderTopWidth,
            borderRadius: style.borderRadius,
          };
        };

        return {
          viewportWidth: document.documentElement.clientWidth,
          documentWidth: document.documentElement.scrollWidth,
          page: rect('.samples-page'),
          pageOverflow: {
            clientWidth: document.querySelector('.samples-page').clientWidth,
            scrollWidth: document.querySelector('.samples-page').scrollWidth,
          },
          breadcrumb: rect('.samples-breadcrumb'),
          copy: rect('.samples-hero-copy'),
          heading: rect('.samples-hero h1'),
          lead: rect('.samples-lead'),
          promises: rect('.samples-promises'),
          promise: rect('.samples-promises li'),
          form: rect('.samples-form-section'),
          formCard: rect('.samples-form-card'),
          steps: rect('.samples-steps'),
          stepCards: [...document.querySelectorAll('.samples-steps-grid article')].map((card) => ({
            clientWidth: card.clientWidth,
            scrollWidth: card.scrollWidth,
          })),
        };
      });

      assert.notEqual(metrics.heading.position, 'absolute', `${width}px: global h1 rule pulls the samples heading out of flow`);
      assert.ok(metrics.copy.top - metrics.breadcrumb.bottom <= 96, `${width}px: excessive gap before page content`);
      assert.ok(metrics.heading.bottom <= metrics.lead.top + 1, `${width}px: heading overlaps the lead`);
      assert.ok(metrics.lead.bottom <= metrics.promises.top + 1, `${width}px: lead overlaps the promises`);
      assert.equal(metrics.page.background, 'rgb(252, 249, 247)', `${width}px: page must use the shared paper color`);
      assert.equal(metrics.form.background, 'rgb(243, 235, 228)', `${width}px: form must use the shared cream card color`);
      assert.equal(metrics.form.color, 'rgb(36, 29, 25)', `${width}px: form must use the shared ink color`);
      assert.equal(metrics.promise.background, 'rgba(0, 0, 0, 0)', `${width}px: promise list must not use custom pill backgrounds`);
      assert.equal(metrics.promise.borderWidth, '0px', `${width}px: promise list must match native bullet lists`);
      assert.ok(metrics.formCard.left >= metrics.form.left - 1 && metrics.formCard.right <= metrics.form.right + 1, `${width}px: form card escapes its section`);
      assert.ok(metrics.steps.top >= metrics.form.bottom, `${width}px: steps overlap the form`);
      assert.ok(metrics.pageOverflow.scrollWidth <= metrics.pageOverflow.clientWidth + 1, `${width}px: samples page content overflows horizontally`);
      metrics.stepCards.forEach((card, index) => {
        assert.ok(card.scrollWidth <= card.clientWidth + 1, `${width}px: step card ${index + 1} content overflows`);
      });
      assert.equal(metrics.documentWidth, metrics.viewportWidth, `${width}px: page is horizontally clipped`);

      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log(`Chocolate samples layout verified at ${widths.join(', ')}px`);
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
