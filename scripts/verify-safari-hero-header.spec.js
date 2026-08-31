const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { webkit } = require('playwright');

const root = path.resolve(__dirname, '..');
const themeRoot = path.join(root, 'wp-content/themes/theobroma');
const baseCss = fs.readFileSync(path.join(themeRoot, 'style.css'), 'utf8');
const homeCss = fs.readFileSync(path.join(themeRoot, 'assets/css/home-redesign.css'), 'utf8');
const headerScript = fs.readFileSync(path.join(themeRoot, 'assets/js/site-header.js'), 'utf8');
const animation = fs.readFileSync(path.join(themeRoot, 'assets/images/hero-chocolate-animated-v2.webp'));

function animationFrames(buffer) {
  const frames = [];
  for (let offset = 12; offset + 8 <= buffer.length;) {
    const type = buffer.toString('ascii', offset, offset + 4);
    const size = buffer.readUInt32LE(offset + 4);
    if (type === 'ANMF') {
      const flags = buffer[offset + 8 + 15];
      frames.push({ dispose: flags & 1, noBlend: Boolean(flags & 2) });
    }
    offset += 8 + size + (size & 1);
  }
  return frames;
}

(async () => {
  const frames = animationFrames(animation);
  assert(frames.length > 100, 'Safari fallback must contain the complete animation');
  assert(frames.every((frame) => frame.noBlend),
    'Every transparent WebP frame must replace the full canvas instead of smearing over previous frames');

  const browser = await webkit.launch({ headless: true });
  try {
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
  }

  console.log('Safari hero animation and complete sticky header remain stable');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
