const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');

const config = require('./pairwise-audit.config.json');
const sourceBase = process.env.THEOBROMA_SOURCE_URL || 'https://theobroma.one';
const localBase = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';
const outputRoot = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'pairs');
const args = Object.fromEntries(process.argv.slice(2).map((arg) => {
  const [key, value = 'true'] = arg.replace(/^--/, '').split('=');
  return [key, value];
}));
const widths = (args.widths || args.width || '390').split(',');
const group = args.group || 'core';
const offset = Number(args.offset || 0);
const limit = Number(args.limit || Number.MAX_SAFE_INTEGER);
const requestedIds = new Set((args.ids || '').split(',').filter(Boolean));
const selectedPairs = config.pairs
  .filter((pair) => (group === 'all' || pair.group === group) && (!requestedIds.size || requestedIds.has(pair.id)))
  .slice(offset, offset + limit);

function normalizeUrl(base, route) {
  return new URL(route, `${base}/`).href;
}

async function settlePage(page) {
  await page.addStyleTag({ content: `
    *,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}
    [role="alertdialog"],.cookie-notice,.cookie-banner,.cookie-consent{display:none!important}
  ` });
  const assetFailures = await page.evaluate(async () => {
    if (document.fonts && document.fonts.ready) await Promise.race([document.fonts.ready, new Promise((resolve) => setTimeout(resolve, 2500))]);
    const step = Math.max(500, Math.floor(innerHeight * 0.8));
    for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
      scrollTo(0, y);
      await new Promise((resolve) => setTimeout(resolve, 30));
    }
    const lazyElements = [...document.querySelectorAll('[data-original]')];
    const urls = [...new Set(lazyElements.map((element) => element.dataset.original).filter(Boolean))];
    for (const element of lazyElements) {
      if (element instanceof HTMLImageElement) element.src = element.dataset.original;
      else element.style.backgroundImage = `url("${element.dataset.original}")`;
    }
    const loaded = await Promise.all(urls.map((url) => new Promise((resolve) => {
      const image = new Image();
      const timeout = setTimeout(() => resolve(false), 5000);
      image.onload = () => { clearTimeout(timeout); resolve(true); };
      image.onerror = () => { clearTimeout(timeout); resolve(false); };
      image.src = url;
    })));
    await Promise.all([...document.images].map(async (image) => {
      if (typeof image.decode !== 'function') return;
      await image.decode().catch(() => {});
    }));
    scrollTo(0, 0);
    return loaded.filter((value) => !value).length;
  });
  await page.waitForTimeout(150);
  return assetFailures;
}

async function navigate(page, url) {
  let lastError;
  for (let attempt = 0; attempt < 4; attempt += 1) {
    try {
      return await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
    } catch (error) {
      lastError = error;
      await page.goto('about:blank', { waitUntil: 'commit', timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(1000 * (attempt + 1));
    }
  }
  throw lastError;
}

async function sourceProductIsComplete(page, viewport) {
  await page.waitForFunction(() => document.querySelectorAll('.js-product-relevant').length >= 4, null, { timeout: 5000 }).catch(() => {});
  return page.evaluate(({ width, height }) => {
    const documentHeight = document.documentElement.scrollHeight;
    const minimumHeight = width >= 901 ? 1800 : (width >= 601 ? 2200 : 1800);
    return document.querySelectorAll('.js-product-relevant').length >= 4 && documentHeight >= Math.min(minimumHeight, height);
  }, viewport);
}

async function capture(browser, side, url, viewport, target) {
  const context = await browser.newContext({ viewport, deviceScaleFactor: 1, reducedMotion: 'reduce' });
  const page = await context.newPage();
  const consoleErrors = [];
  const pageErrors = [];
  page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
  page.on('pageerror', (error) => pageErrors.push(error.message));
  let response = await navigate(page, url);
  if (new URL(url).pathname.replace(/\/$/, '') === '/media') {
    await page.waitForFunction(
      () => document.querySelectorAll('.js-feed-post,.media-card').length >= 4,
      null,
      { timeout: 10000 },
    ).catch(() => {});
  }
  let assetFailures = await settlePage(page);
  if (side === 'source' && url.includes('/tproduct/')) {
    for (let attempt = 0; attempt < 3 && !await sourceProductIsComplete(page, viewport); attempt += 1) {
      await page.waitForTimeout(1500 * (attempt + 1));
      response = await navigate(page, url);
      assetFailures = await settlePage(page);
    }
    if (!await sourceProductIsComplete(page, viewport)) throw new Error(`Incomplete source product page after retry: ${url}`);
  }
  const hasOpenModal = await page.locator('#commerce-modal.is-open .commerce-modal-panel').count() > 0;
  if (hasOpenModal) {
    await page.addStyleTag({ content: `
      body > *:not(#commerce-modal){display:none!important}
      #commerce-modal{position:relative!important;inset:auto!important;height:auto!important;overflow:visible!important}
    ` });
  }
  const metrics = await page.evaluate(() => ({
    url: location.href,
    title: document.title,
    viewportWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
    documentHeight: document.documentElement.scrollHeight,
    headings: [...document.querySelectorAll('h1,h2')].filter((node) => getComputedStyle(node).visibility !== 'hidden').map((node) => node.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean),
    links: document.querySelectorAll('a[href]').length,
    buttons: document.querySelectorAll('button').length,
    forms: document.querySelectorAll('form').length,
    dialogs: [...document.querySelectorAll('[role="dialog"],[role="alertdialog"]')].filter((node) => getComputedStyle(node).display !== 'none').length,
  }));
  await page.screenshot({ path: target, fullPage: true, animations: 'disabled' });
  await context.close();
  return { side, status: response?.status() || null, ...metrics, hasOpenModal, assetFailures, consoleErrors, pageErrors };
}

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
  return { pixels, ratio: pixels / (width * height), width, height, sourceHeight: source.height, localHeight: local.height };
}

(async () => {
  if (!selectedPairs.length) throw new Error(`No route pairs selected for group "${group}"`);
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const widthKey of widths) {
      const viewport = config.viewports[widthKey];
      if (!viewport) throw new Error(`Unknown viewport "${widthKey}"`);
      const viewportDir = path.join(outputRoot, widthKey);
      fs.mkdirSync(viewportDir, { recursive: true });
      for (const pair of selectedPairs) {
        const sourceFile = path.join(viewportDir, `${pair.id}-source.png`);
        const localFile = path.join(viewportDir, `${pair.id}-local.png`);
        const diffFile = path.join(viewportDir, `${pair.id}-diff.png`);
        const source = await capture(browser, 'source', normalizeUrl(sourceBase, pair.source), viewport, sourceFile);
        const local = await capture(browser, 'local', normalizeUrl(localBase, pair.local), viewport, localFile);
        const diff = await comparePng(sourceFile, localFile, diffFile);
        const result = { id: pair.id, group: pair.group, width: widthKey, source, local, diff };
        results.push(result);
        console.log(`${widthKey}px ${pair.id}: ${(diff.ratio * 100).toFixed(2)}% diff, heights ${diff.sourceHeight}/${diff.localHeight}`);
      }
    }
  } finally {
    await browser.close();
  }
  const reportFile = path.join(outputRoot, `report-${group}-${widths.join('-')}-${offset}-${selectedPairs.length}.json`);
  fs.writeFileSync(reportFile, `${JSON.stringify(results, null, 2)}\n`);
  console.log(`Report: ${reportFile}`);
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
