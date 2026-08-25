const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  try {
    await page.setContent(`
      <section data-photo-showcase>
        <button type="button" data-photo-lightbox-trigger data-photo-src="https://example.test/one.jpg" data-photo-alt="Первое фото" data-photo-caption="Первая подпись">Первое</button>
        <button type="button" data-photo-lightbox-trigger data-photo-src="https://example.test/two.jpg" data-photo-alt="Второе фото" data-photo-caption="Вторая подпись">Второе</button>
        <div data-photo-lightbox hidden aria-hidden="true" role="dialog" aria-modal="true">
          <button type="button" data-photo-lightbox-close>Закрыть</button>
          <button type="button" data-photo-lightbox-previous>Назад</button>
          <figure><img data-photo-lightbox-image alt=""><figcaption data-photo-lightbox-caption></figcaption></figure>
          <span data-photo-lightbox-counter></span>
          <button type="button" data-photo-lightbox-next>Вперёд</button>
        </div>
      </section>
    `);
    await page.addScriptTag({ path: path.join(root, 'wp-content/plugins/theobroma-photo-showcases/assets/frontend.js') });

    const viewer = page.locator('[data-photo-lightbox]');
    if (!await viewer.evaluate((element) => element.parentElement === document.body)) throw new Error('Viewer remains trapped inside the clipped showcase section.');
    await page.locator('[data-photo-lightbox-trigger]').first().click();
    if (await viewer.getAttribute('aria-hidden') !== 'false') throw new Error('Viewer did not open.');
    if (!await page.locator('html').evaluate((element) => element.classList.contains('theobroma-photo-lightbox-open'))) throw new Error('Open viewer did not lock the document.');
    if (await page.locator('[data-photo-lightbox-image]').getAttribute('src') !== 'https://example.test/one.jpg') throw new Error('Viewer did not show the selected full image.');
    if (await page.locator('[data-photo-lightbox-counter]').textContent() !== '1 / 2') throw new Error('Viewer counter is incorrect.');

    await page.keyboard.press('ArrowRight');
    if (await page.locator('[data-photo-lightbox-image]').getAttribute('src') !== 'https://example.test/two.jpg') throw new Error('ArrowRight did not advance the viewer.');
    if (await page.locator('[data-photo-lightbox-caption]').textContent() !== 'Вторая подпись') throw new Error('Viewer caption did not follow the image.');

    await page.keyboard.press('Escape');
    if (await viewer.getAttribute('aria-hidden') !== 'true' || !await viewer.isHidden()) throw new Error('Escape did not close the viewer.');
    if (!await page.locator('[data-photo-lightbox-trigger]').first().evaluate((element) => element === document.activeElement)) throw new Error('Closing the viewer did not restore focus.');

    process.stdout.write('Photo showcase viewer opens, navigates and closes accessibly.\n');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exit(1);
});
