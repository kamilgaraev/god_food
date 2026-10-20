const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const http = require('node:http');
const { spawnSync } = require('node:child_process');
const { chromium, webkit } = require('playwright');

const root = path.resolve(__dirname, '..');
const output = process.env.CORPORATE_SECTIONS_OUTPUT || fs.mkdtempSync(path.join(os.tmpdir(), 'theobroma-corporate-sections-'));
fs.mkdirSync(output, { recursive: true });
const php = spawnSync('php', [path.join(__dirname, 'verify-corporate-sections.php'), output], { encoding: 'utf8' });
assert.equal(php.status, 0, php.stderr || php.stdout);
const mime = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'application/javascript', '.woff2': 'font/woff2', '.png': 'image/png', '.webp': 'image/webp', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml' };
const server = http.createServer((request, response) => {
  const pathname = new URL(request.url, 'http://localhost').pathname;
  const file = pathname === '/admin.html' || pathname === '/storefront.html'
    ? path.join(output, pathname.slice(1)) : path.resolve(root, '.' + pathname);
  if ((!file.startsWith(root + path.sep) && !file.startsWith(path.resolve(output) + path.sep)) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
    response.writeHead(404).end(); return;
  }
  response.writeHead(200, { 'Content-Type': mime[path.extname(file)] || 'application/octet-stream' });
  fs.createReadStream(file).pipe(response);
});

