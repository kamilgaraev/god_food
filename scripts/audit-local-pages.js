const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const config = require('./pairwise-audit.config.json');
const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';
const args = Object.fromEntries(process.argv.slice(2).map((argument) => {
  const [key, value = 'true'] = argument.replace(/^--/, '').split('=');
  return [key, value];
}));
const widths = (args.widths || '390,768,1440,2560').split(',');
const outputRoot = path.resolve(__dirname, '..', 'output', 'playwright', 'audit', 'local-only');

function routeId(route) {
  return decodeURIComponent(route).replace(/^\/|\/$/g, '').replace(/[^a-z0-9]+/gi, '-') || 'home';
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const widthKey of widths) {
      const viewport = config.viewports[widthKey];
      if (!viewport) throw new Error(`Unknown viewport "${widthKey}"`);
      const outputDir = path.join(outputRoot, widthKey);
      fs.mkdirSync(outputDir, { recursive: true });
      for (const route of config.localOnly) {
        const context = await browser.newContext({ viewport, deviceScaleFactor: 1, reducedMotion: 'reduce' });
        const page = await context.newPage();
        const consoleErrors = [];
        const pageErrors = [];
        page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
        page.on('pageerror', (error) => pageErrors.push(error.message));
        const response = await page.goto(new URL(route, `${baseUrl}/`).href, { waitUntil: 'networkidle', timeout: 45000 });
        const metrics = await page.evaluate(() => ({
          url: location.href,
          title: document.title,
          viewportWidth: document.documentElement.clientWidth,
          scrollWidth: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth),
          documentHeight: document.documentElement.scrollHeight,
          heading: document.querySelector('h1')?.textContent.replace(/\s+/g, ' ').trim() || '',
        }));
        await page.screenshot({ path: path.join(outputDir, `${routeId(route)}.png`), fullPage: true, animations: 'disabled' });
        await context.close();
        const result = { route, width: widthKey, status: response?.status() || null, ...metrics, consoleErrors, pageErrors };
        results.push(result);
        console.log(`${widthKey}px ${route}: ${result.status} -> ${new URL(result.url).pathname}, overflow ${result.scrollWidth - result.viewportWidth}px`);
      }
    }
  } finally {
    await browser.close();
  }

  fs.mkdirSync(outputRoot, { recursive: true });
  const report = path.join(outputRoot, `report-${widths.join('-')}.json`);
  fs.writeFileSync(report, `${JSON.stringify(results, null, 2)}\n`);
  console.log(`Report: ${report}`);

  const failures = results.filter((result) => (
    result.status !== 200
    || result.scrollWidth - result.viewportWidth > 1
    || result.consoleErrors.length > 0
    || result.pageErrors.length > 0
  ));
  if (failures.length > 0) {
    throw new Error(`${failures.length} local page audits failed; inspect ${report}`);
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
