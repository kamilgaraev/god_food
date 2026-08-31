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

function renderHeroDocument() {
  return `<body class="home"><main>${hero.replace(/<\?php[\s\S]*?\?>/g, '#')}</main><style>${styles}</style></body>`;
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

  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(renderHeroDocument());
    await page.evaluate(() => {
      const video = document.querySelector('[data-home-hero-video]');
      let currentTime = 0;
      Object.defineProperty(video, 'currentTime', {
        configurable: true,
        get: () => currentTime,
        set: (value) => { currentTime = value; },
      });
      video.play = () => {
        video.dataset.playCalls = String(Number(video.dataset.playCalls || 0) + 1);
        return Promise.resolve();
      };
    });
    await page.addScriptTag({ content: script });

    const trigger = page.locator('.home-hero__video-trigger');
    const video = page.locator('[data-home-hero-video]');
    await trigger.click();
    assert.equal(await trigger.getAttribute('data-state'), 'playing',
      'Clicking the visual must expose its playing state');
    assert.equal(await video.getAttribute('data-play-calls'), '1',
      'Clicking the visual must play the video once');

    await trigger.click();
    assert.equal(await video.getAttribute('data-play-calls'), '1',
      'A click while playing must not restart the video');

    await video.evaluate((node) => {
      node.currentTime = 3;
      node.dispatchEvent(new Event('ended'));
    });
    assert.equal(await trigger.getAttribute('data-state'), 'idle',
      'The trigger must become ready again after playback');
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
  } finally {
    await browser.close();
  }

  console.log('Hero video plays once per click and keeps the statement on the left');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
