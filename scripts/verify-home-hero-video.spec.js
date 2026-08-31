const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeRoot = path.resolve(__dirname, '../wp-content/themes/theobroma');
const homepage = fs.readFileSync(path.join(themeRoot, 'index.php'), 'utf8');
const script = fs.readFileSync(path.join(themeRoot, 'assets/js/homepage.js'), 'utf8');
const styles = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8')
  + fs.readFileSync(path.join(themeRoot, 'assets/css/home-redesign.css'), 'utf8');
const hero = homepage.match(/<section class="home-hero"[\s\S]*?<\/section>/)?.[0];
const poster = fs.readFileSync(path.join(themeRoot, 'assets/images/hero-chocolate-poster.webp'));
const videoAsset = fs.readFileSync(path.join(themeRoot, 'assets/video/hero-chocolate.webm'));
const webkitAnimation = fs.readFileSync(path.join(themeRoot, 'assets/images/hero-chocolate-animated.webp'));

function renderHeroDocument() {
  const markup = hero
    .replaceAll("<?php echo esc_url(get_template_directory_uri() . '/assets/images/hero-chocolate-poster.webp'); ?>", `data:image/webp;base64,${poster.toString('base64')}`)
    .replaceAll("<?php echo esc_url(get_template_directory_uri() . '/assets/video/hero-chocolate.webm'); ?>", `data:video/webm;base64,${videoAsset.toString('base64')}`)
    .replaceAll("<?php echo esc_url(get_template_directory_uri() . '/assets/images/hero-chocolate-animated.webp'); ?>", `data:image/webp;base64,${webkitAnimation.toString('base64')}`)
    .replace(/<\?php[\s\S]*?\?>/g, '#');
  return `<body class="home"><main>${markup}</main><style>${styles}</style></body>`;
}

