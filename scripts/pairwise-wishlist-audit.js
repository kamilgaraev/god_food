const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const config = require('./pairwise-audit.config.json');
const sourceBase = process.env.THEOBROMA_SOURCE_URL || 'https://theobroma.one/';
const localBase = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080/';
const widths = (process.env.THEOBROMA_WIDTHS || '390,430,768,1440,1920,2048,2560,3840').split(',');
const outputDir = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'wishlist');

async function largestVisible(locator) {
  const matches = [];
  for (let index = 0; index < await locator.count(); index += 1) {
    const item = locator.nth(index);
    const box = await item.boundingBox();
    if (box && box.width > 0 && box.height > 0) matches.push({ item, box });
  }
  return matches.sort((left, right) => right.box.width * right.box.height - left.box.width * left.box.height)[0];
}

async function capture(browser, side, viewport, target) {
  const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  const response = await page.goto(side === 'source' ? sourceBase : localBase, { waitUntil: 'networkidle', timeout: 60000 });
  if (side === 'source') {
    const cookie = page.locator('.t657:visible');
    if (await cookie.count()) await cookie.locator('.t657__btn:visible,[role="button"]:visible,button:visible').last().click({ force: true });
    const trigger = await largestVisible(page.locator('.nolimWish065'));
    assert.ok(trigger, `${viewport.width}px source wishlist trigger is missing`);
    await trigger.item.click({ force: true });
    await page.locator('.t-popup_show[aria-label*="избранное"]').waitFor({ timeout: 10000 });
  } else {
    await page.locator('[data-wishlist-open]').click();
    await page.locator('#commerce-modal[data-commerce-type="wishlist"].is-open .commerce-wishlist').waitFor();
  }
  await page.waitForTimeout(250);
  const selector = side === 'source' ? '.t-popup_show[aria-label*="избранное"] .t-popup__container' : '.commerce-modal-wishlist';
  const panel = page.locator(selector).last();
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
      assert.match(local.text, /добавьте товары в избранное/i);
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
