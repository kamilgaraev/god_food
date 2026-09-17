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
      const clippedDetails = await page.locator('.cg-detail-grid article').evaluateAll(cards => cards.some(card => {
        const heading = card.querySelector('h3');
        const range = document.createRange();
        range.selectNodeContents(heading);
        const text = range.getBoundingClientRect();
        const box = card.getBoundingClientRect();
        return text.right > box.right || text.left < box.left || card.scrollWidth > card.clientWidth;
      }));
      assert.equal(clippedDetails, false, `${width}: detail headings stay inside cards`);
      if (width <= 600) {
        assert.ok(await page.locator('.cg-request .form-grid input,.cg-request .form-grid select,.cg-request .form-grid textarea').evaluateAll(fields => fields.every(field => parseFloat(getComputedStyle(field).fontSize) >= 16)), 'mobile inputs avoid focus zoom');
        assert.match(await page.locator('#cg-request-title').textContent(), /заявку\s+мы свяжемся\s+в течение дня/);
      }
      const fidelity = await page.evaluate(() => {
        const address = document.querySelector('.cg-request address');
        return {
          photos: Array.from(document.querySelectorAll('.cg-gallery-track img')).map(image => ({ src: image.currentSrc, width: image.naturalWidth, height: image.naturalHeight })),
          fontRatio: parseFloat(getComputedStyle(address).fontSize) / parseFloat(getComputedStyle(document.documentElement).fontSize),
          contactOverflow: address.scrollWidth > address.clientWidth,
          animation: getComputedStyle(document.querySelector('.cg-ribbon-track')).animationName,
        };
      });
      assert.equal(fidelity.photos.length, 2, 'exactly the two Figma gallery frames');
      assert.match(fidelity.photos[0].src, /gallery-chocolate-original[^/]*\.jpg/);
      assert.match(fidelity.photos[1].src, /gallery-packaging-original[^/]*\.jpg/);
      assert.deepEqual(fidelity.photos.map(photo => [photo.width, photo.height]), [[2731, 4096], [2723, 4096]], 'original image resolution');
      assert.ok(Math.abs(fidelity.fontRatio - (width <= 600 ? 1.375 : 1.5)) < .01, 'contacts scale with the site');
      assert.equal(fidelity.contactOverflow, false, 'contacts fit their card');
      assert.equal(fidelity.animation, 'none', 'reduced motion respected');
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
      assert.ok(await dialog.evaluate(element => {
        const text = document.createRange();
        text.selectNodeContents(element.querySelector('h2'));
        return text.getBoundingClientRect().right <= element.querySelector('.cg-dialog-close').getBoundingClientRect().left;
      }), 'modal title does not overlap close button');
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
    const motionPage = await browser.newPage({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'no-preference', deviceScaleFactor: 2 });
    await motionPage.goto(`${base}/corporate-gifts/`, { waitUntil: 'networkidle' });
    const ribbon = motionPage.locator('.cg-ribbon-track');
    const start = await ribbon.evaluate(element => getComputedStyle(element).transform);
    await motionPage.waitForTimeout(300);
    assert.notEqual(await ribbon.evaluate(element => getComputedStyle(element).transform), start, 'ribbon moves');
    assert.ok(await ribbon.evaluate(element => Math.abs(element.getBoundingClientRect().width / 4 - element.firstElementChild.getBoundingClientRect().width) < 1), 'repeat distance equals one group');
    await motionPage.locator('.cg-ribbon').hover();
    assert.equal(await ribbon.evaluate(element => getComputedStyle(element).animationPlayState), 'paused');
    await motionPage.mouse.move(1, 1);
    assert.equal(await ribbon.evaluate(element => getComputedStyle(element).animationPlayState), 'running');
    await motionPage.locator('.cg-detail-grid article').last().scrollIntoViewIfNeeded();
    await motionPage.waitForFunction(() => Array.from(document.querySelectorAll('.cg-detail-grid article')).some(card => card.getAnimations().length > 0));
    await motionPage.emulateMedia({ reducedMotion:'reduce' });
    await motionPage.waitForFunction(() => document.querySelector('.corporate-redesign').getAnimations({ subtree:true }).length === 0);
    assert.equal(await motionPage.locator('.cg-detail-grid article').last().evaluate(card => getComputedStyle(card).opacity), '1');
    await motionPage.close();
    console.log('Marquee motion, seamless repeat, pause and resume passed (DPR 2)');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
