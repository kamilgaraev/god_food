const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { chromium, webkit, request } = require('playwright');

const base = process.env.CORPORATE_BASE_URL || 'https://theobroma.one';
const source = process.env.CORPORATE_MEDIA_SOURCE === '1';
const engine = process.env.BROWSER_ENGINE || 'chromium';
const theme = path.resolve(__dirname, '../wp-content/themes/theobroma');
const output = path.resolve(__dirname, '../output/corporate-media', source ? 'source' : 'live', engine);

async function serveSource(page, html) {
  if (!source) return;
  await page.route(`${base}/corporate-gifts/`, route => route.fulfill({ contentType: 'text/html; charset=utf-8', body: html }));
  await page.route('**/wp-content/themes/theobroma/**', route => {
    const url = new URL(route.request().url());
    const relative = decodeURIComponent(url.pathname.split('/wp-content/themes/theobroma/')[1]);
    const file = path.resolve(theme, relative);
    if (!file.startsWith(theme + path.sep) || !fs.existsSync(file)) return route.continue();
    const types = { '.css': 'text/css', '.js': 'application/javascript', '.mp4': 'video/mp4', '.webp': 'image/webp', '.jpg': 'image/jpeg', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2' };
    const body = fs.readFileSync(file);
    const range = /bytes=(\d+)-(\d*)/.exec(route.request().headers().range || '');
    if (path.extname(file) === '.mp4' && range) {
      const start = Number(range[1]), end = Math.min(range[2] ? Number(range[2]) : body.length - 1, body.length - 1);
      return route.fulfill({ status: 206, contentType: 'video/mp4', headers: { 'accept-ranges': 'bytes', 'content-range': `bytes ${start}-${end}/${body.length}` }, body: body.subarray(start, end + 1) });
    }
    return route.fulfill({ contentType: types[path.extname(file)] || 'application/octet-stream', body });
  });
}

(async () => {
  fs.mkdirSync(output, { recursive: true });
  const api = await request.newContext();
  let html = '';
  let mediaServer;
  if (source) {
    const video = fs.readFileSync(path.join(theme, 'assets/videos/corporate-hero.mp4'));
    mediaServer = http.createServer((req, res) => {
      if (req.url !== '/corporate-hero.mp4') { res.writeHead(404); res.end('Not found'); return; }
      const range = /bytes=(\d+)-(\d*)/.exec(req.headers.range || '');
      const start = range ? Number(range[1]) : 0;
      const end = Math.min(range?.[2] ? Number(range[2]) : video.length - 1, video.length - 1);
      res.writeHead(range ? 206 : 200, { 'Content-Type': 'video/mp4', 'Accept-Ranges': 'bytes', 'Content-Length': end - start + 1, ...(range ? { 'Content-Range': `bytes ${start}-${end}/${video.length}` } : {}) });
      res.end(video.subarray(start, end + 1));
    });
    await new Promise(resolve => mediaServer.listen(0, '127.0.0.1', resolve));
    const response = await api.get(`${base}/corporate-gifts/`);
    assert.equal(response.status(), 200);
    html = (await response.text()).replace(/<main\b[^>]*class="[^"]*corporate-redesign[^"]*"[\s\S]*?<\/main>/,
      fs.readFileSync(path.resolve(__dirname, '../output/corporate-media/candidate-main.html'), 'utf8').replaceAll('http://theobroma.one', base));
    if (engine === 'webkit') html = html.replaceAll(`${base}/wp-content/themes/theobroma/assets/videos/corporate-hero.mp4`, `http://127.0.0.1:${mediaServer.address().port}/corporate-hero.mp4`);
    assert.match(html, /data-cg-video/);
  }
  const browser = await (engine === 'webkit' ? webkit.launch({ headless: true }) : chromium.launch({ channel: 'chrome', headless: true }));
  try {
    for (const width of engine === 'webkit' ? [390] : [390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'no-preference' });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.bringToFront();
      await serveSource(page, html);
      let release;
      const gate = new Promise(resolve => { release = resolve; });
      await page.route(/gallery-(chocolate|packaging)-original(?:-\d+)?\.jpg(?:\?.*)?$/, async route => {
        await gate;
        const name = route.request().url().includes('gallery-chocolate-') ? 'gallery-chocolate-original.jpg' : 'gallery-packaging-original.jpg';
        await route.fulfill({ contentType: 'image/jpeg', body: fs.readFileSync(path.join(theme, 'assets/images/corporate', name)) });
      });
      await page.goto(`${base}/corporate-gifts/`, { waitUntil: 'domcontentloaded' });
      try {
        await page.waitForFunction(() => document.querySelector('[data-cg-video]')?.videoWidth === 1920 && !document.querySelector('[data-cg-video]').paused, null, { timeout: 15000 });
      } catch (error) {
        console.error('Video diagnostic:', await page.locator('[data-cg-video]').evaluate(v => ({ src: v.src, state: v.readyState, paused: v.paused, width: v.videoWidth, error: v.error?.message, rect: v.getBoundingClientRect().toJSON() })), errors);
        throw error;
      }
      const cookie = page.getByRole('button', { name: 'Только необходимые' });
      if (await cookie.isVisible()) await cookie.click();
      const video = page.locator('[data-cg-video]');
      assert.equal(await video.evaluate(v => v.muted && v.loop && v.playsInline), true);
      const mediaBounds = await page.evaluate(() => {
        const hero = document.querySelector('.cg-hero').getBoundingClientRect();
        const poster = document.querySelector('.cg-hero-image:not(video)').getBoundingClientRect();
        const video = document.querySelector('[data-cg-video]').getBoundingClientRect();
        return { overflow: document.documentElement.scrollWidth > innerWidth, same: poster.x === video.x && poster.y === video.y && poster.width === video.width && poster.height === video.height, fullWidth: Math.abs(hero.width - video.width) < 1, overlay: getComputedStyle(document.querySelector('.cg-hero'), '::after').backgroundImage };
      });
      assert.equal(mediaBounds.overflow, false, `${width}: no horizontal overflow`);
      assert.equal(mediaBounds.same, true, `${width}: video retains the poster crop`);
      assert.equal(mediaBounds.fullWidth, true);
      assert.match(mediaBounds.overlay, /linear-gradient/);
      await page.getByRole('button', { name: 'Приостановить видео' }).click();
      assert.equal(await video.evaluate(v => v.paused), true);
      await page.locator('.cg-hero').screenshot({ path: path.join(output, `hero-${width}.png`) });

      await page.locator('.cg-gallery').scrollIntoViewIfNeeded();
      const photos = page.locator('[data-cg-progressive]');
      assert.equal(await photos.count(), 2);
      await photos.evaluateAll(nodes => nodes.forEach(node => { node.querySelector('img').loading = 'eager'; }));
      await photos.evaluateAll(nodes => Promise.all(nodes.map(node => {
        const preview = new Image();
        preview.src = getComputedStyle(node).backgroundImage.slice(5, -2);
        return preview.decode();
      })));
      const before = await photos.evaluateAll(nodes => nodes.map(node => ({ width: node.getBoundingClientRect().width, height: node.getBoundingClientRect().height, opacity: getComputedStyle(node.querySelector('img')).opacity })));
      assert.ok(before.every(photo => photo.width > 100 && photo.height > 100 && Number(photo.opacity) === 0), 'light previews appear before originals');
      if (width !== 768) await page.locator('.cg-gallery').screenshot({ path: path.join(output, `gallery-preview-${width}.png`) });
      release();
      await page.waitForFunction(() => [...document.querySelectorAll('[data-cg-progressive]')].every(node => node.classList.contains('is-ready') && Number(getComputedStyle(node.querySelector('img')).opacity) === 1));
      const after = await photos.evaluateAll(nodes => nodes.map(node => ({ width: node.getBoundingClientRect().width, height: node.getBoundingClientRect().height, naturalWidth: node.querySelector('img').naturalWidth, naturalHeight: node.querySelector('img').naturalHeight })));
      assert.deepEqual(after.map(p => [p.naturalWidth, p.naturalHeight]), [[2731, 4096], [2723, 4096]], 'full original resolution is restored');
      assert.deepEqual(after.map(p => [p.width, p.height]), before.map(p => [p.width, p.height]), 'upgrading images causes no layout shift');
      if (width !== 768) await page.locator('.cg-gallery').screenshot({ path: path.join(output, `gallery-original-${width}.png`) });
      if (width < 600) {
        await page.getByRole('button', { name: 'Следующие фотографии' }).click();
        await page.waitForFunction(() => document.querySelector('.cg-gallery-track').scrollLeft > 100);
      }
      await page.locator('.cg-hero').scrollIntoViewIfNeeded();
      assert.equal(await video.evaluate(v => v.paused), true, 'manual pause survives scrolling');
      assert.deepEqual(errors, []);
      await page.close();
      console.log(`${engine} ${width}: autoplay, pause, crop, progressive images and original resolution passed`);
    }

    const reduced = await browser.newPage({ viewport: { width: 390, height: 1000 }, reducedMotion: 'reduce' });
    await reduced.bringToFront();
    await serveSource(reduced, html);
    await reduced.goto(`${base}/corporate-gifts/`, { waitUntil: 'domcontentloaded' });
    await reduced.waitForFunction(() => document.querySelector('.cg-media-ready'));
    assert.equal(await reduced.locator('[data-cg-video]').getAttribute('src'), null, 'reduced motion does not download the video');
    await reduced.getByRole('button', { name: 'Воспроизвести видео' }).click();
    try {
      await reduced.waitForFunction(() => !document.querySelector('[data-cg-video]').paused && document.querySelector('[data-cg-video]').videoWidth === 1920, null, { timeout: 15000 });
    } catch (error) {
      console.error('Reduced motion diagnostic:', await reduced.locator('[data-cg-video]').evaluate(v => ({ hidden: document.hidden, src: v.src, paused: v.paused, width: v.videoWidth, error: v.error?.message, button: document.querySelector('[data-cg-video-toggle]').outerHTML })));
      throw error;
    }
    await reduced.close();

    const failed = await browser.newPage({ viewport: { width: 390, height: 1000 } });
    await failed.bringToFront();
    if (source) await serveSource(failed, html.replaceAll('corporate-hero.mp4', 'missing-corporate-hero.mp4'));
    else await failed.route(`${base}/corporate-gifts/`, async route => {
      const response = await route.fetch();
      await route.fulfill({ response, body: (await response.text()).replaceAll('corporate-hero.mp4', 'missing-corporate-hero.mp4') });
    });
    await failed.goto(`${base}/corporate-gifts/`, { waitUntil: 'domcontentloaded' });
    await failed.waitForFunction(() => document.querySelector('[data-cg-video]').error && document.querySelector('.cg-hero-image:not(video)').naturalWidth === 1920);
    assert.equal(await failed.locator('[data-cg-video]').evaluate(v => getComputedStyle(v).opacity), '0', 'failed video leaves the poster visible');
    await failed.close();
    console.log(`${engine}: reduced motion, explicit playback and video error fallback passed`);
  } finally {
    await browser.close();
    await api.dispose();
    if (mediaServer) await new Promise(resolve => mediaServer.close(resolve));
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
