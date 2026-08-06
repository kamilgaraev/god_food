const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');

const config = require('./pairwise-audit.config.json');
const sourceBase = process.env.THEOBROMA_SOURCE_URL || 'https://theobroma.one/';
const localBase = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080/';
const outputRoot = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'pairwise-states');
const args = Object.fromEntries(process.argv.slice(2).map((argument) => {
  const [key, value = 'true'] = argument.replace(/^--/, '').split('=');
  return [key, value];
}));
const widths = (args.widths || '390,430,768,1440,1920,2048,2560,3840').split(',');
const requestedStates = new Set((args.states || 'header,cookie,menu,account,cart-empty').split(','));

function pad(image, width, height) {
  const padded = new PNG({ width, height });
  padded.data.fill(255);
  PNG.bitblt(image, padded, 0, 0, image.width, image.height, 0, 0);
  return padded;
}

async function comparePng(sourceFile, localFile, diffFile) {
  const { default: pixelmatch } = await import(pathToFileURL(require.resolve('pixelmatch')).href);
  const source = PNG.sync.read(fs.readFileSync(sourceFile));
  const local = PNG.sync.read(fs.readFileSync(localFile));
  const width = Math.max(source.width, local.width);
  const height = Math.max(source.height, local.height);
  const sourcePadded = pad(source, width, height);
  const localPadded = pad(local, width, height);
  const diff = new PNG({ width, height });
  const pixels = pixelmatch(sourcePadded.data, localPadded.data, diff.data, width, height, { threshold: 0.1, includeAA: false });
  fs.writeFileSync(diffFile, PNG.sync.write(diff));
  return { pixels, ratio: pixels / (width * height), width, height };
}

async function visibleCandidates(locator, viewport) {
  const candidates = [];
  for (let index = 0; index < await locator.count(); index += 1) {
    const item = locator.nth(index);
    const box = await item.boundingBox();
    if (box && box.width > 0 && box.height > 0 && box.x < viewport.width && box.x + box.width > 0 && box.y < viewport.height && box.y + box.height > 0) {
      candidates.push({ item, box });
    }
  }
  return candidates.sort((a, b) => (b.box.width * b.box.height) - (a.box.width * a.box.height));
}

async function largestVisible(locator, viewport) {
  return (await visibleCandidates(locator, viewport))[0] || null;
}

async function sourceAccountControl(page, viewport) {
  const direct = await largestVisible(page.locator('a[href="#openmembersbar"],a:has-text("ЛК")'), viewport);
  if (direct) return direct;
  const label = await largestVisible(page.getByText('ЛК', { exact: true }), viewport);
  if (!label) return null;
  const box = await label.item.evaluate((element) => {
    let control = element;
    while (control.parentElement) {
      const rect = control.getBoundingClientRect();
      if (rect.width >= 35 && rect.height >= 35) break;
      control = control.parentElement;
    }
    const rect = control.getBoundingClientRect();
    return { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
  });
  return { item: label.item, box };
}

async function actionPartBox(page, selector, viewport) {
  const candidate = await largestVisible(page.locator(selector), viewport);
  return candidate?.box || null;
}

async function dismissCookie(page, side) {
  const cookie = side === 'source' ? page.locator('.t657:visible') : page.locator('.cookie-notice:visible');
  if (!await cookie.count()) return;
  const button = side === 'source'
    ? cookie.locator('button,a').filter({ hasText: /НЕ ПОКАЗЫВАТЬ|OK/i }).last()
    : cookie.locator('button').last();
  if (await button.count()) await button.click({ force: true });
  await cookie.first().waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {});
}

async function settle(page) {
  await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });
  await page.evaluate(async () => {
    if (document.fonts?.ready) await Promise.race([document.fonts.ready, new Promise((resolve) => setTimeout(resolve, 3000))]);
  });
  await page.waitForTimeout(200);
}

