const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

const publicSurfaces = {
  header: read('wp-content/themes/theobroma/header.php'),
  footer: read('wp-content/themes/theobroma/footer.php'),
  home: read('wp-content/themes/theobroma/index.php'),
  buy: read('wp-content/themes/theobroma/template-parts/pages/buy.php'),
  product: read('wp-content/themes/theobroma/woocommerce/single-product.php'),
};

for (const [surface, source] of Object.entries(publicSurfaces)) {
  assert.doesNotMatch(source, /Маркетплейсы/iu, `${surface} must not advertise marketplaces`);
  assert.doesNotMatch(source, /(?:wildberries\.ru|ozon\.ru)/iu, `${surface} must not link to Wildberries or Ozon`);
}

assert.doesNotMatch(publicSurfaces.buy, /id="bulletcities2"/u, 'where-to-buy page must not render the marketplace panel');
assert.match(publicSurfaces.buy, /id="buy-tab-1"[^>]*aria-selected="true"/u, 'boutiques must remain the default where-to-buy tab');
assert.match(publicSurfaces.buy, /id="bulletcities3"/u, 'the all-Russia partner tab must remain available');

const functions = read('wp-content/themes/theobroma/functions.php');
assert.match(
  functions,
  /'marketplace'\s*=>\s*theobroma_page_url\('Где купить'\)/u,
  'legacy marketplace URL must permanently redirect to the where-to-buy page',
);

console.log('Marketplace removal verified across public storefront surfaces');
