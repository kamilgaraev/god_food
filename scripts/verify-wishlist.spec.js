const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';
const login = process.env.THEOBROMA_QA_LOGIN;
const password = process.env.THEOBROMA_QA_PASSWORD;

async function openFirstProduct(page) {
  await page.goto(new URL('/catalog/', baseUrl).href, { waitUntil: 'networkidle' });
  await page.locator('ul.products li.product a.woocommerce-LoopProduct-link').first().click();
  await page.locator('#commerce-modal[data-commerce-type="product"].is-open [data-wishlist-toggle]').waitFor();
}

async function toggleCurrentProduct(page) {
  await page.locator('#commerce-modal [data-wishlist-toggle]').click();
  await page.waitForTimeout(300);
  const state = await page.evaluate(() => ({
    count: document.querySelector('.wishlist-count')?.textContent,
    pressed: document.querySelector('[data-wishlist-toggle]')?.getAttribute('aria-pressed'),
    productId: document.querySelector('[data-wishlist-toggle]')?.dataset.productId,
    stored: localStorage.getItem('theobroma_wishlist_product_ids'),
  }));
  assert.equal(state.count, '(1)', `Wishlist toggle failed: ${JSON.stringify(state)}`);
}

async function openWishlist(page) {
  if (!await page.locator('[data-wishlist-open]').isVisible()) {
    await page.keyboard.press('Escape');
    await page.locator('#commerce-modal').waitFor({ state: 'hidden' });
  } else if (await page.locator('#commerce-modal.is-open').count()) {
    await page.keyboard.press('Escape');
    await page.locator('#commerce-modal').waitFor({ state: 'hidden' });
  }
  await page.locator('[data-wishlist-open]').click();
  await page.locator('#commerce-modal[data-commerce-type="wishlist"].is-open .commerce-wishlist').waitFor();
}

async function clearWishlist(page) {
  await openWishlist(page);
  const clear = page.locator('[data-wishlist-clear]');
  if (await clear.isVisible()) {
    await clear.click();
  } else {
    const remove = page.locator('[data-wishlist-remove]').first();
    if (await remove.count()) await remove.click();
  }
  await page.locator('.commerce-wishlist-empty').waitFor();
  assert.equal(await page.locator('.wishlist-count').first().innerText(), '(0)');
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 1440]) {
      const context = await browser.newContext({ viewport: { width, height: 1000 } });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
      await openFirstProduct(page);
      assert.deepEqual(errors, [], `Wishlist bootstrap errors: ${errors.join(' | ')}`);
      await toggleCurrentProduct(page);
      assert.deepEqual(errors, [], `Wishlist browser errors: ${errors.join(' | ')}`);
      assert.equal(await page.locator('[data-wishlist-toggle]').getAttribute('aria-pressed'), 'true');
      await openWishlist(page);
      assert.equal(await page.locator('.commerce-wishlist-product').count(), 1);
      await page.reload({ waitUntil: 'networkidle' });
      assert.equal(await page.locator('.wishlist-count').first().innerText(), '(1)', `${width}px: guest wishlist was not persisted`);
      await clearWishlist(page);
      await context.close();
      console.log(`PASS ${width}px guest wishlist`);
    }

    if (login && password) {
      const context = await browser.newContext({ viewport: { width: 390, height: 932 } });
      const page = await context.newPage();
      await page.goto(new URL('/my-account/', baseUrl).href, { waitUntil: 'networkidle' });
      const modal = page.locator('#account-modal');
      await modal.locator('#account-email').fill(login);
      await modal.locator('[data-account-continue]').click();
      await modal.locator('#account-login-password').fill(password);
      await Promise.all([
        page.waitForLoadState('networkidle'),
        modal.locator('[data-account-login] button[name="login"]').click(),
      ]);
      await openFirstProduct(page);
      await toggleCurrentProduct(page);
      await page.waitForTimeout(500);
      await page.reload({ waitUntil: 'networkidle' });
      assert.equal(await page.locator('.wishlist-count').first().innerText(), '(1)', 'Authenticated wishlist was not persisted in user meta');
      await clearWishlist(page);
      await context.close();
      console.log('PASS authenticated wishlist persistence');
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
