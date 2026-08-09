const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'style.css'),
  'utf8',
);

function assertClose(actual, expected, message) {
  if (Math.abs(actual - expected) > 1) {
    throw new Error(`${message}: expected ${expected}px, received ${actual}px`);
  }
}

async function footerMetrics(browser, viewportWidth) {
  const page = await browser.newPage({ viewport: { width: viewportWidth, height: 900 } });
  await page.setContent(`
    <style>${stylesheet}</style>
    <footer class="site-footer">
      <div class="footer-shell">
        <div class="footer-map"></div>
        <div class="footer-logo"></div>
        <div class="footer-phones"></div>
        <div class="footer-card footer-address"></div>
        <div class="footer-media"></div>
        <div class="footer-card footer-mail"></div>
        <div class="footer-card footer-mail"></div>
        <div class="footer-card footer-mail"></div>
      </div>
      <div class="copyright"></div>
    </footer>
  `);

  const metrics = await page.evaluate(() => {
    const bounds = (selector) => {
      const rect = document.querySelector(selector).getBoundingClientRect();
      return { left: rect.left, right: rect.right, width: rect.width };
    };

    return {
      shell: bounds('.footer-shell'),
      map: bounds('.footer-map'),
      phones: bounds('.footer-phones'),
    };
  });

  await page.close();
  return metrics;
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const mobile = await footerMetrics(browser, 390);
    assertClose(mobile.shell.left, 0, '390px footer shell must reach the left viewport edge');
    assertClose(mobile.shell.right, 390, '390px footer shell must reach the right viewport edge');
    assertClose(mobile.map.left, 20, '390px footer content must keep a 20px left inset');
    assertClose(mobile.map.right, 370, '390px footer content must keep a 20px right inset');

    const tablet = await footerMetrics(browser, 768);
    assertClose(tablet.shell.left, 40, '768px footer shell must keep a 40px left inset');
    assertClose(tablet.shell.right, 728, '768px footer shell must keep a 40px right inset');
    assertClose(tablet.map.width, tablet.phones.width, 'tablet footer columns must have equal widths');
    assertClose(tablet.map.left - tablet.shell.left, tablet.shell.right - tablet.phones.right, 'tablet footer outer insets must be equal');
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
