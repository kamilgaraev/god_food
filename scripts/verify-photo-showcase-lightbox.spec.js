const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  try {
    const landscape = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%221600%22 height=%22900%22%3E%3Crect width=%221600%22 height=%22900%22 fill=%22%23ded0bd%22/%3E%3C/svg%3E';
    await page.setContent(`
      <section data-photo-showcase>
        <button type="button" data-photo-lightbox-trigger data-photo-src="${landscape}" data-photo-alt="Первое фото" data-photo-caption="Первая подпись">Первое</button>
        <button type="button" data-photo-lightbox-trigger data-photo-src="${landscape}#two" data-photo-alt="Второе фото" data-photo-caption="Вторая подпись">Второе</button>
        <div class="theobroma-photo-lightbox" data-photo-lightbox hidden aria-hidden="true" role="dialog" aria-modal="true">
          <button class="theobroma-photo-lightbox__backdrop" type="button" data-photo-lightbox-close>Закрыть</button>
          <div class="theobroma-photo-lightbox__panel">
          <button class="theobroma-photo-lightbox__close" type="button" data-photo-lightbox-close>Закрыть</button>
          <button class="theobroma-photo-lightbox__nav theobroma-photo-lightbox__nav--previous" type="button" data-photo-lightbox-previous>Назад</button>
          <figure><img data-photo-lightbox-image alt=""><figcaption data-photo-lightbox-caption></figcaption></figure>
          <button class="theobroma-photo-lightbox__nav theobroma-photo-lightbox__nav--next" type="button" data-photo-lightbox-next>Вперёд</button>
          </div>
        </div>
      </section>
    `);
    await page.addStyleTag({ path: path.join(root, 'wp-content/plugins/theobroma-photo-showcases/assets/frontend.css') });
    await page.addScriptTag({ path: path.join(root, 'wp-content/plugins/theobroma-photo-showcases/assets/frontend.js') });

    const viewer = page.locator('[data-photo-lightbox]');
    if (!await viewer.evaluate((element) => element.parentElement === document.body)) throw new Error('Viewer remains trapped inside the clipped showcase section.');
    await page.locator('[data-photo-lightbox-trigger]').first().click();
    if (await viewer.getAttribute('aria-hidden') !== 'false') throw new Error('Viewer did not open.');
    if (!await page.locator('html').evaluate((element) => element.classList.contains('theobroma-photo-lightbox-open'))) throw new Error('Open viewer did not lock the document.');
    const selectedSource = await page.locator('[data-photo-lightbox-image]').getAttribute('src');
    if (selectedSource !== landscape) throw new Error(`Viewer did not show the selected full image: ${selectedSource}.`);
    if (await page.locator('[data-photo-lightbox-counter]').count() !== 0) throw new Error('Viewer still exposes a visible image counter.');
    await page.locator('[data-photo-lightbox-image]').evaluate((element) => element.decode());
    const geometry = await page.evaluate(() => {
      const image = document.querySelector('[data-photo-lightbox-image]').getBoundingClientRect();
      const previous = document.querySelector('[data-photo-lightbox-previous]').getBoundingClientRect();
      const next = document.querySelector('[data-photo-lightbox-next]').getBoundingClientRect();
      return {
        imageWidth: image.width,
        previousCenter: previous.left + previous.width / 2,
        nextCenter: next.left + next.width / 2,
        imageLeft: image.left,
        imageRight: image.right,
      };
    });
    if (geometry.imageWidth < 0.85 * 1440) throw new Error(`Viewer image is still too small: ${geometry.imageWidth}px.`);
    if (Math.abs(geometry.previousCenter - geometry.imageLeft) > 70 || Math.abs(geometry.nextCenter - geometry.imageRight) > 70) {
      throw new Error('Viewer arrows are not aligned with the image edges.');
    }
    const previousStyle = await page.locator('[data-photo-lightbox-previous]').evaluate((element) => {
      const style = getComputedStyle(element);
      return { color: style.color, backgroundColor: style.backgroundColor };
    });
    if (previousStyle.color !== 'rgb(52, 52, 52)' || previousStyle.backgroundColor !== 'rgba(251, 247, 241, 0.94)') {
      throw new Error(`Detached viewer lost the site palette: ${JSON.stringify(previousStyle)}.`);
    }

    await page.keyboard.press('ArrowRight');
    if (await page.locator('[data-photo-lightbox-image]').getAttribute('src') !== `${landscape}#two`) throw new Error('ArrowRight did not advance the viewer.');
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
