const assert = require('node:assert/strict');
const http = require('node:http');
const { chromium } = require('playwright');

const baseUrl = (process.env.THEOBROMA_URL || 'http://localhost:8080').replace(/\/$/, '');
const sourceUrl = new URL(baseUrl);
const snapshots = new Map();
const routes = [
  {
    path: '/',
    selectors: ['.home-composition h2', '.home-composition__intro > p:last-child', '.home-promo-card h2', '.review p', '.footer-mail strong'],
  },
  {
    path: '/catalog/',
    selectors: ['.catalog-title', '.catalog-product-description', '.footer-map h3'],
  },
  {
    path: '/recipes/',
    selectors: ['.recipes-intro > h1', '.recipes-lead', '.recipe-card h2', '.recipe-card p'],
  },
  {
    path: '/delivery/',
    selectors: ['.delivery-page > h1', '.delivery-lead', '.delivery-accordion summary span'],
  },
  {
    path: '/cooperation/',
    selectors: ['.cooperation-page > h1', '.cooperation-lead', '.cooperation-form h2'],
  },
  {
    path: '/corporate-gifts/',
    selectors: ['.corporate-gifts-hero h1', '.corporate-gifts-hero > p:last-of-type', '.corporate-gifts-benefits h2'],
  },
  {
    path: '/my-account/',
    selectors: ['.woocommerce h2', '.woocommerce form label', '.footer-mail strong'],
  },
  { path: '/product/theobroma-200-68-coriander/', selectors: [] },
  { path: '/recipe/classic/', selectors: [] },
  { path: '/media/', selectors: [] },
  { path: '/buy/', selectors: [] },
  { path: '/policy/', selectors: [] },
  { path: '/cart/', selectors: [] },
];

const comparisons = [
  { from: 600, to: 768, expectedRatio: 1.035 },
  { from: 768, to: 900, expectedRatio: 1.01 },
  { from: 900, to: 1199, expectedRatio: 1.058 },
  { from: 1200, to: 1440, expectedRatio: 1.066 },
];

function fetchText(path) {
  return new Promise((resolve, reject) => {
    const request = http.get({
      hostname: sourceUrl.hostname,
      port: sourceUrl.port || 80,
      path,
      headers: { Host: 'localhost:8080' },
    }, (response) => {
      let body = '';
      response.setEncoding('utf8');
      response.on('data', (chunk) => { body += chunk; });
      response.on('end', () => resolve(body));
    });
    request.on('error', reject);
  });
}

async function snapshotFor(route) {
  if (!snapshots.has(route.path)) {
    const source = await fetchText(route.path);
    const stylesheets = Array.from(source.matchAll(/<link\b[^>]*rel=['"]stylesheet['"][^>]*href=['"]([^'"]+)/gi), (match) => match[1]);
    let css = '';
    for (const href of stylesheets) {
      const url = new URL(href, 'http://localhost:8080');
      if (url.hostname === 'localhost') {
        css += `\n${await fetchText(`${url.pathname}${url.search}`)}`;
      }
    }
    snapshots.set(route.path, {
      html: source
        .replace(/<link\b[^>]*rel=['"]stylesheet['"][^>]*>/gi, '')
        .replace(/<script\b[\s\S]*?<\/script>/gi, ''),
      css,
    });
  }
  return snapshots.get(route.path);
}

async function fontSizes(browser, route, width) {
  const page = await browser.newPage({
    viewport: { width, height: 1200 },
    reducedMotion: 'reduce',
  });
  try {
    const snapshot = await snapshotFor(route);
    await page.setContent(snapshot.html, { waitUntil: 'domcontentloaded' });
    await page.addStyleTag({ content: snapshot.css });
    await page.evaluate(() => document.fonts.ready);
    return await page.evaluate((selectors) => {
      const elements = Array.from(document.querySelectorAll('body *'));
      const text = elements.flatMap((element, index) => {
        const hasDirectText = Array.from(element.childNodes).some(
          (node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim(),
        );
        const style = getComputedStyle(element);
        const fontSize = parseFloat(style.fontSize);
        if (!hasDirectText || !Number.isFinite(fontSize) || fontSize <= 0 || style.display === 'none' || style.visibility === 'hidden' || !element.getClientRects().length) return [];
        return [{
          index,
          fontSize,
          label: `${element.tagName.toLowerCase()}.${String(element.className || '').trim().replace(/\s+/g, '.')} (${element.textContent.trim().slice(0, 50)})`,
        }];
      });
      return {
        rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
        hasHorizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        selectors: Object.fromEntries(selectors.map((selector) => {
          const element = document.querySelector(selector);
          return [selector, element ? parseFloat(getComputedStyle(element).fontSize) : null];
        })),
        text,
      };
    }, route.selectors);
  } finally {
    await page.close();
  }
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const comparison of comparisons) {
      for (const route of routes) {
        const [fromSizes, toSizes] = await Promise.all([
          fontSizes(browser, route, comparison.from),
          fontSizes(browser, route, comparison.to),
        ]);
        assert.equal(fromSizes.hasHorizontalOverflow, false, `${route.path} must not overflow horizontally at ${comparison.from}px`);
        assert.equal(toSizes.hasHorizontalOverflow, false, `${route.path} must not overflow horizontally at ${comparison.to}px`);
        for (const selector of route.selectors) {
          assert.notEqual(fromSizes.selectors[selector], null, `${route.path}: missing ${selector} at ${comparison.from}px`);
          assert.notEqual(toSizes.selectors[selector], null, `${route.path}: missing ${selector} at ${comparison.to}px`);
          const ratio = toSizes.selectors[selector] / fromSizes.selectors[selector];
          assert.ok(
            ratio >= comparison.expectedRatio - 0.01,
            `${route.path} ${selector} must scale from ${comparison.from}px to ${comparison.to}px; ratio ${ratio.toFixed(3)}`,
          );
        }
        const toText = new Map(toSizes.text.map((item) => [item.index, item]));
        for (const item of fromSizes.text) {
          const next = toText.get(item.index);
          if (!next) continue;
          const ratio = next.fontSize / item.fontSize;
          assert.ok(
            ratio >= comparison.expectedRatio - 0.01,
            `${route.path} ${item.label} must scale from ${comparison.from}px to ${comparison.to}px; ratio ${ratio.toFixed(3)}`,
          );
        }
      }
    }
    for (const width of [390, 1920, 2560, 3200]) {
      for (const route of routes) {
        const metrics = await fontSizes(browser, route, width);
        assert.equal(metrics.hasHorizontalOverflow, false, `${route.path} must not overflow horizontally at ${width}px`);
        if (width >= 2560) {
          assert.ok(Math.abs(metrics.rootFontSize - 20) <= 0.01, `${route.path} root rem must stay capped at 20px at ${width}px`);
        }
      }
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
