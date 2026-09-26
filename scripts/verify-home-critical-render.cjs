const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');

const url = process.env.THEOBROMA_URL || 'https://theobroma.one/';
const themeUrl = new URL('/wp-content/themes/theobroma', url).href.replace(/\/$/, '');
const critical = fs.readFileSync(path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'assets', 'css', 'home-critical.css'), 'utf8')
  .replaceAll('__THEME_URI__', themeUrl);

(async () => {
  const { default: pixelmatch } = await import('pixelmatch');
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-proxy-server', '--disable-http2'] });
  try {
    for (const width of [320, 390, 600, 768, 1200, 1440]) {
      const shots = [];
      for (const onlyCritical of [false, true]) {
        const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
        await page.addInitScript(() => localStorage.setItem('theobroma_cookie_notice_accepted', '1'));
        await page.route(/^https:\/\/unpkg\.com\/leaflet@1\.9\.4\/dist\/leaflet\.(?:css|js)/, async (route) => {
          const file = path.join(__dirname, '..', 'wp-content', 'plugins', 'theobroma-commerce', 'assets', 'vendor', 'leaflet-1.9.4', path.basename(new URL(route.request().url()).pathname));
          await route.fulfill({ path: file });
        });
        if (onlyCritical) {
          await page.route(url, async (route) => {
            const response = await route.fetch();
            const body = (await response.text())
              .replace(/<link rel='stylesheet' id='theobroma-style-css'[^>]*>\s*/, '')
              .replace(/<link rel='stylesheet' id='theobroma-home-redesign-css'[^>]*>\s*/, `<style id="test-home-critical">${critical}</style>`);
            await route.fulfill({ response, body });
          });
        }
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
        if (!onlyCritical) {
          await page.waitForFunction(() => ['theobroma-style-css', 'theobroma-home-redesign-css'].every((id) => document.getElementById(id)?.media === 'all'));
        }
        await page.evaluate(async () => {
          await document.fonts.ready;
          await Promise.all([...document.querySelectorAll('.site-header img,.home-hero img')].map((img) => img.decode().catch(() => {})));
        });
        await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' });
        const hero = await page.locator('.home-hero').boundingBox();
        const screenshot = await page.screenshot({ clip: { x: 0, y: 0, width, height: Math.min(900, Math.ceil(hero.y + hero.height)) } });
        shots.push(PNG.sync.read(screenshot));
        await page.close();
      }
      assert.equal(shots[0].width, shots[1].width);
      assert.equal(shots[0].height, shots[1].height);
      const diff = new PNG({ width: shots[0].width, height: shots[0].height });
      const changed = pixelmatch(shots[0].data, shots[1].data, diff.data, shots[0].width, shots[0].height, { threshold: 0.12 });
      const ratio = changed / (shots[0].width * shots[0].height);
      console.log(`${width}: ${(ratio * 100).toFixed(2)}% pixels differ`);
      assert(ratio < 0.005, `${width}: critical CSS changes first-screen appearance`);
    }
  } finally { await browser.close(); }
})().catch((error) => { console.error(error); process.exit(1); });
