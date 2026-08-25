const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const headerSource = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/header.php'), 'utf8');
const baseStyles = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/style.css'), 'utf8');
const homeStyles = fs.readFileSync(path.join(root, 'wp-content/themes/theobroma/assets/css/home-redesign.css'), 'utf8');
const headerMarkup = headerSource
  .match(/<header class="site-header">[\s\S]*?<\/header>/)?.[0]
  .replace(/<\?php[\s\S]*?\?>/g, '');

assert.ok(headerMarkup, 'The site header markup must be available to the layout test');

async function withRenderedHeader(callback, viewportWidth = 1440) {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: viewportWidth, height: 320 } });
    await page.setContent(`<style>${baseStyles}\n${homeStyles}</style>${headerMarkup}`);
    return await callback(page);
  } finally {
    await browser.close();
  }
}

test('cart is rendered before the account action', async () => {
  const classes = await withRenderedHeader((page) => page.locator('.nav-links-transactional > a').evaluateAll(
    (actions) => actions.map((action) => Array.from(action.classList).find((name) => name.startsWith('header-') && name !== 'header-icon')),
  ));

  assert.deepEqual(classes, ['header-where', 'header-cart', 'header-account']);
});

test('cart and account controls use no more than half a spacing unit', async () => {
  const metrics = await withRenderedHeader((page) => page.evaluate(() => {
    const cart = document.querySelector('.header-cart').getBoundingClientRect();
    const account = document.querySelector('.header-account').getBoundingClientRect();
    return {
      gap: account.left - cart.right,
      rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
    };
  }));

  assert.ok(
    metrics.gap >= 0 && metrics.gap <= metrics.rootFontSize * 0.5 + 0.5,
    `Expected cart/account gap at most 0.5rem, received ${metrics.gap}px`,
  );
});

test('cart has the same dimensions as the account control', async () => {
  for (const viewportWidth of [1440, 768, 390]) {
    const metrics = await withRenderedHeader((page) => page.evaluate(() => {
      const cart = document.querySelector('.header-cart').getBoundingClientRect();
      const account = document.querySelector('.header-account').getBoundingClientRect();
      return {
        cartWidth: cart.width,
        cartHeight: cart.height,
        accountWidth: account.width,
        accountHeight: account.height,
      };
    }), viewportWidth);

    assert.ok(Math.abs(metrics.cartWidth - metrics.accountWidth) <= 0.5, `${viewportWidth}px: cart width ${metrics.cartWidth}px must match account width ${metrics.accountWidth}px`);
    assert.ok(Math.abs(metrics.cartHeight - metrics.accountHeight) <= 0.5, `${viewportWidth}px: cart height ${metrics.cartHeight}px must match account height ${metrics.accountHeight}px`);
  }
});

test('cart count uses a separate badge without covering the icon', async () => {
  await withRenderedHeader(async (page) => {
    const cart = page.locator('.header-cart');
    const icon = cart.locator('img');
    const count = cart.locator('.cart-count');
    const opacity = async (locator) => Number(await locator.evaluate((element) => getComputedStyle(element).opacity));

    assert.equal(await opacity(icon), 1, 'Cart icon must be visible before hover');
    assert.equal(await opacity(count), 0, 'An empty cart must not show a zero badge');

    await count.evaluate((element) => {
      element.textContent = '24';
      element.dataset.count = '24';
    });

    assert.equal(await opacity(count), 1, 'A non-empty cart must show its count badge');

    await cart.hover();
    await page.waitForTimeout(250);

    const boxes = await page.evaluate(() => {
      const iconRect = document.querySelector('.header-cart img').getBoundingClientRect();
      const countRect = document.querySelector('.header-cart .cart-count').getBoundingClientRect();
      return {
        icon: { top: iconRect.top, right: iconRect.right, bottom: iconRect.bottom, left: iconRect.left },
        count: { top: countRect.top, right: countRect.right, bottom: countRect.bottom, left: countRect.left },
      };
    });
    const overlaps = boxes.icon.left < boxes.count.right
      && boxes.icon.right > boxes.count.left
      && boxes.icon.top < boxes.count.bottom
      && boxes.icon.bottom > boxes.count.top;

    assert.equal(await opacity(icon), 1, 'Cart icon must remain visible on hover');
    assert.equal(overlaps, false, `Cart badge ${JSON.stringify(boxes.count)} must not cover icon ${JSON.stringify(boxes.icon)}`);
  });
});

async function accountControlColors(account) {
  return account.evaluate((element) => {
    const styles = getComputedStyle(element);
    const iconStyles = getComputedStyle(element.querySelector('img'));
    return {
      backgroundColor: styles.backgroundColor,
      borderColor: styles.borderColor,
      iconFilter: iconStyles.filter,
    };
  });
}

test('account control becomes gold with a white icon on hover', async () => {
  await withRenderedHeader(async (page) => {
    const account = page.locator('.header-account');
    await account.hover();
    await page.waitForTimeout(220);

    assert.deepEqual(await accountControlColors(account), {
      backgroundColor: 'rgb(176, 144, 61)',
      borderColor: 'rgb(176, 144, 61)',
      iconFilter: 'brightness(0) invert(1)',
    });
  });
});

test('account control keeps the same gold state for keyboard focus', async () => {
  await withRenderedHeader(async (page) => {
    const account = page.locator('.header-account');
    for (let step = 0; step < 12; step += 1) {
      await page.keyboard.press('Tab');
      if (await account.evaluate((element) => element === document.activeElement)) break;
    }
    assert.equal(await account.evaluate((element) => element === document.activeElement), true, 'Tab navigation must reach the account control');
    await page.waitForTimeout(400);

    assert.deepEqual(await accountControlColors(account), {
      backgroundColor: 'rgb(176, 144, 61)',
      borderColor: 'rgb(176, 144, 61)',
      iconFilter: 'brightness(0) invert(1)',
    });
  });
});
