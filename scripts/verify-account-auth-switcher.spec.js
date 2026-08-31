const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');
const templatePath = path.join(
  root,
  'wp-content/themes/theobroma/woocommerce/myaccount/form-login.php',
);
const scriptPath = path.join(
  root,
  'wp-content/themes/theobroma/assets/js/account-page-auth.js',
);
const themeCss = fs.readFileSync(
  path.join(root, 'wp-content/themes/theobroma/style.css'),
  'utf8',
);
const functionsPhp = fs.readFileSync(
  path.join(root, 'wp-content/themes/theobroma/functions.php'),
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

(async () => {
  assert.ok(fs.existsSync(templatePath), 'The theme must override the My Account auth template.');
  assert.ok(fs.existsSync(scriptPath), 'The account-page auth switcher script must exist.');
  assert.match(functionsPhp, /is_account_page\(\)/, 'The switcher must be scoped to My Account.');
  assert.match(functionsPhp, /assets\/js\/account-page-auth\.js/, 'The switcher must be enqueued.');

  const template = fs.readFileSync(templatePath, 'utf8');
  for (const requiredMarkup of [
    'data-account-page-auth',
    'data-account-page-view="login"',
    'data-account-page-view="register"',
    'data-account-page-show="login"',
    'data-account-page-show="register"',
    'woocommerce_login_form_start',
    'woocommerce_register_form_start',
    "wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' )",
    "wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' )",
  ]) {
    assert.match(template, new RegExp(requiredMarkup.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }

  const browser = await launchBrowser();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  try {
    await page.setContent(`
      <style>${themeCss}</style>
      <body class="woocommerce-account">
        <main class="shop-page"><div class="shop-shell">
          <h1 class="account-page-title">Личный кабинет</h1>
          <div class="woocommerce">
          <section class="account-page-auth" data-account-page-auth>
            <div class="account-page-auth__view" data-account-page-view="login">
              <p class="account-page-auth__eyebrow">С возвращением</p>
              <h2>Вход</h2>
              <p class="account-page-auth__intro">Войдите, чтобы посмотреть заказы и данные профиля.</p>
              <form class="woocommerce-form woocommerce-form-login login"><input name="username"><input name="password"><p class="account-page-auth__submit"><button type="submit">Войти</button></p></form>
              <p class="account-page-auth__switch">Нет аккаунта? <button type="button" data-account-page-show="register">Зарегистрироваться</button></p>
            </div>
            <div class="account-page-auth__view" data-account-page-view="register" hidden>
              <h2>Регистрация</h2>
              <form class="woocommerce-form woocommerce-form-register register"><input name="email"><input name="password"><button type="submit">Зарегистрироваться</button></form>
              <p class="account-page-auth__switch">Уже есть аккаунт? <button type="button" data-account-page-show="login">Войти</button></p>
            </div>
          </section>
        </div></div></main>
      </body>
    `);
    await page.addScriptTag({ path: scriptPath });

    const card = page.locator('[data-account-page-auth]');
    const initial = await card.evaluate((element) => {
      const login = element.querySelector('[data-account-page-view="login"]');
      const register = element.querySelector('[data-account-page-view="register"]');
      const pageTitle = document.querySelector('.account-page-title');
      const formTitle = login.querySelector('h2');
      const eyebrow = login.querySelector('.account-page-auth__eyebrow');
      const submit = login.querySelector('button[type="submit"]');
      const cardRect = element.getBoundingClientRect();
      const submitRect = submit.getBoundingClientRect();
      const cardStyle = getComputedStyle(element);
      return {
        width: cardRect.width,
        borderWidth: cardStyle.borderWidth,
        pageTitleSize: Number.parseFloat(getComputedStyle(pageTitle).fontSize),
        formTitleSize: Number.parseFloat(getComputedStyle(formTitle).fontSize),
        pageTitleAlignment: getComputedStyle(pageTitle).textAlign,
        eyebrowLetterSpacing: getComputedStyle(eyebrow).letterSpacing,
        eyebrowTextTransform: getComputedStyle(eyebrow).textTransform,
        eyebrowFontWeight: getComputedStyle(eyebrow).fontWeight,
        submitWidth: submitRect.width,
        submitCenterDifference: Math.abs(
          (submitRect.left + submitRect.width / 2) - (cardRect.left + cardRect.width / 2),
        ),
        loginHidden: login.hidden,
        registerHidden: register.hidden,
      };
    });

    assert.ok(initial.width <= 520, 'Authentication must be presented as one compact card.');
    assert.equal(initial.borderWidth, '0px', 'The card must feel like a calm surface, not a bordered form box.');
    assert.ok(
      initial.pageTitleSize >= initial.formTitleSize * 1.3,
      'The page title must clearly dominate the form title.',
    );
    assert.equal(initial.pageTitleAlignment, 'center', 'The guest page title must align with the auth card.');
    assert.equal(initial.eyebrowLetterSpacing, 'normal', 'The welcome copy must use natural letter spacing.');
    assert.equal(initial.eyebrowTextTransform, 'none', 'The welcome copy must keep natural sentence case.');
    assert.equal(initial.eyebrowFontWeight, '400', 'The welcome copy must not look like a decorative label.');
    assert.ok(initial.submitWidth <= 240, 'The desktop submit button must not dominate the card width.');
    assert.ok(initial.submitCenterDifference <= 1, 'The compact submit button must be centered.');
    assert.equal(initial.loginHidden, false, 'Login must be the initial view.');
    assert.equal(initial.registerHidden, true, 'Registration must not compete with login initially.');

    await page.getByRole('button', { name: 'Зарегистрироваться' }).click();
    assert.equal(await page.locator('[data-account-page-view="login"]').getAttribute('hidden'), '');
    assert.equal(await page.locator('[data-account-page-view="register"]').getAttribute('hidden'), null);

    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    assert.equal(await page.locator('[data-account-page-view="login"]').getAttribute('hidden'), null);
    assert.equal(await page.locator('[data-account-page-view="register"]').getAttribute('hidden'), '');
  } finally {
    await browser.close();
  }

  console.log('Account authentication uses one accessible switchable card.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
