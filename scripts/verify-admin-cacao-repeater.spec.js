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
