const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeCss = fs.readFileSync(
  path.join(__dirname, '../wp-content/themes/theobroma/style.css'),
  'utf8',
);

async function launchBrowser() {
  try {
    return await chromium.launch({ headless: true });
  } catch (error) {
    if (!error.message.includes("Executable doesn't exist")) throw error;
    return chromium.launch({ channel: 'chrome', headless: true });
  }
}

async function formStyles(page, selector) {
  return page.locator(selector).evaluate((form) => {
    const input = form.querySelector('input:not([type="hidden"])');
    const button = form.querySelector('button[type="submit"]');
    const formStyle = getComputedStyle(form);
    const inputStyle = getComputedStyle(input);
    const buttonStyle = getComputedStyle(button);

    return {
      form: {
        padding: formStyle.padding,
        borderWidth: formStyle.borderWidth,
        borderRadius: formStyle.borderRadius,
        backgroundColor: formStyle.backgroundColor,
      },
      input: {
        height: inputStyle.height,
        padding: inputStyle.padding,
        borderBottomWidth: inputStyle.borderBottomWidth,
        borderBottomColor: inputStyle.borderBottomColor,
        backgroundColor: inputStyle.backgroundColor,
        fontFamily: inputStyle.fontFamily,
        fontSize: inputStyle.fontSize,
      },
      button: {
        minWidth: buttonStyle.minWidth,
        minHeight: buttonStyle.minHeight,
        borderRadius: buttonStyle.borderRadius,
        color: buttonStyle.color,
        backgroundColor: buttonStyle.backgroundColor,
        fontFamily: buttonStyle.fontFamily,
        fontSize: buttonStyle.fontSize,
      },
    };
  });
}

(async () => {
  const browser = await launchBrowser();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  try {
    await page.setContent(`
      <style>${themeCss}</style>
      <body class="woocommerce-account">
        <main class="shop-page"><div class="shop-shell"><div class="woocommerce"><div class="u-columns col2-set">
          <div class="u-column1 col-1">
            <form class="woocommerce-form woocommerce-form-login login">
              <input type="text" name="username">
              <input type="password" name="password">
              <button class="woocommerce-button button woocommerce-form-login__submit" type="submit">Войти</button>
            </form>
          </div>
          <div class="u-column2 col-2">
            <form class="woocommerce-form woocommerce-form-register register">
              <input type="email" name="email">
              <input type="password" name="password">
              <button class="woocommerce-button button woocommerce-form-register__submit" type="submit">Регистрация</button>
            </form>
          </div>
        </div></div></div></main>
      </body>
    `);

    const login = await formStyles(page, '.woocommerce-form-login');
    const registration = await formStyles(page, '.woocommerce-form-register');

    assert.deepEqual(
      registration,
      login,
      'Registration form must use the same themed panel, field and submit-button styles as login',
    );
  } finally {
    await browser.close();
  }

  console.log('Account login and registration form styles match.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
