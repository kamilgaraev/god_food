const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const base = process.env.CORPORATE_BASE_URL || 'http://localhost:8080';
const widths = [320, 390, 768, 1440, 2560];
const output = path.resolve(__dirname, '../output/playwright/corporate-redesign');

(async () => {
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.goto(`${base}/corporate-gifts/`, { waitUntil: 'networkidle' });
      const cookie = page.getByRole('button', { name: /Ок, не показывать снова/i });
      if (await cookie.isVisible()) await cookie.click();
      await page.evaluate(async () => {
        document.querySelectorAll('.corporate-redesign img[loading="lazy"]').forEach(image => { image.loading = 'eager'; });
        await Promise.all(Array.from(document.querySelectorAll('.corporate-redesign img[src]')).map(image => image.decode()));
        await document.fonts.ready;
      });
      assert.equal(await page.locator('[data-cg-gift]').count(), 5);
      assert.equal(await page.locator('.cg-detail-grid article').count(), 4);
      assert.equal(await page.locator('.cg-faq details').count(), 8);
      const layout = await page.evaluate(() => {
        const heading = document.querySelector('.cg-hero h1 em').getBoundingClientRect();
        const intro = document.querySelector('.cg-intro').getBoundingClientRect();
        return { width: innerWidth, documentWidth: document.documentElement.scrollWidth, headingRight: heading.right, headingBottom: heading.bottom, introTop: intro.top };
      });
      assert.ok(layout.documentWidth <= width + 1, `${width}: document overflow`);
      assert.ok(layout.headingRight <= width, `${width}: clipped hero title`);
      assert.ok(layout.headingBottom <= layout.introTop, `${width}: overlapping hero copy`);
      await page.screenshot({ path: path.join(output, `${width}.png`), fullPage: true });

      const trigger = page.locator('[data-cg-open="0"]').last();
      const dialog = page.locator('.cg-dialog');
      await trigger.click();
      assert.ok(await dialog.isVisible());
      assert.equal(await dialog.locator('h2').textContent(), '«Знакомство»');
      for (let index = 0; index < 4; index++) {
        await page.keyboard.press('Tab');
        assert.ok(await dialog.evaluate(element => element.contains(document.activeElement)), 'focus stays inside modal');
      }
      await page.keyboard.press('Escape');
      await dialog.waitFor({ state: 'hidden' });
      assert.ok(await trigger.evaluate(element => element === document.activeElement), 'Escape restores trigger focus');
      await trigger.click();
      await page.mouse.click(1, 1);
      await dialog.waitFor({ state: 'hidden' });
      await trigger.click();
      await dialog.getByRole('button', { name: 'Закрыть просмотр набора' }).click();
      await dialog.waitFor({ state: 'hidden' });
      await trigger.click();
      await dialog.getByRole('button', { name: 'Заказать', exact: true }).click();
      await dialog.waitFor({ state: 'hidden' });
      assert.equal(await page.locator('[name="custom[gift]"]').inputValue(), 'Знакомство');
      await page.locator('[data-cg-form] input[name="name"]').fill('');
      assert.equal(await page.locator('[data-cg-form]').evaluate(form => form.checkValidity()), false, 'empty required fields cannot submit');
      const faq = page.locator('.cg-faq details').nth(1);
      await faq.locator('summary').focus();
      await page.keyboard.press('Enter');
      assert.ok(await faq.evaluate(element => element.open), 'FAQ opens by keyboard');
      await page.keyboard.press('Enter');
      assert.equal(await faq.evaluate(element => element.open), false);
      const next = page.getByRole('button', { name: 'Следующие фотографии' });
      if (await next.isVisible() && await next.isEnabled()) {
        const track = page.locator('.cg-gallery-track');
        await next.click();
        await page.waitForFunction(() => document.querySelector('.cg-gallery-track').scrollLeft > 0);
        assert.ok(await track.evaluate(element => element.scrollLeft > 0), 'gallery advances');
      }
      const requestCatalog = page.locator('[data-cg-request]');
      if (await requestCatalog.count()) {
        await requestCatalog.click();
        assert.equal(await page.locator('[name="custom[gift]"]').inputValue(), 'Каталог PDF');
      }
      assert.deepEqual(errors, [], `${width}: browser errors`);
      await page.close();
      console.log(`Corporate redesign: ${width}px passed`);
    }
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