(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;
  try {
    for (const [engine, widths] of [[chromium, [320, 390, 768, 1440, 2560]], [webkit, [390]]]) {
      const browser = await engine.launch({ headless: true });
      try {
        for (const width of widths) {
          const page = await browser.newPage({ viewport: { width, height: 1000 }, deviceScaleFactor: width < 600 ? 3 : 1, reducedMotion: 'reduce' });
          const errors = [];
          page.on('pageerror', error => errors.push(error.message));
          await page.goto(`${base}/storefront.html`);
          await page.evaluate(async () => {
            document.querySelectorAll('img').forEach(image => { image.loading = 'eager'; });
            await Promise.all([...document.images].filter(image => image.getAttribute('src')).map(image => image.decode()));
            await document.fonts.ready;
          });
          assert.equal(await page.locator('.cg-portfolio-card').count(), 4);
          assert.equal(await page.locator('.cg-process-steps li').count(), 6);
          assert.match(await page.locator('.cg-factory').textContent(), /8\s*тонн шоколада в месяц/);
          const metrics = await page.evaluate(() => ({
            overflow: document.documentElement.scrollWidth > innerWidth + 1,
            clipped: [...document.querySelectorAll('.cg-process-steps li,.cg-factory,.cg-portfolio-card')].some(element => element.scrollWidth > element.clientWidth + 1),
            sharp: [...document.querySelectorAll('.cg-portfolio-photo img')].every(image => image.naturalWidth >= image.clientWidth * devicePixelRatio),
          }));
          assert.equal(metrics.overflow, false, `${width}: document fits`);
          assert.equal(metrics.clipped, false, `${width}: copy fits`);
          const headings = await page.evaluate(() => {
            const properties = ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textTransform'];
            const read = selector => {
              const heading = document.querySelector(selector);
              const style = getComputedStyle(heading);
              const accent = getComputedStyle(heading.querySelector('em'));
              return { typography: properties.map(key => style[key]), accent: [accent.color, accent.fontStyle, accent.fontWeight] };
            };
            return ['#cg-details-title', '#cg-portfolio-title', '#cg-process-title'].map(read);
          });
          assert.deepEqual(headings[1], headings[0], 'portfolio heading matches the existing heading and gold italic accent');
          assert.deepEqual(headings[2], headings[0], 'process heading matches the existing heading and gold italic accent');
          assert.equal(await page.locator('.cg-section-label').count(), 0, 'no extra overlines');
          assert.equal(await page.locator('.cg-factory').evaluate(element => getComputedStyle(element).backgroundColor === getComputedStyle(document.querySelector('.corporate-redesign')).backgroundColor), true, 'factory uses the light page surface');
          const decorations = await page.locator('.cg-edge-decoration').evaluateAll(elements => elements.map(element => {
            const style = getComputedStyle(element, '::after');
            return { visible: style.display !== 'none', right: element.getBoundingClientRect().right - parseFloat(style.right), edge: document.documentElement.clientWidth };
          }));
          assert.equal(decorations.length, 3);
          assert.ok(decorations.every(decoration => !decoration.visible || Math.abs(decoration.right - decoration.edge) < 1), 'decorations touch the right viewport edge');
          assert.equal(metrics.sharp, true, `${width}: photos have enough device pixels`);
          const track = page.locator('.cg-portfolio-track');
          if (width < 1000) {
            const next = page.getByRole('button', { name: 'Следующие примеры подарков', exact: true });
            await next.focus();
            await page.keyboard.press('Enter');
            await page.waitForFunction(() => document.querySelector('.cg-portfolio-track').scrollLeft > 100);
            const prev = page.getByRole('button', { name: 'Предыдущие примеры подарков', exact: true });
            await page.waitForFunction(() => !document.querySelector('.cg-portfolio [data-cg-direction="-1"]').disabled, null, { timeout: 1000 });
            await prev.click();
            await page.waitForFunction(() => document.querySelector('.cg-portfolio-track').scrollLeft < 2);
            await track.focus();
            await page.keyboard.press('ArrowRight');
            await page.waitForFunction(() => document.querySelector('.cg-portfolio-track').scrollLeft > 0);
          }
          await track.evaluate(element => { element.scrollTo({ left: 0, behavior: 'instant' }); });
          await page.waitForTimeout(300); // Native keyboard scrolling finishes before the visual snapshot.
          await page.locator('.cg-process').scrollIntoViewIfNeeded();
          await page.locator('.cg-portfolio').screenshot({ path: path.join(output, `${engine.name()}-${width}-portfolio.png`) });
          await page.locator('.cg-process').screenshot({ path: path.join(output, `${engine.name()}-${width}-process.png`) });
          await page.screenshot({ path: path.join(output, `${engine.name()}-${width}.png`), fullPage: true });
          assert.deepEqual(errors, []);
          await page.close();
        }
      } finally { await browser.close(); }
    }
    const browser = await chromium.launch({ headless: true });
    try {
      const page = await browser.newPage({ viewport: { width: 1100, height: 900 } });
      await page.addInitScript(() => {
        window.wp = { media(options) {
          window.mediaOptions = options;
          let select;
          return {
            on(event, callback) { if (event === 'select') select = callback; },
            open() { window.choosePhoto = () => select(); },
            state() { return { get() { return { first() { return { toJSON() { return { id: 20, alt: 'Новое фото', url: '/wp-content/themes/theobroma/assets/images/corporate/premium.webp' }; } }; } }; } }; },
          };
        } };
      });
      await page.goto(`${base}/admin.html`);
      const portfolio = page.locator('[data-cs-section="portfolio"]');
      const rows = portfolio.locator('[data-cs-list] > fieldset');
      const title = row => row.locator('input[name$="[title]"]');
      const firstTitle = await title(rows.first()).inputValue();
      const secondTitle = await title(rows.nth(1)).inputValue();
      await rows.nth(1).locator('[data-cs-move="up"]').focus();
      await page.keyboard.press('Enter');
      assert.equal(await title(rows.first()).inputValue(), secondTitle);
      assert.equal(await title(rows.nth(1)).inputValue(), firstTitle);
      await portfolio.locator('[data-cs-add]').click();
      await title(rows.last()).fill('Наш новый проект');
      await rows.last().locator('[data-cs-media]').click();
      assert.equal(await page.evaluate(() => window.mediaOptions.library.type), 'image');
      await page.evaluate(() => window.choosePhoto());
      assert.equal(await rows.last().locator('[data-cs-image]').inputValue(), '20');
      assert.equal(await rows.last().locator('[data-cs-asset]').inputValue(), '');
      await rows.last().locator('[data-cs-clear]').click();
      assert.equal(await rows.last().locator('[data-cs-image]').inputValue(), '0');
      assert.equal(await rows.last().locator('[data-cs-no-photo]').isVisible(), true);
      await rows.last().locator('[data-cs-remove]').click();
      assert.equal(await rows.count(), 4);
      const process = page.locator('[data-cs-section="process"]');
      await process.locator('summary').click();
      await process.locator('[name$="[factory_value]"]').fill('9');
      assert.equal(await process.locator('[name$="[factory_value]"]').inputValue(), '9');
      // Removing all cards must submit a deliberate empty list, not restore defaults.
      while (await rows.count()) await rows.first().locator('[data-cs-remove]').click();
      assert.equal(await portfolio.locator('[data-cs-add]').isEnabled(), true);
      const formData = await page.locator('form').evaluate(form => [...new FormData(form)]);
      assert.ok(formData.some(([key, value]) => key === 'theobroma_corporate_sections[portfolio][items]' && value === ''));
      assert.ok(!formData.some(([key]) => key.includes('[portfolio][items][')));
      assert.ok(formData.some(([key, value]) => key.endsWith('[factory_value]') && value === '9'));
      await page.setViewportSize({ width: 390, height: 900 });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, 'admin works on mobile');
      await page.screenshot({ path: path.join(output, 'admin-mobile.png'), fullPage: true });
    } finally { await browser.close(); }
    console.log(`Corporate sections: Chromium/WebKit responsive layout, keyboard carousel and admin editing passed. Screenshots: ${output}`);
  } finally { await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });
