const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');
const themeRoot = path.join(root, 'wp-content/themes/theobroma');
const lostTemplatePath = path.join(themeRoot, 'woocommerce/myaccount/form-lost-password.php');
const resetTemplatePath = path.join(themeRoot, 'woocommerce/myaccount/form-reset-password.php');
const themeCss = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8');

async function launchBrowser() {
  try {
    return await chromium.launch({ headless: true });
  } catch (error) {
    if (!error.message.includes("Executable doesn't exist")) throw error;
    return chromium.launch({ channel: 'chrome', headless: true });
  }
}

function assertTemplateContract(templatePath, requiredMarkup) {
  assert.ok(fs.existsSync(templatePath), `${path.basename(templatePath)} must be overridden by the theme.`);
  const template = fs.readFileSync(templatePath, 'utf8');
  for (const marker of requiredMarkup) {
    assert.match(template, new RegExp(marker.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
}

(async () => {
  assertTemplateContract(lostTemplatePath, [
    'account-recovery-card',
    'woocommerce_before_lost_password_form',
    'woocommerce_lostpassword_form',
    'woocommerce_after_lost_password_form',
    'woocommerce_lost_password_message',
    'name="user_login"',
    'name="wc_reset_password"',
    "wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' )",
    'Вернуться ко входу',
  ]);

  assertTemplateContract(resetTemplatePath, [
    'account-recovery-card',
    'woocommerce_before_reset_password_form',
    'woocommerce_resetpassword_form',
    'woocommerce_after_reset_password_form',
    'woocommerce_reset_password_message',
    'name="password_1"',
    'name="password_2"',
    'name="reset_key"',
    'name="reset_login"',
    "wp_nonce_field( 'reset_password', 'woocommerce-reset-password-nonce' )",
  ]);

  const browser = await launchBrowser();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  try {
    await page.setContent(`
      <style>${themeCss}</style>
      <body class="woocommerce-account">
        <main class="shop-page"><div class="shop-shell">
          <h1 class="account-page-title">Личный кабинет</h1>
          <div class="woocommerce">
            <section class="account-recovery-card">
              <h2>Сброс пароля</h2>
              <p class="account-recovery-card__intro">Укажите email или имя пользователя. Мы пришлём ссылку для создания нового пароля.</p>
              <form class="woocommerce-ResetPassword lost_reset_password">
                <p class="form-row"><label for="user_login">Email или имя пользователя</label><input id="user_login" type="text"></p>
                <p class="account-recovery-card__submit"><button type="submit">Получить ссылку</button></p>
              </form>
              <p class="account-recovery-card__back"><a href="#">Вернуться ко входу</a></p>
            </section>
          </div>
        </div></main>
      </body>
    `);

    const metrics = await page.locator('.account-recovery-card').evaluate((card) => {
      const cardRect = card.getBoundingClientRect();
      const inputRect = card.querySelector('input').getBoundingClientRect();
      const buttonRect = card.querySelector('button').getBoundingClientRect();
      const pageTitle = document.querySelector('.account-page-title');
      const cardTitle = card.querySelector('h2');
      return {
        cardWidth: cardRect.width,
        inputWidth: inputRect.width,
        contentWidth: card.clientWidth - Number.parseFloat(getComputedStyle(card).paddingLeft) - Number.parseFloat(getComputedStyle(card).paddingRight),
        buttonWidth: buttonRect.width,
        buttonCenterDifference: Math.abs(
          (buttonRect.left + buttonRect.width / 2) - (cardRect.left + cardRect.width / 2),
        ),
        pageTitleSize: Number.parseFloat(getComputedStyle(pageTitle).fontSize),
        cardTitleSize: Number.parseFloat(getComputedStyle(cardTitle).fontSize),
        cardTitleFamily: getComputedStyle(cardTitle).fontFamily,
        cardTitleWeight: getComputedStyle(cardTitle).fontWeight,
        cardTitleTransform: getComputedStyle(cardTitle).textTransform,
      };
    });

    assert.ok(metrics.cardWidth <= 520, 'Password recovery must use the same compact card width as login.');
    assert.ok(Math.abs(metrics.inputWidth - metrics.contentWidth) <= 1, 'The recovery field must fill the card content width.');
    assert.ok(metrics.buttonWidth <= 240, 'The desktop recovery action must remain compact.');
    assert.ok(metrics.buttonCenterDifference <= 1, 'The recovery action must be centered.');
    assert.ok(metrics.pageTitleSize >= metrics.cardTitleSize * 1.3, 'The page title must dominate the recovery title.');
    assert.ok(metrics.cardTitleFamily.includes('Montserrat'), 'The recovery title must use the functional sans-serif typeface.');
    assert.equal(metrics.cardTitleWeight, '500', 'The recovery title must use a restrained medium weight.');
    assert.equal(metrics.cardTitleTransform, 'none', 'The recovery title must keep natural sentence case.');
    assert.ok(metrics.cardTitleSize <= metrics.pageTitleSize * 0.45, 'The recovery title must not compete with the page heading.');
  } finally {
    await browser.close();
  }

  console.log('Password recovery uses the complete themed account flow.');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
