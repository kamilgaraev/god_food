const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');

const themeRoot = path.resolve(__dirname, '../wp-content/themes/theobroma');
const homepage = fs.readFileSync(path.join(themeRoot, 'index.php'), 'utf8');
const script = fs.readFileSync(path.join(themeRoot, 'assets/js/homepage.js'), 'utf8');
const styles = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8')
  + fs.readFileSync(path.join(themeRoot, 'assets/css/home-redesign.css'), 'utf8');
const hero = homepage.match(/<section class="home-hero"[\s\S]*?<\/section>/)?.[0];
const posterPath = path.join(themeRoot, 'assets/images/hero-chocolate-poster.jpg');
const videoPath = path.join(themeRoot, 'assets/video/hero-chocolate.mp4');
const poster = fs.existsSync(posterPath) ? fs.readFileSync(posterPath) : Buffer.alloc(0);
const videoAsset = fs.existsSync(videoPath) ? fs.readFileSync(videoPath) : Buffer.alloc(0);

function renderHeroDocument() {
  const markup = hero
    .replaceAll("<?php echo esc_url(get_template_directory_uri() . '/assets/images/hero-chocolate-poster.jpg'); ?>", `data:image/jpeg;base64,${poster.toString('base64')}`)
    .replaceAll("<?php echo esc_url(get_template_directory_uri() . '/assets/video/hero-chocolate.mp4'); ?>", `data:video/mp4;base64,${videoAsset.toString('base64')}`)
    .replace(/<\?php[\s\S]*?\?>/g, '#');
  return `<body class="home"><main>${markup}</main><style>${styles}</style></body>`;
}

function visibleChocolateGap(png) {
  const background = Array.from(png.data.subarray(0, 3));
  let lowestChocolatePixel = -1;
  for (let y = 0; y < png.height; y += 1) {
    for (let x = Math.floor(png.width * 0.5); x < png.width; x += 1) {
      const offset = (y * png.width + x) * 4;
      const distance = Math.abs(png.data[offset] - background[0])
        + Math.abs(png.data[offset + 1] - background[1])
        + Math.abs(png.data[offset + 2] - background[2]);
      if (distance > 90 && png.data[offset] < 170) lowestChocolatePixel = y;
    }
  }
  return png.height - 1 - lowestChocolatePixel;
}

