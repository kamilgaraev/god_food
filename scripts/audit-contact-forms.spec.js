const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';
const outputDir = path.resolve(__dirname, '..', 'output', 'playwright', 'contact-forms');
const cases = [
  { route: '/', selector: '.contact form', corporate: false },
  { route: '/cooperation/', selector: '.cooperation-form form', corporate: false },
  { route: '/corporate-gifts/', selector: '.corporate-gifts-request form', corporate: true },
];

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const testCase of cases) {
      const context = await browser.newContext({ viewport: { width: 390, height: 932 } });
      const page = await context.newPage();
      await page.goto(new URL(testCase.route, baseUrl).href, { waitUntil: 'networkidle' });
      const form = page.locator(testCase.selector);
      await form.waitFor();

      for (const name of ['action', 'theobroma_contact_nonce', 'theobroma_form_started', 'theobroma_website', 'name', 'phone', 'consent']) {
        assert.equal(await form.locator(`[name="${name}"]`).count(), 1, `${testCase.route}: missing ${name}`);
      }
      assert.equal(await form.locator('[name="action"]').inputValue(), 'theobroma_contact');
      assert.match(await form.getAttribute('action'), /\/wp-admin\/admin-post\.php$/);
      assert.equal(await form.locator('[name="theobroma_website"]').getAttribute('tabindex'), '-1');
      if (testCase.corporate) {
        assert.equal(await form.locator('[name="request_type"]').inputValue(), 'corporate_gift');
        assert.equal(await form.locator('[name="email"]').getAttribute('required'), '');
      }

      await form.locator('[name="name"]').fill('Антиспам аудит');
      await form.locator('[name="phone"]').fill('+7 999 000-00-00');
      if (testCase.corporate) await form.locator('[name="email"]').fill('forms-audit@example.com');
      await form.locator('[name="consent"]').check();
      await form.locator('[name="theobroma_form_started"]').evaluate((input) => { input.value = String(Math.floor(Date.now() / 1000)); });
      await Promise.all([
        page.waitForURL((url) => url.searchParams.get('contact') === 'error'),
        form.evaluate((element) => element.submit()),
      ]);
      assert.equal(new URL(page.url()).searchParams.get('contact'), 'error', `${testCase.route}: fast submission was accepted`);
      results.push({ route: testCase.route, fastSubmission: 'rejected' });
      await context.close();
      console.log(`PASS ${testCase.route}: fields and minimum-delay rejection`);
    }

    const context = await browser.newContext({ viewport: { width: 390, height: 932 } });
    const page = await context.newPage();
    await page.goto(new URL('/cooperation/', baseUrl).href, { waitUntil: 'networkidle' });
    const form = page.locator('.cooperation-form form');
    await form.locator('[name="name"]').fill('Антиспам аудит');
    await form.locator('[name="phone"]').fill('+7 999 000-00-00');
    await form.locator('[name="consent"]').check();
    await form.locator('[name="theobroma_form_started"]').evaluate((input) => { input.value = String(Math.floor(Date.now() / 1000) - 10); });
    await form.locator('[name="theobroma_website"]').fill('bot.example');
    await Promise.all([
      page.waitForURL((url) => url.searchParams.get('contact') === 'error'),
      form.evaluate((element) => element.submit()),
    ]);
    assert.equal(new URL(page.url()).searchParams.get('contact'), 'error', 'Honeypot submission was accepted');
    results.push({ route: '/cooperation/', honeypotSubmission: 'rejected' });
    await context.close();
    console.log('PASS honeypot rejection');

    fs.writeFileSync(path.join(outputDir, 'report.json'), `${JSON.stringify(results, null, 2)}\n`);
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
