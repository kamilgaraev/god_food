const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const phoneScript = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'assets', 'js', 'phone-input.js');
const themeStyles = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'style.css');
const localChrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

(async () => {
  const browser = await chromium.launch({
    headless: true,
    ...(fs.existsSync(localChrome) ? { executablePath: localChrome } : {}),
  });
  const page = await browser.newPage();

  try {
    await page.setContent(`
      <section class="cooperation-form">
        <form>
          <div class="form-grid">
            <div class="phone-field">
              <span class="phone-flag" aria-hidden="true"></span>
              <span class="phone-triangle" aria-hidden="true"></span>
              <span class="phone-code" aria-hidden="true">+7</span>
              <input type="tel" name="phone" required>
            </div>
          </div>
        </form>
      </section>
    `);
    await page.addStyleTag({ path: themeStyles });
    await page.addScriptTag({ path: phoneScript });

    const phone = page.locator('[name="phone"]');
    assert.equal(await phone.inputValue(), '', 'an untouched phone input must stay empty');
    assert.equal(await phone.getAttribute('placeholder'), 'Номер телефона');
    assert.equal(await phone.getAttribute('inputmode'), 'tel');
    assert.equal(await phone.getAttribute('autocomplete'), 'tel');
    assert.equal(await phone.getAttribute('maxlength'), '18');
    assert.equal(await page.locator('.phone-flag,.phone-triangle,.phone-code').count(), 0, 'legacy country controls must be removed');

    await phone.focus();
    assert.equal(await phone.inputValue(), '', 'focus alone must not insert the +7 prefix');
    await phone.pressSequentially('2');
    assert.equal(await phone.inputValue(), '+7 (2', 'the +7 prefix must appear after the first entered digit');
    await phone.pressSequentially('131231234');
    assert.equal(await phone.inputValue(), '+7 (213) 123-12-34', 'typing all ten digits must preserve the final digit');
    const layout = await phone.evaluate((input) => {
      const field = input.closest('.phone-field');
      return {
        fieldWidth: field.getBoundingClientRect().width,
        inputWidth: input.getBoundingClientRect().width,
        clientWidth: input.clientWidth,
        scrollWidth: input.scrollWidth,
        boxShadow: getComputedStyle(input).boxShadow,
      };
    });
    assert(Math.abs(layout.fieldWidth - layout.inputWidth) <= 1, `phone input must fill its ${layout.fieldWidth}px field, received ${layout.inputWidth}px`);
    assert(layout.scrollWidth <= layout.clientWidth, `full phone number must fit without clipping: ${layout.scrollWidth}px > ${layout.clientWidth}px`);
    assert.notEqual(layout.boxShadow, 'none', 'the underline must belong to the input and remain continuous');

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
    assert.equal(await phone.inputValue(), '', 'repeated Backspace must restore the empty state after all national digits are removed');
    await phone.blur();
    assert.equal(await phone.inputValue(), '', 'an empty prefix must disappear when the field becomes inactive');

    await phone.fill('+7 (999) 123-45-67');
    await phone.focus();
    await phone.evaluate((input) => input.setSelectionRange(4, 4));
    await page.keyboard.press('Delete');
    assert.equal(await phone.inputValue(), '+7 (991) 234-56-7', 'Delete at the opening parenthesis must remove the next digit');

    await phone.fill('+7 (999) 123-45-67');
    await phone.selectText();
    await page.keyboard.press('Backspace');
    assert.equal(await phone.inputValue(), '', 'deleting a selection must restore the empty state');

    await phone.fill('9');
    await phone.blur();
    assert.notEqual(await phone.evaluate((input) => input.validationMessage), '', 'a non-empty incomplete number must remain invalid');
    await phone.fill('9991234567');
    assert.equal(await phone.inputValue(), '+7 (999) 123-45-67');
    assert.equal(await phone.evaluate((input) => input.validationMessage), '', 'a complete Russian number must be valid');

    await phone.evaluate((input) => input.form.reset());
    await page.waitForFunction(() => document.querySelector('[name="phone"]').value === '');
    assert.equal(await phone.inputValue(), '', 'resetting a form must restore the empty state');

    await page.evaluate(() => {
      const form = document.createElement('form');
      const input = document.createElement('input');
      input.name = 'billing_phone';
      form.append(input);
      document.body.append(form);
    });
    const checkoutPhone = page.locator('[name="billing_phone"]');
    await checkoutPhone.waitFor();
    assert.equal(await checkoutPhone.getAttribute('type'), 'tel', 'phone-named inputs must receive the telephone input type');
    await assert.doesNotReject(async () => {
      await page.waitForFunction(() => document.querySelector('[name="billing_phone"]').placeholder === 'Номер телефона');
    }, 'phone inputs inserted by the checkout must receive the shared placeholder automatically');
    assert.equal(await checkoutPhone.inputValue(), '', 'phone inputs inserted by the checkout must stay empty until typing');
    await checkoutPhone.pressSequentially('9');
    assert.equal(await checkoutPhone.inputValue(), '+7 (9', 'dynamic checkout inputs must add +7 after the first digit');
    await checkoutPhone.evaluate((input) => input.form.reset());
    await page.waitForFunction(() => document.querySelector('[name="billing_phone"]').value === '');
    assert.equal(await checkoutPhone.inputValue(), '', 'resetting a dynamic checkout form must restore the empty state');

    console.log('Russian phone formatting and deletion behavior verified.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
