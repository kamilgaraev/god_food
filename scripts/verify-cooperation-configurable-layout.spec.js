const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');

const baseUrl = (process.env.THEOBROMA_URL || 'https://theobroma.uit-dev.ru').replace(/\/$/, '');
const cssFile = process.env.THEOBROMA_CSS_FILE || '';
const widths = [390, 631, 953, 1440];

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
      await page.goto(`${baseUrl}/cooperation/`, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.evaluate(() => document.fonts.ready);
      if (cssFile) await page.addStyleTag({ content: fs.readFileSync(cssFile, 'utf8') });

      await page.locator('.cooperation-form .form-grid').evaluate((grid) => {
        for (let index = 0; index < 5; index += 1) {
          const field = document.createElement('input');
          field.type = 'text';
          field.className = index === 2 ? 'message-field' : 'generated-field';
          field.placeholder = `Generated field ${index + 1}`;
          grid.appendChild(field);
        }
      });

      const metrics = await page.evaluate(() => {
        const rect = (selector) => {
          const box = document.querySelector(selector).getBoundingClientRect();
          return { top: box.top + scrollY, right: box.right, bottom: box.bottom + scrollY, left: box.left, width: box.width };
        };
        const form = rect('.cooperation-form form');
        return {
          viewportWidth: document.documentElement.clientWidth,
          documentWidth: document.documentElement.scrollWidth,
          section: rect('.cooperation-form'),
          form,
          fields: [...document.querySelectorAll('.cooperation-form .form-grid > *')].map((field) => {
            const box = field.getBoundingClientRect();
            return { left: box.left, right: box.right };
          }),
          submit: rect('.cooperation-form .form-submit'),
          benefits: rect('.cooperation-benefits'),
        };
      });

      const leftInset = metrics.form.left - metrics.section.left;
      const rightInset = metrics.section.right - metrics.form.right;
      assert.ok(Math.abs(leftInset - rightInset) <= 2, `${width}px: form insets differ (${leftInset}px vs ${rightInset}px)`);
      assert.ok(metrics.section.bottom >= metrics.submit.bottom + 16, `${width}px: configurable fields overflow the form background`);
      assert.ok(metrics.benefits.top >= metrics.section.bottom, `${width}px: benefits overlap the configurable form`);
      assert.equal(metrics.documentWidth, metrics.viewportWidth, `${width}px: cooperation page is horizontally clipped`);
      metrics.fields.forEach((field, index) => {
        assert.ok(field.left >= metrics.form.left - 1 && field.right <= metrics.form.right + 1, `${width}px: field ${index + 1} escapes the form`);
      });

      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log(`Configurable cooperation form verified at ${widths.join(', ')}px`);
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
