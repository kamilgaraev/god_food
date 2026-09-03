const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const script = fs.readFileSync(
  path.resolve(__dirname, '../wp-content/plugins/theobroma-admin-tools/assets/content-settings.js'),
  'utf8',
);

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    await page.route('https://example.test/**', (route) => route.fulfill({
      contentType: 'image/svg+xml',
      body: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20" fill="gold"/></svg>',
    }));
    await page.setContent(`
      <div data-cacao-profiles data-next-index="1">
        <div data-cacao-profile-list>
          <fieldset data-cacao-profile-row>
            <input name="settings[cacao_profiles][0][percentage]" value="70">
            <button type="button" data-remove-cacao-profile>Удалить</button>
          </fieldset>
        </div>
        <button type="button" data-add-cacao-profile>Добавить процент</button>
        <template data-cacao-profile-template>
          <fieldset data-cacao-profile-row>
            <input name="settings[cacao_profiles][__INDEX__][percentage]" value="">
            <input name="settings[cacao_profiles][__INDEX__][label]" value="">
            <div data-cacao-image>
              <input type="url" data-cacao-image-url>
              <img data-cacao-image-preview hidden>
              <button type="button" data-select-cacao-image>Выбрать изображение</button>
              <button type="button" data-clear-cacao-image>Сбросить</button>
            </div>
            <button type="button" data-remove-cacao-profile>Удалить</button>
          </fieldset>
        </template>
      </div>
    `);
    await page.addScriptTag({ content: script });

    await page.locator('[data-add-cacao-profile]').click();
    assert.equal(await page.locator('[data-cacao-profile-row]').count(), 2, 'Add button must append a profile row');
    assert.equal(
      await page.locator('[data-cacao-profile-row]').nth(1).locator('input').first().getAttribute('name'),
      'settings[cacao_profiles][1][percentage]',
      'New profile fields must receive a unique settings index',
    );

    const customUrl = 'https://example.test/custom-cacao.jpg';
    const imageInput = page.locator('[data-cacao-image-url]');
    const preview = page.locator('[data-cacao-image-preview]');
    await imageInput.fill(customUrl);
    assert.equal(await preview.getAttribute('src'), customUrl, 'Typing an image URL updates the preview');
    await preview.waitFor({ state: 'visible' });
    assert.equal(await preview.isVisible(), true);
    await page.locator('[data-clear-cacao-image]').click();
    assert.equal(await imageInput.inputValue(), '', 'Reset clears the override');
    assert.equal(await preview.getAttribute('src'), null);
    assert.equal(await preview.isVisible(), false);
    assert.equal(await imageInput.evaluate((element) => element === document.activeElement), true);

    await page.evaluate(() => {
      window.wp = { media: (options) => {
        if (options.library.type !== 'image' || options.multiple !== false) throw new Error('Image-only selection required');
        let select;
        return {
          on: (event, callback) => { if (event === 'select') select = callback; },
          state: () => ({ get: () => ({ first: () => ({ toJSON: () => ({ url: 'https://example.test/original.jpg', sizes: { large: { url: 'https://example.test/large.jpg' } } }) }) }) }),
          open: () => select(),
        };
      } };
    });
    await page.locator('[data-select-cacao-image]').click();
    assert.equal(await imageInput.inputValue(), 'https://example.test/large.jpg', 'Media selection writes the large image URL');
    assert.equal(await preview.getAttribute('src'), 'https://example.test/large.jpg');

    await page.locator('[data-cacao-profile-row]').first().locator('[data-remove-cacao-profile]').click();
    assert.equal(await page.locator('[data-cacao-profile-row]').count(), 1, 'Remove button must delete only its profile row');
  } finally {
    await browser.close();
  }
  console.log('Dynamic cacao profile controls verified');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
