const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const sourceUrl = process.env.CART_SOURCE_URL || 'https://theobroma.one/catalog/tproduct/741850665872-68-gorkii-shokolad-200g';
const localUrl = process.env.CART_LOCAL_URL || 'http://localhost:8080/product/theobroma-200-68-coriander/';
const width = Number(process.env.CART_WIDTH || 390);
const height = width <= 430 ? 932 : width <= 768 ? 1024 : 1200;
const output = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'pairwise-cart', String(width));

async function openSourceCart(page) {
  await page.goto(sourceUrl, { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await page.locator('.t-store__prod-popup__slider').waitFor({ timeout: 20_000 });
  await page.evaluate(async () => document.fonts?.ready);
  const buy = page.locator('.js-store-prod-submit:visible,.t-store__prod-popup__btn:visible').first();
  assert.ok(await buy.count(), 'Source product buy button is missing');
  await buy.click({ force: true });
  const cart = page.locator('.t706__cartwin:visible,.t706__cartwin_showed:visible').first();
  try {
    await cart.waitFor({ timeout: 10_000 });
  } catch {
    await buy.click({ force: true });
    await cart.waitFor({ timeout: 20_000 });
  }
  await page.locator('.t706__orderform input.t-input:visible').first().waitFor({ timeout: 10_000 });
  await page.waitForTimeout(2_000);
}

async function openLocalCart(page) {
  await page.goto(localUrl, { waitUntil: 'networkidle', timeout: 60_000 });
  await page.locator('#commerce-modal .product-detail-page').waitFor();
  await page.locator('#commerce-modal .single_add_to_cart_button').click();
  await page.locator('#commerce-modal[data-commerce-type="cart"].is-open .commerce-cart-product').waitFor();
  await page.locator('.commerce-cart-checkout input.input-text:visible').first().waitFor();
}

async function exerciseLocalCart(page) {
  const quantity = page.locator('.commerce-cart-quantity span');
  await page.locator('.commerce-cart-quantity button[data-cart-quantity="2"]').click();
  await quantity.filter({ hasText: /^2$/ }).waitFor({ timeout: 10_000 });
  const doubledSubtotal = (await page.locator('.commerce-cart-subtotal strong').innerText()).replace(/\D/g, '');
  assert.equal(doubledSubtotal, '2852', 'Cart subtotal did not double after quantity increase');

  await page.locator('.commerce-cart-quantity button[data-cart-quantity="1"]').click();
  await quantity.filter({ hasText: /^1$/ }).waitFor({ timeout: 10_000 });
  await page.locator('.commerce-cart-remove').click();
  await page.locator('.commerce-cart--empty').waitFor({ timeout: 10_000 });
  await page.locator('.cart-count').first().filter({ hasText: '(0)' }).waitFor({ timeout: 10_000 });
}

async function cartMetrics(page, side) {
  const selectors = side === 'source' ? {
    close: '.t706__close-button',
    heading: '.t706__cartwin-heading',
    thumb: '.t706__product-imgdiv',
    title: '.t706__product-title',
    titleText: '.t706__product-title a',
    quantity: '.t706__product-plusminus',
    price: '.t706__product-amount',
    remove: '.t706__product-del',
    subtotal: '.t706__cartwin-bottom',
    auth: '.t706__auth',
  } : {
    close: '.commerce-modal-close',
    heading: '.commerce-cart-header h2',
    thumb: '.commerce-cart-thumb',
    title: '.commerce-cart-product h3',
    titleText: '.commerce-cart-product h3 a',
    quantity: '.commerce-cart-quantity',
    price: '.commerce-cart-price',
    remove: '.commerce-cart-remove',
    subtotal: '.commerce-cart-subtotal',
    auth: '.commerce-cart-auth',
  };
  const boxes = {};
  for (const [name, selector] of Object.entries(selectors)) {
    boxes[name] = await page.locator(selector).first().boundingBox();
  }
  const cartRoot = side === 'source'
    ? page.locator('.t706__cartwin:visible,.t706__cartwin_showed:visible').first()
    : page.locator('#commerce-modal[data-commerce-type="cart"].is-open');
  const deliveryInfo = cartRoot.getByText('Подробная информация о доставке и оплате', { exact: true }).last();
  boxes.deliveryInfoVisible = Boolean(await deliveryInfo.count()) && await deliveryInfo.isVisible();
  boxes.deliveryInfo = boxes.deliveryInfoVisible ? await deliveryInfo.boundingBox() : null;
  const inputSelector = side === 'source' ? '.t706__orderform input.t-input:visible' : '.commerce-cart-checkout input.input-text:visible';
  boxes.inputs = [];
  for (let index = 0; index < Math.min(3, await page.locator(inputSelector).count()); index += 1) {
    if (side === 'source') {
      boxes.inputs.push(await page.locator(inputSelector).nth(index).evaluate((element) => {
        const rect = (element.closest('.t-input-block') || element).getBoundingClientRect();
        return { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
      }));
    } else {
      boxes.inputs.push(await page.locator(inputSelector).nth(index).boundingBox());
    }
  }
  boxes.topBarColor = side === 'local' && width <= 600 ? await page.locator('#commerce-modal').evaluate((element) => getComputedStyle(element, '::before').backgroundColor) : await page.evaluate(() => {
    let element = document.elementFromPoint(innerWidth / 2, 25);
    while (element) {
      const color = getComputedStyle(element).backgroundColor;
      if (color !== 'rgba(0, 0, 0, 0)') return color;
      element = element.parentElement;
    }
    return 'rgba(0, 0, 0, 0)';
  });
  if (side === 'local') {
    boxes.requiredFields = await page.evaluate(() => ({
      city: Boolean(document.querySelector('#billing_city')),
      name: Boolean(document.querySelector('#billing_first_name')),
      phone: Boolean(document.querySelector('#billing_phone')),
      email: Boolean(document.querySelector('#billing_email')),
      postcode: Boolean(document.querySelector('#billing_postcode')),
      address1: Boolean(document.querySelector('#billing_address_1')),
      address2: Boolean(document.querySelector('#billing_address_2')),
    }));
  }
  return boxes;
}

(async () => {
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const measured = {};
  try {
    for (const side of ['source', 'local']) {
      const context = await browser.newContext({ viewport: { width, height }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      if (side === 'source') await openSourceCart(page);
      else await openLocalCart(page);
      await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });
      await page.waitForTimeout(300);
      await page.screenshot({ path: path.join(output, `${side}.png`), fullPage: false, animations: 'disabled' });
      const rootSelector = side === 'source' ? '.t706__cartwin:visible,.t706__cartwin_showed:visible' : '#commerce-modal[data-commerce-type="cart"].is-open';
      const root = page.locator(rootSelector).first();
      measured[side] = await cartMetrics(page, side);
      measured[side].errors = errors;
      measured[side].scroll = await root.evaluate((element) => ({ scrollHeight: element.scrollHeight, clientHeight: element.clientHeight }));
      fs.writeFileSync(path.join(output, `${side}-metrics.json`), `${JSON.stringify({
        side,
        errors,
        ...measured[side],
      }, null, 2)}\n`);
      for (const scrollTop of [700, 1200]) {
        await root.evaluate((element, value) => { element.scrollTop = value; }, scrollTop);
        await page.waitForTimeout(100);
        await page.screenshot({ path: path.join(output, `${side}-${scrollTop}.png`), fullPage: false, animations: 'disabled' });
      }
      if (side === 'local') await exerciseLocalCart(page);
      await context.close();
    }
    assert.equal(measured.local.topBarColor, measured.source.topBarColor, 'Cart top bar color differs');
    assert.deepEqual(measured.local.errors, [], 'Local cart has browser errors');
    assert.equal(measured.local.deliveryInfoVisible, measured.source.deliveryInfoVisible, 'Cart delivery-information link visibility differs');
    const comparedParts = ['close', 'heading', 'thumb', 'title', 'titleText', 'quantity', 'price', 'remove', 'subtotal', 'auth'];
    if (measured.source.deliveryInfoVisible) comparedParts.push('deliveryInfo');
    for (const part of comparedParts) {
      for (const key of ['x', 'y', 'width', 'height']) {
        const delta = Math.abs(measured.source[part][key] - measured.local[part][key]);
        assert.ok(delta <= 1.25, `${width}px cart ${part} ${key} differs by ${delta}px`);
      }
    }
    assert.equal(measured.source.inputs.length, 3, 'Source cart did not expose the expected first three checkout fields');
    assert.equal(measured.local.inputs.length, 3, 'Local cart did not expose the expected first three checkout fields');
    for (let index = 0; index < 3; index += 1) {
      for (const key of ['x', 'y', 'width', 'height']) {
        const delta = Math.abs(measured.source.inputs[index][key] - measured.local.inputs[index][key]);
        assert.ok(delta <= 1.25, `${width}px cart input ${index + 1} ${key} differs by ${delta}px`);
      }
    }
    assert.ok(Object.values(measured.local.requiredFields).every(Boolean), 'Local cart is missing contact or delivery-address fields');
    console.log(`Pairwise populated cart verified at ${width}px`);
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
