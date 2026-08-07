const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';
const outputDir = path.resolve(__dirname, '..', 'output', 'playwright', 'core-web-vitals');
const routes = [
  { id: 'home', path: '/' },
  { id: 'catalog', path: '/catalog/' },
  { id: 'product', path: '/product/theobroma-200-70/' },
  { id: 'article', path: '/chto-oznachayut-protsenty-na-plitke-shokolada/' },
];
const profiles = [
  { id: 'desktop', viewport: { width: 1440, height: 1200 }, cpuRate: 1 },
  {
    id: 'mobile-fast-4g',
    viewport: { width: 390, height: 844 },
    cpuRate: 4,
    network: {
      offline: false,
      latency: 150,
      downloadThroughput: 1.6 * 1024 * 1024 / 8,
      uploadThroughput: 750 * 1024 / 8,
      connectionType: 'cellular4g',
    },
  },
];
const thresholds = { lcp: 2500, cls: 0.1, inp: 200, ttfb: 800 };
const selectedRouteIds = new Set((process.env.THEOBROMA_CWV_ROUTES || '').split(',').filter(Boolean));
const selectedRoutes = selectedRouteIds.size ? routes.filter((route) => selectedRouteIds.has(route.id)) : routes;

async function installObservers(page) {
  await page.addInitScript(() => {
    localStorage.setItem('theobroma_cookie_notice_accepted', '1');
    window.__theobromaVitals = { lcp: 0, cls: 0, inp: 0, longTasks: [] };
    new PerformanceObserver((list) => {
      const entries = list.getEntries();
      const last = entries[entries.length - 1];
      if (last) {
        window.__theobromaVitals.lcp = last.startTime;
        window.__theobromaVitals.lcpElement = last.element?.outerHTML?.slice(0, 240) || '';
      }
    }).observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (!entry.hadRecentInput) window.__theobromaVitals.cls += entry.value;
      }
    }).observe({ type: 'layout-shift', buffered: true });
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (entry.interactionId) {
          window.__theobromaVitals.inp = Math.max(window.__theobromaVitals.inp, entry.duration);
        }
      }
    }).observe({ type: 'event', buffered: true, durationThreshold: 16 });
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        window.__theobromaVitals.longTasks.push(Math.round(entry.duration));
      }
    }).observe({ type: 'longtask', buffered: true });
  });
}

async function interact(page) {
  if (await page.locator('.commerce-modal.is-open').count()) return;
  const menu = page.locator('.menu-toggle:visible');
  if (await menu.count()) {
    await menu.click();
    await page.waitForTimeout(150);
    await page.locator('.mobile-menu-close:visible').click();
  }
  await page.waitForTimeout(300);
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  try {
    for (const profile of profiles) {
      for (const route of selectedRoutes) {
        const context = await browser.newContext({
          viewport: profile.viewport,
          deviceScaleFactor: 1,
          reducedMotion: 'reduce',
        });
        const page = await context.newPage();
        await installObservers(page);
        const cdp = await context.newCDPSession(page);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: profile.cpuRate });
        if (profile.network) {
          await cdp.send('Network.enable');
          await cdp.send('Network.emulateNetworkConditions', profile.network);
        }
        const response = await page.goto(new URL(route.path, `${baseUrl}/`).href, {
          waitUntil: 'networkidle',
          timeout: 60000,
        });
        await page.evaluate(() => document.fonts.ready);
        await page.waitForTimeout(500);
        await interact(page);
        const measured = await page.evaluate(() => {
          const navigation = performance.getEntriesByType('navigation')[0];
          const resources = performance.getEntriesByType('resource');
          const paints = performance.getEntriesByType('paint');
          return {
            ...window.__theobromaVitals,
            firstContentfulPaint: paints.find((entry) => entry.name === 'first-contentful-paint')?.startTime || 0,
            ttfb: navigation.responseStart,
            domContentLoaded: navigation.domContentLoadedEventEnd,
            load: navigation.loadEventEnd,
            transferBytes: resources.reduce((total, entry) => total + (entry.transferSize || 0), 0),
            resourceCount: resources.length,
            reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
            heroAnimation: getComputedStyle(document.querySelector('.hero h1 i') || document.body).animationName,
            heroOpacity: getComputedStyle(document.querySelector('.hero h1 i') || document.body).opacity,
            criticalResources: resources
              .filter((entry) => /style\.css|\/fonts\/|hero-bg|hero-chocolate/.test(entry.name))
              .map((entry) => ({
                name: entry.name.split('/').pop().split('?')[0],
                start: Math.round(entry.startTime),
                end: Math.round(entry.responseEnd),
                bytes: entry.transferSize || 0,
              })),
            largestResources: resources
              .map((entry) => ({ name: entry.name.split('/').pop().split('?')[0], bytes: entry.transferSize || 0, end: Math.round(entry.responseEnd) }))
              .sort((left, right) => right.bytes - left.bytes)
              .slice(0, 15),
          };
        });
        const result = {
          profile: profile.id,
          route: route.id,
          status: response?.status() || null,
          ...Object.fromEntries(Object.entries(measured).map(([key, value]) => (
            typeof value === 'number' ? [key, Math.round(value * 100) / 100] : [key, value]
          ))),
        };
        if (result.inp === 0) result.inpUpperBound = 16;
        results.push(result);
        console.log(`${profile.id} ${route.id}: LCP ${result.lcp}ms, CLS ${result.cls}, INP ${result.inp}ms, TTFB ${result.ttfb}ms`);
        await context.close();
      }
    }
  } finally {
    await browser.close();
  }

  fs.mkdirSync(outputDir, { recursive: true });
  const reportPath = path.join(outputDir, 'report.json');
  fs.writeFileSync(reportPath, `${JSON.stringify({ thresholds, results }, null, 2)}\n`);
  console.log(`Report: ${reportPath}`);

  const failures = results.filter((result) => (
    result.status !== 200
    || result.lcp <= 0
    || result.lcp > thresholds.lcp
    || result.cls > thresholds.cls
    || result.inp > thresholds.inp
    || result.ttfb > thresholds.ttfb
  ));
  if (failures.length) {
    console.error(`Core Web Vitals failures: ${failures.map((item) => `${item.profile}/${item.route}`).join(', ')}`);
    process.exitCode = 1;
  }
})();
