const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const config = require('./pairwise-audit.config.json');
const sourceBase = process.env.THEOBROMA_SOURCE_URL || 'https://theobroma.one/';
const localBase = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080/';
const widths = (process.env.THEOBROMA_WIDTHS || '390,430,768,1440,1920,2048,2560,3840').split(',');
const outputDir = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'wishlist-populated');

async function largestVisible(locator) {
  const matches = [];
  for (let index = 0; index < await locator.count(); index += 1) {
    const item = locator.nth(index);
    const box = await item.boundingBox();
    if (box && box.width > 0 && box.height > 0) matches.push({ item, box });
  }
  return matches.sort((left, right) => right.box.width * right.box.height - left.box.width * left.box.height)[0];
}

async function acceptCookie(page, side) {
  if (side === 'source') {
    const cookie = page.locator('.t657:visible');
    if (await cookie.count()) await cookie.locator('.t657__btn:visible,[role="button"]:visible,button:visible').last().click({ force: true });
  } else {
    const button = page.locator('[data-cookie-accept]:visible');
    if (await button.count()) await button.click();
  }
}

async function populateSource(page, width) {
  const product = await largestVisible(page.locator('.js-product,.t-store__card'));
  assert.ok(product, `${width}px source product is missing`);
  await product.item.click({ force: true });
  const favorite = page.locator('.t-popup_show [href="#addtofavorites"]:visible,.t-popup_show .t1002__addBtn:visible').first();
  await favorite.waitFor({ timeout: 15000 });
  await favorite.click();
  await page.locator('.t-popup_show .t1002__addBtn_active:visible').waitFor({ timeout: 10000 });
  await page.waitForTimeout(300);
  const close = page.locator('.t-popup_show .t-popup__close:visible,.t-popup_show .t-popup__close-wrapper:visible').first();
  if (await close.count()) await close.click({ force: true });
  else await page.keyboard.press('Escape');
  await page.goto(sourceBase, { waitUntil: 'networkidle', timeout: 60000 });
  await acceptCookie(page, 'source');
  const trigger = await largestVisible(page.locator('.nolimWish065'));
  assert.ok(trigger, `${width}px source wishlist trigger is missing`);
  const triggerAtom = trigger.item.locator('.tn-atom').first();
  if (await triggerAtom.count()) await triggerAtom.click({ force: true });
  else await trigger.item.click({ force: true });
  try {
    await page.locator('.t1002__wishlistwin-content_showed').waitFor({ timeout: 10000 });
  } catch (error) {
    const state = await page.evaluate(() => ({
      wishlists: [...document.querySelectorAll('[class*="wishlistwin-content"]')].map((node) => ({ className: node.className, rect: node.getBoundingClientRect().toJSON() })),
      popups: [...document.querySelectorAll('.t-popup_show')].map((node) => ({ label: node.getAttribute('aria-label'), text: node.textContent.trim().replace(/\s+/g, ' ').slice(0, 160) })),
      triggers: [...document.querySelectorAll('.nolimWish065')].map((node) => ({ text: node.textContent.trim(), className: node.className })),
    }));
    throw new Error(`${width}px source populated wishlist did not open: ${JSON.stringify(state)}`, { cause: error });
  }
}

async function populateLocal(page) {
  await page.locator('[data-product-modal-link],.product > a,a[href*="/product/"]').first().click();
  await page.locator('#commerce-modal[data-commerce-type="product"].is-open [data-wishlist-toggle]').click();
  if (await page.locator('#commerce-modal[data-commerce-type="product"].is-open').count()) await page.keyboard.press('Escape');
  await page.locator('[data-wishlist-open]').click();
  await page.locator('#commerce-modal[data-commerce-type="wishlist"].is-open .commerce-wishlist-product').waitFor();
}

async function capture(browser, side, viewport, target) {
  const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  const response = await page.goto(side === 'source' ? sourceBase : localBase, { waitUntil: 'networkidle', timeout: 60000 });
  await acceptCookie(page, side);
  if (side === 'source') await populateSource(page, viewport.width);
  else await populateLocal(page);
  await page.waitForTimeout(300);
  const panel = page.locator(side === 'source' ? '.t1002__wishlistwin-content_showed' : '.commerce-modal-wishlist').last();
  const result = {
    status: response?.status() || null,
    box: await panel.boundingBox(),
    text: (await panel.innerText()).replace(/\s+/g, ' ').trim(),
    overflow: await page.evaluate(() => Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - document.documentElement.clientWidth),
    errors,
  };
  await page.screenshot({ path: target, animations: 'disabled' });
  await context.close();
  return result;
}

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const width of widths) {
      const viewport = config.viewports[width];
      const source = await capture(browser, 'source', viewport, path.join(outputDir, `${width}-source.png`));
      const local = await capture(browser, 'local', viewport, path.join(outputDir, `${width}-local.png`));
      assert.equal(source.status, 200);
      assert.equal(local.status, 200);
      assert.ok(local.overflow <= 1, `${width}px local overflow ${local.overflow}px`);
      assert.deepEqual(local.errors, [], `${width}px local browser errors`);
      assert.match(local.text, /\d+\s*р(?:уб)?\.?/i, `${width}px local populated wishlist must show a product price`);
      results.push({ width, source, local });
      console.log(`${width}px source=${JSON.stringify(source.box)} local=${JSON.stringify(local.box)}`);
    }
  } finally {
    await browser.close();
  }
  fs.writeFileSync(path.join(outputDir, 'report.json'), `${JSON.stringify(results, null, 2)}\n`);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
