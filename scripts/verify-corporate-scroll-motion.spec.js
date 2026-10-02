const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, webkit } = require('playwright');

const base = process.env.CORPORATE_BASE_URL || 'https://theobroma.one';
const source = process.env.CORPORATE_MOTION_SOURCE === '1';
const engine = process.env.BROWSER_ENGINE || 'chromium';
const script = path.resolve(__dirname, '../wp-content/themes/theobroma/assets/js/corporate-gifts.js');
const output = path.resolve(__dirname, '../output/corporate-scroll-motion', source ? 'source' : 'live', engine);
const targets = '.cg-gift,.cg-details-intro,.cg-detail-grid article,.cg-gallery h2,.cg-gallery-track,.cg-season,.cg-faq details,.cg-reviews h2,.cg-review-track,.cg-request-layout > div';

async function setup(page) {
  const pending = new Set();
  page.on('request', request => pending.add(request));
  page.on('requestfinished', request => pending.delete(request));
  page.on('requestfailed', request => pending.delete(request));
  if (source) await page.route(/\/assets\/js\/corporate-gifts\.js(?:\?|$)/, route =>
    route.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(script) }));
  await page.addInitScript(selector => {
    window.cgScrollMotions = [];
    const animate = Element.prototype.animate;
    Element.prototype.animate = function(frames, options) {
      const animation = animate.call(this, frames, options);
      if (!this.matches(selector) || !frames[0]?.transform) return animation;
      const entry = { element: this, duration: options.duration, minimumOpacity: 1, frames: 0 };
      window.cgScrollMotions.push(entry);
      const sample = () => {
        const bounds = this.getBoundingClientRect();
        if (bounds.top < innerHeight && bounds.bottom > 0) {
          entry.minimumOpacity = Math.min(entry.minimumOpacity, Number(getComputedStyle(this).opacity));
          entry.frames++;
        }
        if (animation.playState !== 'finished' && animation.playState !== 'idle') requestAnimationFrame(sample);
      };
      requestAnimationFrame(sample);
      return animation;
    };
  }, targets);
  try {
    await page.goto(`${base}/corporate-gifts/`, { waitUntil: 'domcontentloaded' });
  } catch (error) {
    console.error('Pending navigation requests:', [...pending].map(request => { const url = new URL(request.url()); return url.origin + url.pathname; }));
    throw error;
  }
  await page.waitForFunction(() => document.querySelector('.cg-media-ready'));
  const cookie = page.getByRole('button', { name: 'Только необходимые' });
  if (await cookie.isVisible()) await cookie.click();
  await page.evaluate(() => document.querySelector('[data-cg-video]')?.pause());
}

(async () => {
  fs.mkdirSync(output, { recursive: true });
  const browser = await (engine === 'webkit' ? webkit.launch({ headless: true }) : chromium.launch({ channel: 'chrome', headless: true }));
  const context = await browser.newContext({ viewport: { width: 390, height: 1000 }, reducedMotion: 'no-preference' });
  try {
    for (const width of engine === 'webkit' ? [390] : [390, 768, 1440]) {
      const page = await context.newPage();
      await page.setViewportSize({ width, height: 1000 });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await setup(page);
      for (const section of ['.cg-gift-grid', '.cg-detail-grid', '.cg-gallery', '.cg-faq', '.cg-reviews', '.cg-request-layout']) {
        await page.locator(section).scrollIntoViewIfNeeded();
        await page.evaluate(() => Promise.all(document.querySelector('.corporate-redesign').getAnimations({ subtree: true })
          .filter(animation => animation.effect.getComputedTiming().iterations !== Infinity)
          .map(animation => animation.finished.catch(() => {}))));
      }
      const motions = await page.evaluate(() => window.cgScrollMotions.map(({ element, ...values }) => values));
      assert.ok(motions.length >= 12, `${width}: scroll exercises several sections`);
      assert.ok(motions.every(motion => motion.frames > 0), `${width}: animation sampled while visible`);
      assert.ok(motions.every(motion => motion.minimumOpacity >= 0.99), `${width}: visible content must never disappear or flicker`);
      assert.ok(motions.every(motion => motion.duration >= 800 && motion.duration <= 1200), `${width}: gradual entrance timing`);
      await page.locator('.cg-gift-grid').scrollIntoViewIfNeeded();
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      assert.equal(await page.evaluate(() => new Set(window.cgScrollMotions.map(motion => motion.element)).size === window.cgScrollMotions.length),
        true, `${width}: scrolling back does not replay entrances`);
      await page.locator('.cg-gift-grid').screenshot({ path: path.join(output, `gifts-${width}.png`) });
      const faq = page.locator('.cg-faq details').nth(1);
      await faq.locator('summary').click();
      await page.waitForFunction(() => { const question = document.querySelectorAll('.cg-faq details')[1]; return question.open && !question.dataset.expanded; });
      await faq.locator('summary').click();
      await page.waitForFunction(() => !document.querySelectorAll('.cg-faq details')[1].open);
      assert.deepEqual(errors, []);
      await page.close();
      console.log(`${engine} ${width}: readable scroll motion, smooth timing, single entrance and FAQ passed`);
    }
    const reduced = await context.newPage();
    await reduced.emulateMedia({ reducedMotion: 'reduce' });
    await setup(reduced);
    await reduced.locator('.cg-detail-grid').scrollIntoViewIfNeeded();
    assert.equal(await reduced.evaluate(() => window.cgScrollMotions.length), 0);
    assert.equal(await reduced.locator('.cg-detail-grid article').first().evaluate(element => getComputedStyle(element).opacity), '1');
    await reduced.close();
    console.log(`${engine}: reduced motion remains readable without entrance animation`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
