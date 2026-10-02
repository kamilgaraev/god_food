const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium, request } = require('playwright');
const plan = require('../data/releases/2026-10-02-client-revisions.json');
const base = process.env.SITE_URL || 'https://theobroma.one';
const image = base + '/wp-content/themes/theobroma/assets/images/social-preview-20261002.png';
const normalize = value => value.replace(/\s+/g, ' ').trim();
assert.equal(new Set(plan.seo.map(row => row.description)).size, plan.seo.length, 'Unique page descriptions');

(async () => {
  const api = await request.newContext();
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const parser = await browser.newPage();
  const output = path.resolve(__dirname, '../output/client-revisions');
  fs.mkdirSync(output, { recursive: true });
  try {
    for (const [old, target] of Object.entries(plan.redirects)) {
      const response = await api.get(base + old, { maxRedirects: 0 });
      assert.equal(response.status(), 301, old);
      assert.equal(response.headers().location, base + target, old);
    }
    const results = [];
    for (const row of plan.seo) {
      const sourcePath = new URL(row.url).pathname;
      const url = base + (plan.redirects[sourcePath] || sourcePath);
      const response = await api.get(url);
      assert.equal(response.status(), 200, url);
      const data = await parser.evaluate(html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const meta = name => doc.querySelector(`meta[name="${name}"],meta[property="${name}"]`)?.getAttribute('content');
        return { title: doc.title, titles: doc.querySelectorAll('title').length, description: meta('description'), h1: doc.querySelector('h1')?.textContent,
          canonical: doc.querySelector('link[rel="canonical"]')?.getAttribute('href'), og: meta('og:image'), twitter: meta('twitter:image'),
          width: meta('og:image:width'), height: meta('og:image:height'), mime: meta('og:image:type'), site: meta('og:site_name'),
          schemas: [...doc.querySelectorAll('script[type="application/ld+json"]')].map(s => JSON.parse(s.textContent)) };
      }, await response.text());
      assert.equal(data.title, row.title, `${url}: exact Title without duplicate brand suffix`);
      assert.equal(data.titles, 1);
      assert.equal(data.description, row.description, `${url}: Description`);
      assert.equal(normalize(data.h1), row.h1, `${url}: H1`);
      assert.equal(data.canonical, url, `${url}: canonical`);
      assert.equal(data.og, image, `${url}: shared OG cover`);
      assert.equal(data.twitter, image);
      assert.equal(data.width, '1200'); assert.equal(data.height, '630'); assert.equal(data.mime, 'image/png');
      assert.equal(data.site, 'Theobroma Пища богов');
      const ownSchema = data.schemas[0];
      assert.ok(ownSchema, `${url}: schema`);
      if (row.kind === 'product') {
        assert.equal(ownSchema['@type'], 'Product');
        assert.ok(ownSchema.image?.length, `${url}: real product photo in schema`);
        assert.ok(ownSchema.sku);
        assert.notEqual(ownSchema.image[0], image, `${url}: product photo is separate from social cover`);
        const price = plan.prices.find(p => p.id === row.id) || plan.unpriced.find(p => p.id === row.id);
        assert.equal(Number(ownSchema.offers.price), Number(price.price), `${url}: price`);
      }
      if (sourcePath.startsWith('/recipe/')) {
        assert.equal(ownSchema['@type'], 'Recipe');
        assert.equal(ownSchema.totalTime, 'PT5M');
        assert.ok(ownSchema.recipeIngredient.length >= 4);
        assert.ok(ownSchema.recipeInstructions.length >= 3);
        assert.ok(ownSchema.image[0].includes('recipe-'));
      }
      if (row.kind === 'term' || ['/catalog/', '/recipes/', '/media/'].includes(sourcePath)) {
        assert.equal(ownSchema['@type'], 'CollectionPage');
        assert.ok(ownSchema.mainEntity.itemListElement.length);
      }
      if (sourcePath === '/corporate-gifts/') {
        assert.equal(ownSchema['@type'], 'FAQPage');
        assert.ok(JSON.stringify(ownSchema).includes('нектара кокосовой пальмы'));
      }
      results.push({ row: row.row, url, title: data.title, h1: normalize(data.h1), schema: ownSchema['@type'] || '@graph' });
    }
    for (const agent of ['TelegramBot (like TwitterBot)', 'vkShare']) {
      const response = await api.get(image, { headers: { 'User-Agent': agent } });
      assert.equal(response.status(), 200);
      assert.match(response.headers()['content-type'], /image\/png/);
      const bytes = await response.body();
      assert.equal(bytes.readUInt32BE(16), 1200); assert.equal(bytes.readUInt32BE(20), 630);
      assert.deepEqual(bytes, fs.readFileSync(path.resolve(__dirname, '../wp-content/themes/theobroma/assets/images/social-preview-20261002.png')));
    }
    const index = await api.get(base + '/wp-sitemap.xml');
    const sitemapPaths = [...(await index.text()).matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
    let sitemap = '';
    for (const url of sitemapPaths) sitemap += await (await api.get(url)).text();
    assert.ok(sitemap.includes('/product/theobroma-30-strawberry/'));
    assert.ok(!sitemap.includes('theobroma-chia-100'));
    for (const route of ['/cart/', '/my-account/']) {
      const html = await (await api.get(base + route)).text();
      assert.match(html, /name=['"]robots['"][^>]*content=['"][^'"]*noindex/);
    }
    fs.writeFileSync(path.join(output, 'seo-verification.json'), JSON.stringify(results, null, 2));
    console.log(`Verified ${results.length} exact Titles, Descriptions and H1s, all shared previews, 26 Product offers, 3 Recipes, redirects and sitemap.`);

    for (const width of [390, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      for (const route of ['/catalog/', '/product-category/cacao/', '/buy/', '/cooperation/', '/media/', '/recipes/', '/product/theobroma-100-68-coriander/', '/product/theobroma-cacao-100/']) {
        await page.goto(base + route, { waitUntil: 'domcontentloaded' });
        await page.evaluate(() => document.fonts.ready);
        const cookie = page.getByRole('button', { name: 'Только необходимые' });
        if (await cookie.isVisible()) await cookie.click();
        await page.locator('h1:visible').first().waitFor({ state: 'visible' });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width} ${route}: overflow`);
        if (route === '/product-category/cacao/') {
          const thumbnails = page.locator('ul.products li.product img');
          assert.equal(await thumbnails.count(), 3);
          for (const thumbnail of await thumbnails.all()) {
            await thumbnail.evaluate(img => img.decode());
            assert.ok(!(await thumbnail.getAttribute('src')).includes('placeholder'), 'Cacao catalog photo must exist');
          }
        }
        if (route.startsWith('/product/')) {
          assert.equal(await page.locator('h1:visible').first().evaluate(node => getComputedStyle(node).textTransform), 'none');
          await page.locator('[data-product-main-image]:visible').first().evaluate(img => img.decode());
        }
        await page.evaluate(() => Promise.all(document.getAnimations().filter(animation =>
          animation.effect.getComputedTiming().iterations !== Infinity).map(animation => animation.finished.catch(() => {}))));
        await page.screenshot({ path: path.join(output, `${width}-${route.split('/').filter(Boolean).join('-')}.png`) });
      }
      assert.deepEqual(errors, []);
      await page.close();
      console.log(`${width}: page headings, product images and layout passed`);
    }
    const cart = await browser.newPage({ viewport: { width: 390, height: 1000 } });
    await cart.goto(base + '/catalog/', { waitUntil: 'domcontentloaded' });
    const cookie = cart.getByRole('button', { name: 'Только необходимые' });
    if (await cookie.isVisible()) await cookie.click();
    await cart.goto(base + '/product/theobroma-100-68-coriander/', { waitUntil: 'domcontentloaded' });
    await cart.locator('#commerce-modal .single_add_to_cart_button').click();
    const price = cart.locator('#commerce-modal[data-commerce-type="cart"] .commerce-cart-price').first();
    await price.waitFor();
    assert.match(await price.innerText(), /780\s*(?:р|₽)/, 'New price must reach the actual shopping cart');
    console.log('Cart: coriander 100 g costs 780 RUB');
    await cart.close();
  } finally { await parser.close(); await browser.close(); await api.dispose(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