(async () => {
  assert(hero, 'Homepage hero must exist');
  assert.match(hero, /<p class="home-eyebrow">Абсолютно натуральный<\/p>/,
    'Hero must keep the Absolutely Natural statement visible');

  const triggerMarkup = hero.match(/<button class="home-hero__video-trigger"[\s\S]*?<\/button>/)?.[0];
  assert(triggerMarkup, 'Hero must contain a button that starts the chocolate video');
  assert.match(triggerMarkup, /aria-label="Воспроизвести анимацию шоколада"/,
    'The video trigger must explain its action to assistive technology');
  assert.match(triggerMarkup, /<video[^>]*data-home-hero-video[^>]*muted[^>]*playsinline[^>]*preload="metadata"/,
    'Hero video must be muted, inline, and load metadata only');
  assert.doesNotMatch(triggerMarkup, /\b(?:autoplay|loop)\b/,
    'Hero video must play only once after a click');
  assert(triggerMarkup.includes('/assets/video/hero-chocolate.webm'),
    'Hero must use the transparent WebM animation');
  assert.match(triggerMarkup, /type="video\/webm"/,
    'Hero animation source must declare the WebM media type');
  assert(triggerMarkup.includes('/assets/images/hero-chocolate-animated.webp'),
    'Hero must include an alpha-safe animated WebP fallback for WebKit');

  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(renderHeroDocument());
    await page.locator('[data-home-hero-video]').evaluate((node) => new Promise((resolve, reject) => {
      if (node.readyState >= 1) return resolve();
      node.addEventListener('loadedmetadata', resolve, { once: true });
      node.addEventListener('error', () => reject(new Error('Hero WebM failed to decode')), { once: true });
    }));
    await page.addScriptTag({ content: script });

    const trigger = page.locator('.home-hero__video-trigger');
    const video = page.locator('[data-home-hero-video]');
    const mediaMetrics = await video.evaluate((node) => ({
      width: node.videoWidth,
      height: node.videoHeight,
      duration: node.duration,
    }));
    assert.deepEqual([mediaMetrics.width, mediaMetrics.height], [800, 800],
      'Hero WebM must decode at the intended square dimensions');
    assert(mediaMetrics.duration > 6 && mediaMetrics.duration < 6.1,
      `Hero WebM must retain the source duration (got ${mediaMetrics.duration})`);

    await trigger.click();
    assert.equal(await trigger.getAttribute('data-state'), 'playing',
      'Clicking the visual must expose its playing state');
    await video.evaluate((node) => new Promise((resolve) => {
      const seek = () => {
        node.removeEventListener('playing', seek);
        node.currentTime = 4.5;
        node.addEventListener('seeked', resolve, { once: true });
      };
      if (!node.paused) seek(); else node.addEventListener('playing', seek, { once: true });
    }));
    const alphaCoverage = await video.evaluate((node) => {
      const canvas = document.createElement('canvas');
      canvas.width = node.videoWidth;
      canvas.height = node.videoHeight;
      const context = canvas.getContext('2d', { willReadFrequently: true });
      context.clearRect(0, 0, canvas.width, canvas.height);
      context.drawImage(node, 0, 0);
      const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
      let transparent = 0;
      let opaque = 0;
      for (let index = 3; index < pixels.length; index += 4) {
        if (pixels[index] < 10) transparent += 1;
        if (pixels[index] > 245) opaque += 1;
      }
      const pixelCount = pixels.length / 4;
      return { transparent: transparent / pixelCount, opaque: opaque / pixelCount };
    });
    assert(alphaCoverage.transparent > 0.55 && alphaCoverage.opaque > 0.05,
      `Hero WebM must contain both transparent background and opaque chocolate pixels (got ${JSON.stringify(alphaCoverage)})`);

    const timeBeforeRepeatedClick = await video.evaluate((node) => node.currentTime);
    await trigger.click();
    const timeAfterRepeatedClick = await video.evaluate((node) => node.currentTime);
    assert(timeAfterRepeatedClick >= timeBeforeRepeatedClick - 0.1,
      'A click while playing must not restart the video');

    await video.evaluate((node) => node.pause());
    assert.equal(await trigger.getAttribute('data-state'), 'idle',
      'An interrupted video must make the trigger ready again');
    assert.equal(await video.evaluate((node) => node.currentTime), 0,
      'An interrupted video must return to its poster frame');

    await trigger.click();
    await video.evaluate((node) => { node.currentTime = node.duration - 0.1; });
    await page.waitForFunction(() => document.querySelector('.home-hero__video-trigger')?.dataset.state === 'idle', null, { timeout: 2000 });
    assert.equal(await video.evaluate((node) => node.currentTime), 0,
      'The ended video must return to its poster frame');

    const desktopLayout = await page.evaluate(() => {
      const label = document.querySelector('.home-eyebrow').getBoundingClientRect();
      const visual = document.querySelector('.home-hero__video-trigger').getBoundingClientRect();
      return { labelRight: label.right, visualLeft: visual.left, visualWidth: visual.width };
    });
    assert(desktopLayout.labelRight < desktopLayout.visualLeft,
      'At desktop width the statement must sit to the left of the video');
    assert(desktopLayout.visualWidth >= 420,
      `Desktop video must remain visually dominant (got ${desktopLayout.visualWidth}px)`);

    for (const width of [320, 390, 600, 601, 768, 1199, 1440, 1920]) {
      await page.setViewportSize({ width, height: 900 });
      const geometry = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        label: document.querySelector('.home-eyebrow').getBoundingClientRect().toJSON(),
        visual: document.querySelector('.home-hero__video-trigger').getBoundingClientRect().toJSON(),
      }));
      assert(geometry.overflow <= 1, `${width}px hero must not create horizontal overflow (got ${geometry.overflow}px)`);
      assert(geometry.label.left >= 0 && geometry.label.right <= width,
        `${width}px statement must remain inside the viewport`);
      assert(geometry.visual.left >= 0 && geometry.visual.right <= width,
        `${width}px video must remain inside the viewport`);
    }

    const webkitPage = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await webkitPage.setContent(renderHeroDocument());
    await webkitPage.evaluate(() => {
      Object.defineProperty(navigator, 'userAgent', { configurable: true, value: 'Mozilla/5.0 AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1' });
      window.__heroTimers = [];
      window.setTimeout = (callback, delay) => {
        window.__heroTimers.push({ callback, delay });
        return window.__heroTimers.length;
      };
    });
    await webkitPage.addScriptTag({ content: script });
    const webkitTrigger = webkitPage.locator('.home-hero__video-trigger');
    await webkitTrigger.click();
    assert.equal(await webkitTrigger.getAttribute('data-state'), 'playing',
      'WebKit fallback must expose its playing state');
    assert.equal(await webkitPage.locator('[data-home-hero-fallback]').evaluate((node) => node.src.startsWith('data:image/webp')), true,
      'WebKit fallback must switch to the transparent animated WebP');
    const fallbackDelay = await webkitPage.evaluate(() => window.__heroTimers[0]?.delay);
    assert(fallbackDelay >= 6042 && fallbackDelay <= 6200,
      `WebKit fallback must reset after one playback (got ${fallbackDelay}ms)`);
    await webkitPage.evaluate(() => window.__heroTimers[0].callback());
    assert.equal(await webkitTrigger.getAttribute('data-state'), 'idle',
      'WebKit fallback must become clickable again after one playback');
    await webkitPage.close();
  } finally {
    await browser.close();
  }

  console.log('Hero video plays once per click and keeps the statement on the left');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
