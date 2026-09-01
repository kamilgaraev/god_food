const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { webkit } = require('playwright');
const { PNG } = require('pngjs');

const root = path.resolve(__dirname, '..');
const themeRoot = path.join(root, 'wp-content/themes/theobroma');
const baseCss = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8');
const homeCss = fs.readFileSync(path.join(themeRoot, 'assets/css/home-redesign.css'), 'utf8');
const headerScript = fs.readFileSync(path.join(themeRoot, 'assets/js/site-header.js'), 'utf8');
const homepageScript = fs.readFileSync(path.join(themeRoot, 'assets/js/homepage.js'), 'utf8');
const homepage = fs.readFileSync(path.join(themeRoot, 'index.php'), 'utf8');
const heroVideoPath = path.join(themeRoot, 'assets/video/hero-chocolate.mp4');
const heroVideo = fs.existsSync(heroVideoPath) ? fs.readFileSync(heroVideoPath) : Buffer.alloc(0);
const heroPoster = fs.readFileSync(path.join(themeRoot, 'assets/images/hero-chocolate-poster.jpg'));
const heroMarkup = homepage.match(/<section class="home-hero"[\s\S]*?<\/section>/)[0]
  .replace("<?php echo esc_url(get_template_directory_uri() . '/assets/images/hero-chocolate-poster.jpg'); ?>", '/hero-chocolate-poster.jpg')
  .replace("<?php echo esc_url(get_template_directory_uri() . '/assets/video/hero-chocolate.mp4'); ?>", '/hero-chocolate.mp4')
  .replace(/<\?php[\s\S]*?\?>/g, '#');
const heroDocument = `<!doctype html><meta charset="utf-8"><style>${baseCss}\n${homeCss}</style><body class="home"><main>${heroMarkup}</main>`;

async function createMediaServer(video, poster) {
  const server = http.createServer((request, response) => {
    if (request.url === '/hero-chocolate-poster.jpg') {
      response.writeHead(200, { 'Content-Type': 'image/jpeg', 'Content-Length': poster.length });
      response.end(poster);
      return;
    }
    if (request.url !== '/hero-chocolate.mp4') {
      response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      response.end(heroDocument);
      return;
    }

    const range = /bytes=(\d+)-(\d*)/.exec(request.headers.range || '');
    const start = range ? Number(range[1]) : 0;
    const end = Math.min(range && range[2] ? Number(range[2]) : video.length - 1, video.length - 1);
    response.writeHead(range ? 206 : 200, {
      'Accept-Ranges': 'bytes',
      'Content-Type': 'video/mp4',
      'Content-Length': end - start + 1,
      ...(range ? { 'Content-Range': `bytes ${start}-${end}/${video.length}` } : {}),
    });
    response.end(video.subarray(start, end + 1));
  });
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  return {
    url: `http://127.0.0.1:${server.address().port}/`,
    close: () => new Promise((resolve) => server.close(resolve)),
  };
}

