const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const config = require('./pairwise-audit.config.json');
const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';
const widths = (process.env.THEOBROMA_WIDTHS || '390,1440').split(',').map(Number);
const routes = [...new Set([...config.pairs.map((pair) => pair.local), ...config.localOnly])];
const outputDir = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'accessibility');

async function auditPage(page) {
  return page.evaluate(() => {
    const visible = (element) => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden';
    const text = (element) => (element.textContent || '').replace(/\s+/g, ' ').trim();
    const accessibleName = (element) => {
      const labelledBy = (element.getAttribute('aria-labelledby') || '')
        .split(/\s+/)
        .filter(Boolean)
        .map((id) => text(document.getElementById(id) || document.createElement('span')))
        .join(' ')
        .trim();
      const labels = element.labels ? [...element.labels].map(text).join(' ').trim() : '';
      const imageAlt = [...element.querySelectorAll('img[alt]')].map((image) => image.alt.trim()).join(' ').trim();
      return (element.getAttribute('aria-label') || labelledBy || labels || text(element) || imageAlt || element.getAttribute('title') || element.getAttribute('value') || '').trim();
    };
    const ids = [...document.querySelectorAll('[id]')].map((element) => element.id).filter(Boolean);
    const duplicateIds = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
    const controls = [...document.querySelectorAll('a[href],button,input:not([type="hidden"]),select,textarea')].filter(visible);
    const unnamedControls = controls.filter((element) => !accessibleName(element)).map((element) => element.outerHTML.slice(0, 240));
    const formControls = [...document.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]),select,textarea')].filter(visible);
    const unlabeledFields = formControls.filter((element) => !accessibleName(element)).map((element) => element.outerHTML.slice(0, 240));
    return {
      lang: document.documentElement.lang,
      title: document.title.trim(),
      mainCount: document.querySelectorAll('main').length,
      h1Count: document.querySelectorAll('h1').length,
      skipLink: Boolean(document.querySelector('a[href="#theobroma-main"]')),
      skipTarget: Boolean(document.querySelector('#theobroma-main')),
      duplicateIds,
      imagesWithoutAlt: [...document.querySelectorAll('img:not([alt])')].map((element) => element.outerHTML.slice(0, 240)),
      unnamedControls,
      unlabeledFields,
    };
  });
}

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const width of widths) {
      const context = await browser.newContext({ viewport: { width, height: width < 600 ? 932 : 1200 }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      for (const route of routes) {
        const errors = [];
        const onPageError = (error) => errors.push(error.message);
        const onConsole = (message) => { if (message.type() === 'error') errors.push(message.text()); };
        page.on('pageerror', onPageError);
        page.on('console', onConsole);
        const response = await page.goto(new URL(route, baseUrl).href, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForTimeout(100);
        const audit = await auditPage(page);
        const failures = [];
        if (!audit.lang.toLowerCase().startsWith('ru')) failures.push(`lang=${audit.lang || 'missing'}`);
        if (!audit.title) failures.push('document title is missing');
        if (audit.mainCount !== 1) failures.push(`expected one main landmark, found ${audit.mainCount}`);
        if (audit.h1Count < 1) failures.push('h1 is missing');
        if (!audit.skipLink || !audit.skipTarget) failures.push('skip link or target is missing');
        if (audit.duplicateIds.length) failures.push(`duplicate ids: ${audit.duplicateIds.join(', ')}`);
        if (audit.imagesWithoutAlt.length) failures.push(`${audit.imagesWithoutAlt.length} images without alt attribute`);
        if (audit.unnamedControls.length) failures.push(`${audit.unnamedControls.length} unnamed controls`);
        if (audit.unlabeledFields.length) failures.push(`${audit.unlabeledFields.length} unlabeled form fields`);
        if (errors.length) failures.push(`${errors.length} browser errors`);
        results.push({ width, route, status: response?.status() || null, finalUrl: page.url(), audit, errors, failures });
        console.log(`${width}px ${route}: ${failures.length ? `FAIL ${failures.join(' | ')}` : 'PASS'}`);
        page.removeListener('pageerror', onPageError);
        page.removeListener('console', onConsole);
      }
      await context.close();
    }
  } finally {
    await browser.close();
  }
  const reportPath = path.join(outputDir, `report-${widths.join('-')}.json`);
  fs.writeFileSync(reportPath, `${JSON.stringify(results, null, 2)}\n`);
  const failed = results.filter((result) => result.failures.length);
  console.log(`Accessibility routes: ${results.length - failed.length}/${results.length} passed; report ${reportPath}`);
  if (failed.length) process.exitCode = 1;
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
