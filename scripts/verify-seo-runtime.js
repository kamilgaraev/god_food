const assert = require('node:assert/strict');

const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';

async function get(path) {
  const response = await fetch(new URL(path, `${baseUrl}/`), { redirect: 'manual' });
  const body = await response.text();
  assert.equal(response.status, 200, `${path} must return 200, got ${response.status}`);
  return body;
}

function meta(html, attribute, value) {
  const pattern = new RegExp(`<meta[^>]+${attribute}=["']${value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}["'][^>]+content=["']([^"']+)["']`, 'i');
  return html.match(pattern)?.[1] || '';
}

function canonical(html) {
  return html.match(/<link[^>]+rel=["']canonical["'][^>]+href=["']([^"']+)["']/i)?.[1] || '';
}

function schemas(html) {
  return [...html.matchAll(/<script[^>]+type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi)]
    .map((match) => JSON.parse(match[1]));
}

function findSchema(documents, type) {
  for (const document of documents) {
    if (document['@type'] === type) return document;
    const graph = Array.isArray(document['@graph']) ? document['@graph'] : [];
    const found = graph.find((node) => node['@type'] === type);
    if (found) return found;
  }
  return null;
}

(async () => {
  const robots = await get('/robots.txt');
  assert.match(robots, /User-agent:\s*\*/i);
  assert.match(robots, /Disallow:\s*\/wp-admin\//i);
  assert.match(robots, new RegExp(`Sitemap:\\s*${baseUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\/wp-sitemap\\.xml`, 'i'));

  const sitemap = await get('/wp-sitemap.xml');
  assert.match(sitemap, /wp-sitemap-posts-product-1\.xml/);
  assert.match(sitemap, /wp-sitemap-posts-post-1\.xml/);
  assert.match(sitemap, /wp-sitemap-posts-page-1\.xml/);
  assert.doesNotMatch(sitemap, /wp-sitemap-users-/i, 'Public user sitemap must be disabled.');

  const [products, posts, pages] = await Promise.all([
    get('/wp-sitemap-posts-product-1.xml'),
    get('/wp-sitemap-posts-post-1.xml'),
    get('/wp-sitemap-posts-page-1.xml'),
  ]);
  assert.match(products, /\/product\/theobroma-200-70\//);
  assert.match(posts, /\/chto-oznachayut-protsenty-na-plitke-shokolada\//);
  assert.match(pages, /\/catalog\//);
  assert.match(pages, /\/corporate-gifts\//);

  const home = await get('/');
  assert.ok(meta(home, 'name', 'description'));
  assert.equal(meta(home, 'property', 'og:type'), 'website');
  assert.equal(canonical(home), `${baseUrl}/`);
  assert.ok(findSchema(schemas(home), 'Organization'));
  assert.ok(findSchema(schemas(home), 'WebSite'));
  assert.doesNotMatch(home, /<meta[^>]+name=["']yandex-verification["']/i, 'Unconfigured Webmaster token must not render.');

  const productUrl = `${baseUrl}/product/theobroma-200-70/`;
  const productHtml = await get('/product/theobroma-200-70/');
  assert.ok(meta(productHtml, 'name', 'description'));
  assert.equal(meta(productHtml, 'property', 'og:type'), 'product');
  assert.equal(canonical(productHtml), productUrl);
  const product = findSchema(schemas(productHtml), 'Product');
  assert.ok(product, 'Product JSON-LD is required.');
  assert.equal(product.sku, 'theobroma-200-70');
  assert.equal(product.offers.priceCurrency, 'RUB');
  assert.equal(product.offers.availability, 'https://schema.org/InStock');
  assert.ok(Array.isArray(product.image) && product.image.length >= 1);

  const articlePath = '/chto-oznachayut-protsenty-na-plitke-shokolada/';
  const articleHtml = await get(articlePath);
  assert.ok(meta(articleHtml, 'name', 'description'));
  assert.equal(meta(articleHtml, 'property', 'og:type'), 'article');
  assert.equal(canonical(articleHtml), `${baseUrl}${articlePath}`);
  const article = findSchema(schemas(articleHtml), 'Article');
  assert.ok(article, 'Article JSON-LD is required.');
  assert.ok(article.datePublished);
  assert.ok(article.dateModified);
  assert.equal(article.publisher.name, 'Пища Богов');

  for (const path of ['/cart/', '/my-account/']) {
    const html = await get(path);
    assert.match(html, /<meta\s+name=["']robots["'][^>]+content=["'][^"']*noindex/i, `${path} must be noindex.`);
  }
  const emptyCheckout = await fetch(new URL('/checkout/', `${baseUrl}/`), { redirect: 'manual' });
  assert.ok([301, 302, 303].includes(emptyCheckout.status), 'Empty checkout must redirect.');
  assert.match(emptyCheckout.headers.get('location') || '', /\/cart\/?$/i, 'Empty checkout must redirect to cart.');

  console.log('Runtime SEO verified: robots, sitemaps, metadata, canonical URLs, Product/Article schema, commerce noindex/redirect.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