(async () => {
  assert(fs.existsSync(heroVideoPath), 'Safari must receive the opaque hero MP4');
  assert(heroVideo.length <= 4_000_000,
    `Safari hero MP4 must stay lightweight enough for smooth decoding (got ${heroVideo.length} bytes)`);
  assert.equal(heroVideo.subarray(4, 8).toString('ascii'), 'ftyp',
    'Safari hero video must use the MP4 container');
  assert(heroVideo.includes(Buffer.from('avc1')),
    'Safari hero video must expose an H.264/AVC track');

  const mediaServer = await createMediaServer(heroVideo, heroPoster);
  const browser = await webkit.launch({ headless: true });
  try {
    const heroPage = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await heroPage.goto(mediaServer.url);
    const safariMedia = await heroPage.locator('[data-home-hero-video]').evaluate((node) => new Promise((resolve, reject) => {
      const report = () => resolve({
        support: node.canPlayType('video/mp4; codecs="avc1.640028"'),
        width: node.videoWidth,
        height: node.videoHeight,
        duration: node.duration,
      });
      if (node.readyState >= 1) return report();
      node.addEventListener('loadedmetadata', report, { once: true });
      node.addEventListener('error', () => reject(new Error('WebKit failed to decode the opaque H.264 MP4')), { once: true });
    }));
    assert(safariMedia.support, 'WebKit must report H.264 MP4 playback support');
    assert(safariMedia.width >= 1000 && safariMedia.height >= 600
      && Math.abs(safariMedia.width / safariMedia.height - 16 / 9) < 0.01,
    `WebKit must decode the complete wide hero composition (got ${safariMedia.width}x${safariMedia.height})`);
    assert(safariMedia.duration > 6 && safariMedia.duration < 6.1,
      `WebKit must retain the complete hero duration (got ${safariMedia.duration})`);
    await heroPage.addScriptTag({ content: homepageScript });
    const heroTrigger = heroPage.locator('.home-hero__video-trigger');
    const video = heroPage.locator('[data-home-hero-video]');
    await heroTrigger.click();
    assert.equal(await heroTrigger.getAttribute('data-state'), 'playing',
      'WebKit must start the MP4 after one click');
    await video.evaluate((node) => new Promise((resolve) => {
      node.currentTime = 4.5;
      node.addEventListener('seeked', resolve, { once: true });
    }));
    const transparentPixels = await video.evaluate((node) => {
      const canvas = document.createElement('canvas');
      canvas.width = node.videoWidth;
      canvas.height = node.videoHeight;
      const context = canvas.getContext('2d', { willReadFrequently: true });
      context.drawImage(node, 0, 0);
      const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
      let transparent = 0;
      for (let index = 3; index < pixels.length; index += 4) {
        if (pixels[index] < 255) transparent += 1;
      }
      return transparent;
    });
    assert.equal(transparentPixels, 0, 'WebKit-decoded hero frames must contain no alpha');
    const webkitFrame = PNG.sync.read(await heroPage.screenshot());
    const pixel = (x, y) => Array.from(webkitFrame.data.subarray((y * webkitFrame.width + x) * 4, (y * webkitFrame.width + x) * 4 + 3));
    const videoRect = await video.boundingBox();
    const sampleX = Math.floor(videoRect.x + videoRect.width - 100);
    const sampleY = Math.floor(videoRect.y + 100);
    const videoBackground = pixel(sampleX, sampleY);
    const pageBackground = pixel(100, sampleY);
    const videoTopEdge = pixel(sampleX, Math.floor(videoRect.y));
    const backgroundDistance = videoBackground.reduce((total, channel, index) => total + Math.abs(channel - pageBackground[index]), 0);
    const topEdgeDistance = videoTopEdge.reduce((total, channel, index) => total + Math.abs(channel - pageBackground[index]), 0);
    assert(backgroundDistance <= 8,
      `WebKit must render the MP4 background seamlessly against #fbf7f1 (video rgb(${videoBackground}), page rgb(${pageBackground}))`);
    assert(topEdgeDistance <= 8,
      `WebKit must not paint a dark line along the MP4 surface (edge rgb(${videoTopEdge}), page rgb(${pageBackground}))`);
    await video.evaluate((node) => { node.currentTime = node.duration - 0.1; });
    await heroPage.waitForFunction(() => document.querySelector('.home-hero__video-trigger').dataset.state === 'idle');
    await heroTrigger.click();
    assert.equal(await heroTrigger.getAttribute('data-state'), 'playing',
      'WebKit must allow replay after the one-shot video ends');
    await heroPage.close();

    for (const width of [390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await page.setContent(`<!doctype html><style>${baseCss}\n${homeCss}</style>
        <header class="site-header">
          <a class="shipping"><img alt=""><span>Бесплатная доставка от 2500 рублей</span></a>
          <nav class="nav">
            <div class="nav-links nav-links-study"><a>Каталог</a><a>Рецепты</a><a>Где купить</a><a>Сотрудничество</a></div>
            <a class="brand">Theobroma</a>
            <div class="nav-links nav-links-transactional floating-actions"><a class="header-icon header-cart"></a><a class="header-icon header-account"></a></div>
            <button class="menu-toggle"><span></span><span></span><span></span></button>
          </nav>
        </header><main style="height:3000px"></main>`);
      await page.addScriptTag({ content: headerScript });
      await page.evaluate(() => scrollTo(0, 180));
      await page.waitForTimeout(100);

      const visible = await page.evaluate(() => {
        const box = (selector) => document.querySelector(selector).getBoundingClientRect();
        const shipping = box('.shipping');
        const nav = box('.nav');
        const selectors = innerWidth >= 1200
          ? ['.nav-links-study', '.brand', '.floating-actions']
          : ['.brand', '.floating-actions', '.menu-toggle'];
        return {
          shippingTop: shipping.top,
          shippingBottom: shipping.bottom,
          navTop: nav.top,
          allControlsPaintable: selectors.every((selector) => {
            const rect = box(selector);
            return rect.width > 0 && rect.height > 0 && rect.left >= 0 && rect.right <= innerWidth;
          }),
        };
      });

      assert(visible.shippingTop >= -1, `${width}px: Safari must keep the free-shipping banner visible while scrolling`);
      assert(Math.abs(visible.navTop - visible.shippingBottom) <= 1,
        `${width}px: Safari must keep the complete navigation attached below the shipping banner`);
      assert(visible.allControlsPaintable, `${width}px: Safari clipped part of the header controls`);
      await page.close();
    }
  } finally {
    await browser.close();
    await mediaServer.close();
  }

  console.log('Safari decodes the opaque one-shot hero MP4 and keeps the complete sticky header stable');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
