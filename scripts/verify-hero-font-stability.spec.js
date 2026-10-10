const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { webkit } = require('playwright');

const theme = path.resolve(__dirname, '../wp-content/themes/theobroma');
const css = fs.readFileSync(path.join(theme, 'style.css'), 'utf8') + '\n' + fs.readFileSync(path.join(theme, 'assets/css/home-redesign.css'), 'utf8');
const output = path.resolve(__dirname, '../output/corporate-media/font-stability');
const requests = [];
const markup = '<section class="home-hero"><p class="home-eyebrow">Абсолютно натуральный шоколад</p><div class="home-hero__trust"><div><strong id="gi">ГИ 35</strong><span>вместо 70</span></div><div><strong>4,9</strong><span>1 200 отзывов</span></div></div></section>';
const html = `<!doctype html><meta charset="utf-8"><style>${css}</style><body class="home">${markup}</body>`;

(async () => {
  fs.mkdirSync(output, { recursive: true });
  const server = http.createServer((req, res) => {
    requests.push(req.url);
    if (req.url.endsWith('cormorant-cyrillic-variable.woff2')) { res.writeHead(503); res.end(); return; }
    if (req.url.startsWith('/assets/fonts/')) {
      const file = path.join(theme, req.url);
      res.writeHead(200, { 'Content-Type': 'font/woff2', 'Cache-Control': 'public,max-age=3600' });
      res.end(fs.readFileSync(file));
      return;
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }); res.end(html);
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const url = `http://127.0.0.1:${server.address().port}/`;
  const browser = await webkit.launch({ headless: true });
  try {
    const snapshots = [];
    for (let cycle = 0; cycle < 3; cycle++) {
      const context = await browser.newContext({ viewport: { width: 390, height: 800 }, deviceScaleFactor: 2 });
      const page = await context.newPage();
      for (const state of ['cold', 'warm']) {
        await page.goto(url, { waitUntil: 'domcontentloaded' });
        await page.evaluate(() => document.fonts.ready);
        const font = await page.locator('#gi').evaluate(node => {
          const s = getComputedStyle(node);
          return { family: s.fontFamily, weight: s.fontWeight, synthesis: s.fontSynthesis, ready: document.fonts.check('400 44px "Cormorant Hero"', 'ГИ 35') };
        });
        assert.match(font.family, /^"?Cormorant Hero/);
        assert.equal(font.weight, '400');
        assert.equal(font.synthesis, 'none');
        assert.equal(font.ready, true);
        const pixels = await page.locator('#gi').screenshot({ path: path.join(output, `gi-${cycle}-${state}.png`) });
        snapshots.push(pixels);
      }
      await context.close();
    }
    snapshots.forEach(pixels => assert.deepEqual(pixels, snapshots[0], 'WebKit cold and warm loads have the same glyph rendering'));
    assert.ok(requests.some(url => url.endsWith('cormorant-hero-400.woff2')));
    assert.equal(requests.some(url => url.endsWith('cormorant-cyrillic-variable.woff2')), false, 'hero does not depend on a separately loaded Cyrillic variable face');
    console.log('WebKit: 3 cold and 3 warm loads render identical GI glyphs at weight 400; a failed legacy Cyrillic font cannot affect them.');
  } finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
