const assert = require('node:assert/strict');
const path = require('node:path');
const { spawn } = require('node:child_process');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const port = 8898;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', root], {
  cwd: root,
  stdio: 'ignore',
  windowsHide: true,
});

const waitForServer = async () => {
  for (let attempt = 0; attempt < 40; attempt += 1) {
    try {
      const response = await fetch(`http://127.0.0.1:${port}/scripts/verify-marketplace-product-links.php?render=1`);
      if (response.ok) return;
    } catch {}
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
  throw new Error('Marketplace layout fixture did not start.');
};

(async () => {
  await waitForServer();
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of [390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
      await page.goto(`http://127.0.0.1:${port}/scripts/verify-marketplace-product-links.php?render=1`, { waitUntil: 'networkidle' });
      await page.evaluate(async () => document.fonts?.ready);
      const cards = await page.locator('.market-product').evaluateAll((elements) => elements.map((element) => {
        const card = element.getBoundingClientRect();
        const actions = element.querySelector('.market-product-actions').getBoundingClientRect();
        return { height: card.height, cardBottom: card.bottom, actionsBottom: actions.bottom };
      }));

      assert.equal(cards.length, 4, `${width}px: expected four product cards`);
      const expectedHeight = cards[0].height;
      cards.forEach((card, index) => {
        assert.ok(Math.abs(card.height - expectedHeight) <= 1, `${width}px: card ${index + 1} height differs from the first card`);
        assert.ok(Math.abs(card.cardBottom - card.actionsBottom) <= 1, `${width}px: card ${index + 1} actions are not aligned to its bottom edge`);
      });
      await page.close();
    }
  } finally {
    await browser.close();
  }
  console.log('Marketplace card heights: OK');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
}).finally(() => {
  server.kill();
});