async function waitForSourceAccount(page, timeout) {
  return page.waitForFunction(() => [...document.querySelectorAll('h1,h2,h3,div')].some((element) => {
    const text = element.textContent.replace(/\s+/g, ' ').trim();
    const rect = element.getBoundingClientRect();
    return text === 'Войти или создать профиль' && rect.width > 0 && rect.height > 0;
  }), null, { timeout }).then(() => true).catch(() => false);
}

async function headerMetrics(page, side, viewport) {
  if (side === 'source') {
    const account = await sourceAccountControl(page, viewport);
    const menu = await largestVisible(page.locator('a[href="#menuopen"]'), viewport);
    return {
      account: account?.box || null,
      accountHref: account ? await account.item.getAttribute('href') : null,
      accountTarget: account ? await account.item.getAttribute('target') : null,
      menu: menu?.box || null,
      actionParts: {
        accountIcon: await actionPartBox(page, 'img[data-original*="user_4_1"]', viewport),
        accountText: await actionPartBox(page, '[data-elem-id="1764672147761000001"] .tn-atom', viewport),
        cartIcon: await actionPartBox(page, '.mycart010 img', viewport),
        cartText: await actionPartBox(page, '.mycount010 .tn-atom', viewport),
        favoriteIcon: await actionPartBox(page, '.nolimWish065 img', viewport),
        favoriteText: await actionPartBox(page, '.wishNolimQuantity065 .tn-atom', viewport),
      },
    };
  }
  const box = async (selector) => page.locator(selector).evaluate((element) => {
    const rect = element.getBoundingClientRect();
    return { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
  });
  return {
    account: await box('[data-account-trigger]'),
    menu: viewport.width <= 900 ? await box('.menu-toggle') : null,
    actionParts: {
      accountIcon: await actionPartBox(page, '[data-account-trigger] img', viewport),
      accountText: await actionPartBox(page, '[data-account-trigger] span', viewport),
      cartIcon: await actionPartBox(page, '.floating-actions a:nth-child(1) img', viewport),
      cartText: await actionPartBox(page, '.floating-actions a:nth-child(1) span', viewport),
      favoriteIcon: await actionPartBox(page, '.floating-actions a:nth-child(2) img', viewport),
      favoriteText: await actionPartBox(page, '.floating-actions a:nth-child(2) span', viewport),
    },
  };
}

async function openState(page, side, state, viewport) {
  if (!['cookie', 'account', 'cart-empty'].includes(state)) await dismissCookie(page, side);
  if (state === 'header' || state === 'cookie') return;

  if (state === 'menu') {
    if (viewport.width > 900) return;
    if (side === 'source') {
      const trigger = await largestVisible(page.locator('a[href="#menuopen"]'), viewport);
      assert.ok(trigger, `${viewport.width}px source menu trigger is missing`);
      await trigger.item.click({ force: true });
      await page.locator('.t450__container:visible').waitFor({ timeout: 5000 });
    } else {
      await page.locator('.menu-toggle').click();
      await page.locator('.mobile-menu[aria-hidden="false"]').waitFor();
    }
    return;
  }

  if (state === 'account') {
    if (side === 'source') {
      let opened = await waitForSourceAccount(page, 100);
      if (!opened) {
        const hashTriggers = await visibleCandidates(page.locator('a[href="#openmembersbar"]'), viewport);
        for (const candidate of hashTriggers) {
          await page.mouse.click(candidate.box.x + candidate.box.width / 2, candidate.box.y + candidate.box.height / 2);
          opened = await waitForSourceAccount(page, 1000);
          if (opened) break;
        }
      }
      if (!opened) {
        const trigger = await sourceAccountControl(page, viewport);
        if (trigger) {
          await page.mouse.click(trigger.box.x + trigger.box.width / 2, trigger.box.y + trigger.box.height / 2);
        }
        opened = await waitForSourceAccount(page, 5000);
      }
    } else {
      await page.locator('[data-account-trigger]').click();
      await page.locator('#account-modal.is-open #account-email').waitFor();
    }
    return;
  }

  if (state === 'cart-empty') {
    if (side === 'source') {
      const count = await largestVisible(page.locator('.mycount010'), viewport);
      assert.ok(count, `${viewport.width}px source cart counter is missing`);
      const cartControl = await largestVisible(page.locator('.mycart010'), viewport);
      if (cartControl) await cartControl.item.click({ force: true });
      else await page.mouse.click(count.box.x - 15, count.box.y + count.box.height / 2);
      const message = page.locator('.t-popup_show[aria-label*="Пожалуйста"]');
      const opened = await message.waitFor({ timeout: 2500 }).then(() => true).catch(() => false);
      if (!opened) {
        await page.locator('[href="#popup:emptybag"]').first().evaluate((element) => element.click());
        await message.waitFor({ timeout: 5000 }).catch(async (error) => {
          const debug = await page.evaluate(({ x, y }) => {
            const hit = document.elementFromPoint(x, y);
            const ancestors = [];
            for (let element = hit; element && ancestors.length < 6; element = element.parentElement) {
              ancestors.push({ tag: element.tagName, className: String(element.className), id: element.id, onclick: element.getAttribute('onclick') });
            }
            return {
              url: location.href,
              hit: hit?.outerHTML?.slice(0, 800) || null,
              hash: location.hash,
              ancestors,
              cartGlobals: Object.keys(window).filter((key) => /cart.*(open|show)|(open|show).*cart/i.test(key)),
            };
          }, { x: count.box.x - 15, y: count.box.y + count.box.height / 2 });
          throw new Error(`${error.message}\nsource cart click debug: ${JSON.stringify(debug)}`);
        });
      }
    } else {
      await page.locator('.floating-actions a:first-child').click();
      await page.locator('#commerce-modal.is-open .commerce-cart-empty').waitFor();
    }
  }
}

async function stateBox(page, side, state) {
  if (side === 'source' && state === 'account') {
    return page.evaluate(() => {
      const matches = [...document.querySelectorAll('h1,h2,h3,div')].filter((element) => {
        const text = element.textContent.replace(/\s+/g, ' ').trim();
        const rect = element.getBoundingClientRect();
        return text === 'Войти или создать профиль' && rect.width > 0 && rect.height > 0;
      }).sort((a, b) => {
        const first = a.getBoundingClientRect();
        const second = b.getBoundingClientRect();
        return first.width * first.height - second.width * second.height;
      });
      if (!matches.length) return null;
      let candidate = matches[0];
      while (candidate.parentElement) {
        const parent = candidate.parentElement;
        const rect = parent.getBoundingClientRect();
        const style = getComputedStyle(parent);
        if (rect.width >= 300 && rect.height >= 80 && style.backgroundColor !== 'rgba(0, 0, 0, 0)') candidate = parent;
        else if (candidate !== matches[0]) break;
        else candidate = parent;
      }
      const rect = candidate.getBoundingClientRect();
      return { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
    });
  }
  const selector = {
    cookie: side === 'source' ? '.t657:visible' : '.cookie-notice:visible',
    menu: side === 'source' ? '.t450__container:visible' : '.mobile-menu[aria-hidden="false"]',
    account: '#account-modal.is-open .account-modal-panel',
    'cart-empty': side === 'source' ? '.t-popup_show[aria-label*="Пожалуйста"] .t396__artboard' : '.commerce-modal-cart:has(.commerce-cart-empty)',
  }[state];
  if (!selector) return null;
  const locator = page.locator(selector).last();
  if (!await locator.count()) return null;
  if (side === 'source' && (state === 'account' || state === 'cart-empty')) {
    return locator.evaluate((element) => {
      let candidate = element;
      while (candidate.parentElement) {
        const parent = candidate.parentElement;
        const rect = parent.getBoundingClientRect();
        const style = getComputedStyle(parent);
        if (rect.width >= 300 && rect.height >= 80 && style.backgroundColor !== 'rgba(0, 0, 0, 0)') candidate = parent;
        else if (candidate !== element) break;
        else candidate = parent;
      }
      const rect = candidate.getBoundingClientRect();
      return { x: rect.x, y: rect.y, width: rect.width, height: rect.height };
    });
  }
  return locator.boundingBox();
}

async function capture(browser, side, state, viewport, target) {
  const context = await browser.newContext({ viewport, deviceScaleFactor: 1, reducedMotion: 'reduce' });
  const page = await context.newPage();
  const errors = [];
  page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('pageerror', (error) => errors.push(error.message));
  const response = await page.goto(side === 'source' ? sourceBase : localBase, {
    waitUntil: side === 'source' ? 'networkidle' : 'domcontentloaded',
    timeout: 60000,
  });
  await settle(page);
  await openState(page, side, state, viewport);
  await page.waitForTimeout(250);
  const metrics = await page.evaluate(() => ({
    viewportWidth: document.documentElement.clientWidth,
    scrollWidth: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth),
  }));
  const result = {
    status: response?.status() || null,
    errors,
    ...metrics,
    box: await stateBox(page, side, state),
    header: state === 'header' ? await headerMetrics(page, side, viewport) : null,
  };
  const options = state === 'header'
    ? { path: target, clip: { x: 0, y: 0, width: viewport.width, height: Math.min(240, viewport.height) }, animations: 'disabled' }
    : { path: target, fullPage: false, animations: 'disabled' };
  await page.screenshot(options);
  await context.close();
  return result;
}

(async () => {
  fs.mkdirSync(outputRoot, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const widthKey of widths) {
      const viewport = config.viewports[widthKey];
      if (!viewport) throw new Error(`Unknown viewport ${widthKey}`);
      const states = [...requestedStates].filter((state) => state !== 'menu' || viewport.width <= 900);
      const viewportDir = path.join(outputRoot, widthKey);
      fs.mkdirSync(viewportDir, { recursive: true });
      for (const state of states) {
        const sourceFile = path.join(viewportDir, `${state}-source.png`);
        const localFile = path.join(viewportDir, `${state}-local.png`);
        const diffFile = path.join(viewportDir, `${state}-diff.png`);
        const source = await capture(browser, 'source', state, viewport, sourceFile);
        const local = await capture(browser, 'local', state, viewport, localFile);
        assert.equal(source.status, 200, `${widthKey}px ${state}: source HTTP ${source.status}`);
        assert.equal(local.status, 200, `${widthKey}px ${state}: local HTTP ${local.status}`);
        assert.deepEqual(local.errors, [], `${widthKey}px ${state}: local browser errors`);
        assert.ok(local.scrollWidth - local.viewportWidth <= 1, `${widthKey}px ${state}: local horizontal overflow`);
        if (state === 'header' && viewport.width <= 2048) {
          console.log(`${widthKey}px account boxes`, source.header.account, local.header.account);
          for (const key of ['x', 'y', 'width', 'height']) {
            const delta = Math.abs(source.header.account[key] - local.header.account[key]);
            assert.ok(delta <= 1.25, `${widthKey}px account ${key} differs by ${delta}px`);
          }
          if (viewport.width <= 900) {
            for (const part of Object.keys(source.header.actionParts)) {
              assert.equal(Boolean(local.header.actionParts[part]), Boolean(source.header.actionParts[part]), `${widthKey}px ${part} visibility differs`);
              if (!source.header.actionParts[part]) continue;
              for (const key of ['x', 'y', 'width', 'height']) {
                const delta = Math.abs(source.header.actionParts[part][key] - local.header.actionParts[part][key]);
                assert.ok(delta <= 1.25, `${widthKey}px ${part} ${key} differs by ${delta}px`);
              }
            }
          }
        }
        const diff = await comparePng(sourceFile, localFile, diffFile);
        results.push({ width: widthKey, state, source, local, diff });
        console.log(`${widthKey}px ${state}: ${(diff.ratio * 100).toFixed(2)}% diff`);
      }
    }
  } finally {
    await browser.close();
  }
  const report = path.join(outputRoot, `report-${widths.join('-')}.json`);
  fs.writeFileSync(report, `${JSON.stringify(results, null, 2)}\n`);
  console.log(`Report: ${report}`);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
