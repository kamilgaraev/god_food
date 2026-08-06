const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const cases = {
  '/': { height: 4777, contactY: 3563.90625, contactHeight: 414, footerY: 3977.90625 },
  '/recipes/': { height: 2339, contactY: 1121, contactHeight: 419, footerY: 1540 },
  '/marketplace/': { height: 2593, contactY: 1380, contactHeight: 414, footerY: 1794 },
  '/buy/': { height: 2155, contactY: 942, contactHeight: 414, footerY: 1356 },
};

const closeEnough = (actual, expected, tolerance, label) => {
  assert.ok(Math.abs(actual - expected) <= tolerance, `${label}: expected ${expected}px, got ${actual}px`);
};

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const [route, expected] of Object.entries(cases)) {
      const context = await browser.newContext({ viewport: { width: 768, height: 1024 }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      await page.goto(`http://localhost:8080${route}`, { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const metrics = await page.evaluate(() => {
        const contact = document.querySelector('section.contact').getBoundingClientRect();
        const heading = document.querySelector('.contact-card > h2').getBoundingClientRect();
        const form = document.querySelector('.contact-card .form-grid').getBoundingClientRect();
        const consent = document.querySelector('.contact-card .consent').getBoundingClientRect();
        const submit = document.querySelector('.contact-card .form-submit .button').getBoundingClientRect();
        const footer = document.querySelector('.site-footer').getBoundingClientRect();
        return {
          height: document.documentElement.scrollHeight,
          scrollWidth: document.documentElement.scrollWidth,
          contactY: contact.y + scrollY,
          contactHeight: contact.height,
          heading: { x: heading.x, y: heading.y + scrollY, width: heading.width, height: heading.height },
          form: { x: form.x, y: form.y + scrollY, width: form.width, height: form.height },
          consent: { y: consent.y + scrollY, height: consent.height },
          submit: { x: submit.x, y: submit.y + scrollY, width: submit.width, height: submit.height },
          footerY: footer.y + scrollY,
        };
      });
      assert.equal(metrics.scrollWidth, 768, `${route}: horizontal overflow`);
      closeEnough(metrics.height, expected.height, 2, `${route} document height`);
      closeEnough(metrics.contactY, expected.contactY, 2, `${route} contact position`);
      closeEnough(metrics.contactHeight, expected.contactHeight, 2, `${route} contact height`);
      closeEnough(metrics.heading.x, 125, 2, `${route} contact heading x`);
      closeEnough(metrics.heading.y, expected.contactY + 40, 2, `${route} contact heading y`);
      closeEnough(metrics.heading.width, 519, 2, `${route} contact heading width`);
      closeEnough(metrics.heading.height, 44, 2, `${route} contact heading height`);
      closeEnough(metrics.form.x, 84, 2, `${route} contact form x`);
      closeEnough(metrics.form.y, expected.contactY + 123, 2, `${route} contact form y`);
      closeEnough(metrics.form.width, 600, 2, `${route} contact form width`);
      closeEnough(metrics.form.height, 132, 2, `${route} contact form height`);
      closeEnough(metrics.consent.y, expected.contactY + 284, 2, `${route} consent y`);
      closeEnough(metrics.consent.height, 20, 2, `${route} consent height`);
      closeEnough(metrics.submit.x, 274, 2, `${route} submit x`);
      closeEnough(metrics.submit.y, expected.contactY + 344, 2, `${route} submit y`);
      closeEnough(metrics.submit.width, 220, 2, `${route} submit width`);
      closeEnough(metrics.submit.height, 42, 2, `${route} submit height`);
      closeEnough(metrics.footerY, expected.footerY, 2, `${route} footer position`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