(async () => {
  assert(hero, 'Homepage hero must exist');
  assert.match(hero, /<p class="home-eyebrow">Абсолютно натуральный шоколад<\/p>/,
    'Hero must identify the product as absolutely natural chocolate');

  const triggerMarkup = hero.match(/<button class="home-hero__video-trigger"[\s\S]*?<\/button>/)?.[0];
  assert(triggerMarkup, 'Hero must contain a button that starts the chocolate video');
  assert.match(triggerMarkup, /aria-label="Воспроизвести анимацию шоколада"/,
    'The video trigger must explain its action to assistive technology');
  assert.match(triggerMarkup, /<video[^>]*data-home-hero-video[^>]*muted[^>]*playsinline[^>]*preload="metadata"/,
    'Hero video must be muted, inline, and load metadata only');
  assert.doesNotMatch(triggerMarkup, /\b(?:autoplay|loop)\b/,
    'Hero video must play only once after a click');
  assert(fs.existsSync(videoPath), 'Hero must ship the opaque MP4 animation');
  assert(fs.existsSync(posterPath), 'Hero must ship a static JPEG poster without alpha');
  assert(triggerMarkup.includes('/assets/video/hero-chocolate.mp4'),
    'Hero must use the opaque MP4 animation');
  assert.match(triggerMarkup, /type="video\/mp4"/,
    'Hero animation source must declare the MP4 media type');
  assert(triggerMarkup.includes('/assets/images/hero-chocolate-poster.jpg'),
    'Hero must use the opaque JPEG poster');
  assert.doesNotMatch(triggerMarkup, /data-home-hero-fallback|\.webm|animated-v3\.webp/,
    'Hero must not retain an alpha video or WebP animation fallback');
  assert.equal(videoAsset.subarray(4, 8).toString('ascii'), 'ftyp',
    'Hero video must use an ISO MP4 container');
  assert(videoAsset.includes(Buffer.from('avc1')),
    'Hero video must contain an H.264/AVC track for hardware decoding');

  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(renderHeroDocument());
    await page.locator('[data-home-hero-video]').evaluate((node) => new Promise((resolve, reject) => {
      if (node.readyState >= 1) return resolve();
      node.addEventListener('loadedmetadata', resolve, { once: true });
      node.addEventListener('error', () => reject(new Error('Hero MP4 failed to decode')), { once: true });
    }));
    await page.addScriptTag({ content: script });

    const trigger = page.locator('.home-hero__video-trigger');
    const video = page.locator('[data-home-hero-video]');
    const mediaMetrics = await video.evaluate((node) => ({
      width: node.videoWidth,
      height: node.videoHeight,
      duration: node.duration,
    }));
    assert.deepEqual([mediaMetrics.width, mediaMetrics.height], [1280, 720],
      'Hero MP4 must retain a wide canvas so falling pieces are not cropped');
    assert(mediaMetrics.duration > 6 && mediaMetrics.duration < 6.1,
      `Hero MP4 must retain the source duration (got ${mediaMetrics.duration})`);

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
    const pixelCoverage = await video.evaluate((node) => {
      const canvas = document.createElement('canvas');
      canvas.width = node.videoWidth;
      canvas.height = node.videoHeight;
      const context = canvas.getContext('2d', { willReadFrequently: true });
      context.clearRect(0, 0, canvas.width, canvas.height);
      context.drawImage(node, 0, 0);
      const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
      let transparent = 0;
      let darkSidePixels = 0;
      for (let index = 3; index < pixels.length; index += 4) {
        if (pixels[index] < 255) transparent += 1;
        const x = ((index - 3) / 4) % canvas.width;
        const rgbOffset = index - 3;
        const darkness = 251 - pixels[rgbOffset] + 247 - pixels[rgbOffset + 1] + 241 - pixels[rgbOffset + 2];
        if (darkness > 90 && (x < 4 || x >= canvas.width - 4)) darkSidePixels += 1;
      }
      const pixelCount = pixels.length / 4;
      return {
        transparent: transparent / pixelCount,
        darkSidePixels,
        corner: Array.from(pixels.subarray(0, 3)),
      };
    });
    assert.equal(pixelCoverage.transparent, 0,
      'Hero MP4 decoded frame must be fully opaque');
    assert(pixelCoverage.corner.every((channel, index) => Math.abs(channel - [251, 247, 241][index]) <= 4),
      `Hero MP4 background must match #fbf7f1 (got rgb(${pixelCoverage.corner.join(', ')}))`);
    assert.equal(pixelCoverage.darkSidePixels, 0,
      'Falling chocolate pieces must keep background breathing room at both side edges');

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
      const copyNode = document.querySelector('.home-hero__copy');
      const copy = copyNode.getBoundingClientRect();
      const trigger = document.querySelector('.home-hero__video-trigger');
      trigger.dataset.state = 'playing';
      const visual = trigger.getBoundingClientRect();
      const media = document.querySelector('[data-home-hero-video]').getBoundingClientRect();
      const mediaStyle = getComputedStyle(document.querySelector('[data-home-hero-video]'));
      const hero = document.querySelector('.home-hero').getBoundingClientRect();
      return {
        labelRight: label.right,
        copyRight: copy.right,
        visualLeft: visual.left,
        visualWidth: visual.width,
        mediaLeft: media.left,
        mediaBottomGap: hero.bottom - media.bottom,
        mediaFilter: mediaStyle.filter,
        copyZIndex: Number(getComputedStyle(copyNode).zIndex),
        playingZIndex: Number(getComputedStyle(trigger).zIndex),
      };
    });
    assert(desktopLayout.labelRight < desktopLayout.visualLeft,
      'At desktop width the statement must sit to the left of the video');
    assert(desktopLayout.visualWidth >= 420,
      `Desktop video must remain visually dominant (got ${desktopLayout.visualWidth}px)`);
    assert(desktopLayout.mediaLeft < desktopLayout.visualLeft - 100 && desktopLayout.mediaLeft < desktopLayout.copyRight,
      'The wide video layer must extend over the left hero column instead of clipping at the trigger boundary');
    assert(Math.abs(desktopLayout.mediaBottomGap) <= 32,
      `The video composition must sit against the benefit strip (gap ${desktopLayout.mediaBottomGap}px)`);
    assert.equal(desktopLayout.mediaFilter, 'none',
      'Opaque video must not cast a shadow around its full rectangular canvas');
    assert(desktopLayout.playingZIndex < desktopLayout.copyZIndex,
      'While playing, the video must remain behind all hero copy');

    await trigger.evaluate((node) => { node.dataset.state = 'idle'; });
    const desktopHeroPng = PNG.sync.read(await page.locator('.home-hero').screenshot());
    const desktopChocolateGap = visibleChocolateGap(desktopHeroPng);
    assert(desktopChocolateGap >= 0 && desktopChocolateGap <= 2,
      `Visible chocolate must sit intact against the benefit strip (gap ${desktopChocolateGap}px)`);

    const responsiveChocolateGaps = [];
    for (const width of [320, 390, 600, 601, 768, 1199, 1200, 1250, 1300, 1440, 1920]) {
      await page.setViewportSize({ width, height: 900 });
      const geometry = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        label: document.querySelector('.home-eyebrow').getBoundingClientRect().toJSON(),
        visual: document.querySelector('.home-hero__video-trigger').getBoundingClientRect().toJSON(),
        visualDisplay: getComputedStyle(document.querySelector('.home-hero__video-trigger')).display,
      }));
      assert(geometry.overflow <= 1, `${width}px hero must not create horizontal overflow (got ${geometry.overflow}px)`);
      assert(geometry.label.left >= 0 && geometry.label.right <= width,
        `${width}px statement must remain inside the viewport`);
      assert(geometry.visual.left >= 0 && geometry.visual.right <= width,
        `${width}px video must remain inside the viewport`);
      if (width <= 600) {
        assert.equal(geometry.visualDisplay, 'none', `${width}px hero video must be removed from the compact mobile layout`);
      } else {
        const responsiveHeroPng = PNG.sync.read(await page.locator('.home-hero').screenshot());
        const responsiveChocolateGap = visibleChocolateGap(responsiveHeroPng);
        responsiveChocolateGaps.push({ width, gap: responsiveChocolateGap });
      }
    }
    const invalidChocolateGaps = responsiveChocolateGaps.filter(({ gap }) => gap < 0 || gap > 2);
    assert.deepEqual(invalidChocolateGaps, [],
      `Chocolate must remain intact and visually attached to the benefit strip: ${JSON.stringify(responsiveChocolateGaps)}`);

    await page.setViewportSize({ width: 390, height: 844 });
    await video.evaluate((node) => new Promise((resolve) => {
      node.currentTime = 4.5;
      node.addEventListener('seeked', resolve, { once: true });
    }));
    const mobileHeroPng = PNG.sync.read(await page.locator('.home-hero').screenshot());
    const background = Array.from(mobileHeroPng.data.subarray(0, 3));
    let paintedEdgePixels = 0;
    for (let y = 2; y < mobileHeroPng.height - 3; y += 1) {
      for (const x of [0, 1, mobileHeroPng.width - 2, mobileHeroPng.width - 1]) {
        const offset = (y * mobileHeroPng.width + x) * 4;
        const distance = Math.abs(mobileHeroPng.data[offset] - background[0])
          + Math.abs(mobileHeroPng.data[offset + 1] - background[1])
          + Math.abs(mobileHeroPng.data[offset + 2] - background[2]);
        if (distance > 24) paintedEdgePixels += 1;
      }
    }
    assert(paintedEdgePixels <= 4,
      `Mobile falling pieces must remain visibly inside the hero edges (got ${paintedEdgePixels} painted edge pixels)`);

  } finally {
    await browser.close();
  }

  console.log('Hero video plays once per click and keeps the statement on the left');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
