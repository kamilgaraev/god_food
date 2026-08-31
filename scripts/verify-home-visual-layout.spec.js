const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const VIEWPORTS = [320, 390, 521, 600, 768, 1101, 1199, 1200, 1440, 2048, 2560, 3200];
const failures = [];

function check(condition, message) {
  if (!condition) failures.push(message);
}

async function openHomepage(browser, width) {
  const page = await browser.newPage({ viewport: { width, height: 1200 }, reducedMotion: 'reduce' });
  const local = new URL(BASE_URL);
  if (local.port !== '8080') {
    await page.route('http://localhost:8080/**', async (route) => {
      const target = new URL(route.request().url());
      target.protocol = local.protocol;
      target.hostname = local.hostname;
      target.port = local.port;
      await route.continue({ url: target.href });
    });
  }
  await page.goto(BASE_URL, { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: '.cookie-notice { display: none !important; }' });
  await page.evaluate(() => document.fonts.ready);
  return page;
}

async function metricsFor(page) {
  return page.evaluate(() => {
    const rect = (selector) => {
      const box = document.querySelector(selector)?.getBoundingClientRect();
      return box && { left: box.left, right: box.right, top: box.top, bottom: box.bottom, width: box.width, height: box.height };
    };
    const style = (selector) => getComputedStyle(document.querySelector(selector));
    const productCards = Array.from(document.querySelectorAll('.home-product-card'), (card) => {
      const box = card.getBoundingClientRect();
      return { left: box.left, right: box.right, top: box.top, width: box.width };
    });
    const sharedContainers = [
      '.home-section-heading',
      '.home-product-grid',
      '.home-cacao__shell',
      '.home-composition__shell',
    ].map(rect);
    const promoCards = Array.from(document.querySelectorAll('.home-promo-card'), (card) => {
      const box = card.getBoundingClientRect();
      return { left: box.left, right: box.right };
    });

    return {
      viewport: document.documentElement.clientWidth,
      documentWidth: document.documentElement.scrollWidth,
      rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
      nav: rect('.nav'),
      hero: rect('.home-hero'),
      heroCopy: rect('.home-hero__copy'),
      heroVideo: rect('.home-hero__video-trigger'),
      trust: rect('.home-hero__trust'),
      lead: rect('.home-hero__lead'),
      leadText: rect('.home-hero__lead > p'),
      heroActions: rect('.home-hero__actions'),
      productGrid: rect('.home-product-grid'),
      productCards,
      productColumns: style('.home-product-grid').gridTemplateColumns.split(' ').filter(Boolean).length,
      productFlow: style('.home-product-grid').gridAutoFlow,
      cacaoPanel: rect('.home-cacao__panel'),
      cacaoColumns: style('.home-cacao__shell').gridTemplateColumns.split(' ').filter(Boolean).length,
      compositionColumns: style('.home-composition__shell').gridTemplateColumns.split(' ').filter(Boolean).length,
      sharedContainers,
      promoCards,
    };
  });
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const allMetrics = new Map();

  try {
    for (const width of VIEWPORTS) {
      const page = await openHomepage(browser, width);
      const metrics = await metricsFor(page);
      allMetrics.set(width, metrics);

      check(metrics.documentWidth === metrics.viewport, `${width}px document must not overflow horizontally`);
      check(metrics.nav.left >= -1 && metrics.nav.right <= metrics.viewport + 1, `${width}px header must fit the viewport`);
      check(metrics.cacaoPanel.left >= -1 && metrics.cacaoPanel.right <= metrics.viewport + 1, `${width}px cacao panel must fit the viewport`);

      if (width <= 600) {
        check(metrics.productColumns === 1 && metrics.productFlow === 'row', `${width}px mobile homepage catalog must use one column`);
        check(metrics.productCards[1].top > metrics.productCards[0].top, `${width}px homepage products must use separate rows`);
        check(metrics.productCards.every(({ width: cardWidth }) => cardWidth >= metrics.productGrid.width - 2), `${width}px mobile homepage products must fill their row`);
        check(metrics.productCards.every(({ left, right }) => left >= -1 && right <= metrics.viewport + 1), `${width}px mobile homepage products must fit the viewport`);
        check(metrics.cacaoColumns === 1 && metrics.compositionColumns === 1, `${width}px mobile content sections must use one column`);
        check(metrics.lead.bottom <= metrics.trust.top + 1 && metrics.trust.bottom <= metrics.heroVideo.top + 1, `${width}px mobile hero content must keep copy, trust and video in order`);
      } else if (width < 1200) {
        check(metrics.productColumns === 2, `${width}px tablet catalog must use two columns`);
        check(metrics.cacaoColumns === 1 && metrics.compositionColumns === 1, `${width}px tablet content sections must use one column`);
        check(metrics.heroCopy.right <= metrics.heroVideo.left + 1, `${width}px tablet hero copy must stay to the left of the video`);
        check(metrics.heroVideo.bottom <= metrics.hero.bottom + 1, `${width}px tablet hero video must fit inside the hero`);
      } else {
        check(metrics.productColumns === 4, `${width}px desktop catalog must use four columns`);
        check(metrics.cacaoColumns === 2 && metrics.compositionColumns === 2, `${width}px desktop content sections must use two columns`);
        const lefts = metrics.sharedContainers.map(({ left }) => left);
        const rights = metrics.sharedContainers.map(({ right }) => right);
        check(Math.max(...lefts) - Math.min(...lefts) <= 2, `${width}px desktop sections must share one left container edge`);
        check(Math.max(...rights) - Math.min(...rights) <= 2, `${width}px desktop sections must share one right container edge`);
        check(metrics.promoCards.length === 2 && Math.abs(metrics.promoCards[0].left - lefts[0]) <= 2 && Math.abs(metrics.promoCards[1].right - rights[0]) <= 2, `${width}px promo cards must align to the shared container`);
        check(metrics.heroCopy.right <= metrics.heroVideo.left + 1, `${width}px desktop hero copy must stay to the left of the video`);
      }

      await page.close();
    }

    const width2560 = allMetrics.get(2560).productGrid.width;
    const width3200 = allMetrics.get(3200).productGrid.width;
    check(Math.abs(width2560 - width3200) <= 1, 'desktop container must stop growing after the 2560px reference viewport');

    if (failures.length) throw new Error(`Home layout regressions:\n- ${failures.join('\n- ')}`);
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
