const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const phoneScript = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'assets', 'js', 'phone-input.js');
const localChrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

(async () => {
  const browser = await chromium.launch({
    headless: true,
    ...(fs.existsSync(localChrome) ? { executablePath: localChrome } : {}),
  });
  const page = await browser.newPage();

  try {
    await page.setContent(`
      <form>
        <div class="phone-field">
          <span class="phone-flag" aria-hidden="true"></span>
          <span class="phone-triangle" aria-hidden="true"></span>
          <span class="phone-code" aria-hidden="true">+7</span>
          <input type="tel" name="phone" required>
        </div>
      </form>
    `);
    await page.addScriptTag({ path: phoneScript });

    const phone = page.locator('[name="phone"]');
    assert.equal(await phone.inputValue(), '+7', 'every phone input must start with the fixed +7 prefix');
    assert.equal(await phone.getAttribute('placeholder'), '+7 (000) 000-00-00');
    assert.equal(await phone.getAttribute('inputmode'), 'tel');
    assert.equal(await phone.getAttribute('autocomplete'), 'tel');
    assert.equal(await phone.getAttribute('maxlength'), '18');
    assert.equal(await page.locator('.phone-flag,.phone-triangle,.phone-code').count(), 0, 'legacy country controls must be removed');

    await phone.fill('89991234567');
    assert.equal(await phone.inputValue(), '+7 (999) 123-45-67', 'a number pasted with 8 must normalize to +7');

    await phone.evaluate((input) => {
      input.value = '+79991234567';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    assert.equal(await phone.inputValue(), '+7 (999) 123-45-67', 'a compact +7 number must be formatted');

    await phone.focus();
    await phone.evaluate((input) => input.setSelectionRange(9, 9));
    await page.keyboard.press('Backspace');
    assert.equal(await phone.inputValue(), '+7 (991) 234-56-7', 'Backspace at a separator must delete a digit instead of getting stuck');

    await phone.evaluate((input) => input.setSelectionRange(input.value.length, input.value.length));
    for (let index = 0; index < 9; index += 1) {
      await page.keyboard.press('Backspace');
    }
    assert.equal(await phone.inputValue(), '+7', 'repeated Backspace must erase all national digits and preserve only +7');

    await phone.fill('+7 (999) 123-45-67');
    await phone.focus();
    await phone.evaluate((input) => input.setSelectionRange(4, 4));
    await page.keyboard.press('Delete');
    assert.equal(await phone.inputValue(), '+7 (991) 234-56-7', 'Delete at the opening parenthesis must remove the next digit');

    await phone.fill('+7 (999) 123-45-67');
    await phone.selectText();
    await page.keyboard.press('Backspace');
    assert.equal(await phone.inputValue(), '+7', 'deleting a selection must restore the fixed +7 prefix');

    await phone.blur();
    assert.notEqual(await phone.evaluate((input) => input.validationMessage), '', 'an incomplete prefixed number must remain invalid');
    await phone.fill('9991234567');
    assert.equal(await phone.inputValue(), '+7 (999) 123-45-67');
    assert.equal(await phone.evaluate((input) => input.validationMessage), '', 'a complete Russian number must be valid');

    await phone.evaluate((input) => input.form.reset());
    await page.waitForFunction(() => document.querySelector('[name="phone"]').value === '+7');
    assert.equal(await phone.inputValue(), '+7', 'resetting a form must restore the +7 prefix');

    await page.evaluate(() => {
      const input = document.createElement('input');
      input.name = 'billing_phone';
      document.body.append(input);
    });
    const checkoutPhone = page.locator('[name="billing_phone"]');
    await checkoutPhone.waitFor();
    assert.equal(await checkoutPhone.getAttribute('type'), 'tel', 'phone-named inputs must receive the telephone input type');
    await assert.doesNotReject(async () => {
      await page.waitForFunction(() => document.querySelector('[name="billing_phone"]').value === '+7');
    }, 'phone inputs inserted by the checkout must be enhanced automatically');

    console.log('Russian phone formatting and deletion behavior verified.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
