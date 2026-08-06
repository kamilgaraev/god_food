const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';
const outputDir = path.resolve(__dirname, '..', 'output', 'playwright', 'checkout');

async function waitForCheckout(page) {
  await page.locator('form.checkout').waitFor();
  await page.waitForFunction(() => !document.querySelector('form.checkout .blockUI'));
}

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const context = await browser.newContext({ viewport: { width: 390, height: 932 }, reducedMotion: 'reduce' });
  const page = await context.newPage();
  const browserErrors = [];
  const checkoutResponses = [];
  page.on('console', (message) => { if (message.type() === 'error') browserErrors.push(message.text()); });
  page.on('pageerror', (error) => browserErrors.push(error.message));
  page.on('response', (response) => {
    if (response.url().includes('wc-ajax=checkout')) checkoutResponses.push({ url: response.url(), status: response.status() });
  });

  try {
    await page.goto(new URL('/catalog/', baseUrl).href, { waitUntil: 'networkidle' });
    const cookie = page.locator('.cookie-notice:visible');
    if (await cookie.count()) await cookie.locator('button').click();

    await page.locator('ul.products li.product a.woocommerce-LoopProduct-link').first().click();
    await page.locator('#commerce-modal[data-commerce-type="product"].is-open .single_add_to_cart_button').click();
    await page.locator('#commerce-modal[data-commerce-type="cart"].is-open .commerce-cart-product').waitFor();
    await waitForCheckout(page);

    const fieldNames = await page.locator('form.checkout input, form.checkout select').evaluateAll((elements) => (
      elements.map((element) => element.name).filter(Boolean)
    ));
    for (const requiredField of [
      'billing_city',
      'billing_first_name',
      'billing_phone',
      'billing_email',
      'billing_postcode',
      'billing_address_1',
      'billing_address_2',
      'theobroma_privacy_consent',
      'terms',
    ]) {
      assert.ok(fieldNames.includes(requiredField), `Checkout field is missing: ${requiredField}`);
    }

    const initial = await page.evaluate(() => ({
      shippingMethods: [...document.querySelectorAll('input[name^="shipping_method"]')].map((input) => ({
        value: input.value,
        label: input.closest('li')?.textContent?.replace(/\s+/g, ' ').trim() || '',
      })),
      paymentMethods: [...document.querySelectorAll('input[name="payment_method"]')].map((input) => ({
        value: input.value,
        checked: input.checked,
      })),
      notices: [...document.querySelectorAll('.woocommerce-error,.woocommerce-info,.woocommerce-message')]
        .map((element) => element.textContent.replace(/\s+/g, ' ').trim()),
    }));
    assert.deepEqual(initial.shippingMethods, [], 'A delivery rate is shown without configured provider credentials');
    assert.ok(initial.paymentMethods.some(({ value }) => value === 'cod'), 'Cash on delivery is not available');
    assert.ok(!initial.paymentMethods.some(({ value }) => value === 'yookassa_widget'), 'Disabled YooKassa is exposed at checkout');

    const invalidFields = await page.locator('form.checkout :invalid').evaluateAll((elements) => elements.map((element) => element.name).filter(Boolean));
    for (const name of ['theobroma_privacy_consent']) {
      assert.ok(invalidFields.includes(name), `Native required validation is missing for ${name}`);
    }
    await page.locator('form.checkout').evaluate((form) => { form.noValidate = true; });
    await page.locator('#place_order').click();
    await page.waitForTimeout(3000);
    assert.ok(checkoutResponses.length > 0, `Checkout submit did not issue a WooCommerce AJAX request; browser errors: ${browserErrors.join(' | ')}`);
    await page.locator('.woocommerce-NoticeGroup-checkout').waitFor({ timeout: 10000 });
    const validationText = await page.locator('.woocommerce-NoticeGroup-checkout').innerText();
    for (const [label, pattern] of [
      ['Имя', /Имя/i],
      ['Город', /Город/i],
      ['Телефон', /\+7 \(000\)/i],
      ['Email', /Email/i],
      ['Согласие на данные', /согласие на обработку персональных данных/i],
      ['Публичная оферта', /правила и условия/i],
      ['Способ доставки', /не выбран метод доставки/i],
    ]) {
      assert.match(validationText, pattern, `Missing checkout validation for ${label}`);
    }

    const values = {
      billing_city: 'Москва',
      billing_first_name: 'Тест',
      billing_phone: '+7 999 000-00-00',
      billing_email: 'checkout-audit@example.com',
      billing_postcode: '101000',
      billing_address_1: 'Тестовая улица, 1',
      billing_address_2: '1',
    };
    for (const [name, value] of Object.entries(values)) {
      await page.locator(`[name="${name}"]`).fill(value);
    }
    await page.locator('[name="theobroma_privacy_consent"]').check();
    await page.locator('[name="terms"]').check();
    await page.locator('[name="billing_city"]').blur();
    await page.waitForTimeout(1000);
    await waitForCheckout(page);

    const afterAddress = await page.evaluate(() => ({
      shippingMethods: [...document.querySelectorAll('input[name^="shipping_method"]')].map((input) => input.value),
      unavailable: [...document.querySelectorAll('.woocommerce-shipping-totals,.woocommerce-info,.woocommerce-error')]
        .map((element) => element.textContent.replace(/\s+/g, ' ').trim())
        .filter(Boolean),
      overflow: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - document.documentElement.clientWidth,
    }));
    assert.deepEqual(afterAddress.shippingMethods, [], 'A provider delivery rate appeared without credentials');
    assert.ok(afterAddress.overflow <= 1, `Checkout has ${afterAddress.overflow}px horizontal overflow`);
    assert.deepEqual(browserErrors, [], `Browser errors: ${browserErrors.join(' | ')}`);

    await page.screenshot({ path: path.join(outputDir, 'guest-no-provider.png'), fullPage: true, animations: 'disabled' });
    fs.writeFileSync(path.join(outputDir, 'report.json'), `${JSON.stringify({ initial, validationText, afterAddress }, null, 2)}\n`);
    console.log('Guest checkout validation and fail-closed provider state passed');
  } finally {
    await context.close();
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
