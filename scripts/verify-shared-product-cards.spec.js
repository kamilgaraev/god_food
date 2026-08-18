const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relativePath) => {
  const absolutePath = path.join(root, relativePath);
  return fs.existsSync(absolutePath) ? fs.readFileSync(absolutePath, 'utf8') : '';
};

const sharedCard = read('wp-content/themes/theobroma/template-parts/home/product-card.php');
const productLoop = read('wp-content/themes/theobroma/woocommerce/content-product.php');
const loopStart = read('wp-content/themes/theobroma/woocommerce/loop/loop-start.php');
const productDetail = read('wp-content/themes/theobroma/woocommerce/single-product.php');
const mediaArticle = read('wp-content/themes/theobroma/single.php');
const recipe = read('wp-content/themes/theobroma/single-theobroma_recipe.php');
const corporateGifts = read('wp-content/themes/theobroma/template-parts/pages/corporate-gifts.php');
const home = read('wp-content/themes/theobroma/index.php');
const styles = read('wp-content/themes/theobroma/assets/css/home-redesign.css');

const failures = [];
const expectContains = (source, needle, message) => {
  if (!source.includes(needle)) failures.push(message);
};

expectContains(sharedCard, "$args['wrapper_tag']", 'Shared card must support the list-item wrapper used by WooCommerce loops.');
expectContains(sharedCard, "$args['wrapper_classes']", 'Shared card must accept context-specific wrapper classes.');
expectContains(sharedCard, "$run_woocommerce_loop_hook('woocommerce_before_shop_loop_item')", 'WooCommerce cards must preserve the before-item extension point.');
expectContains(sharedCard, "$run_woocommerce_loop_hook('woocommerce_after_shop_loop_item')", 'WooCommerce cards must preserve the after-item extension point.');
expectContains(productLoop, "template-parts/home/product-card", 'WooCommerce product loops must render the homepage card template.');
expectContains(productLoop, "'woocommerce_loop_hooks' => true", 'WooCommerce product loops must enable classic loop hooks.');
expectContains(loopStart, 'home-product-grid', 'WooCommerce product loops must use the homepage card grid.');

for (const [name, source] of [
  ['homepage', home],
  ['product recommendations', productDetail],
  ['media article products', mediaArticle],
  ['recipe products', recipe],
  ['corporate gift products', corporateGifts],
]) {
  expectContains(source, "template-parts/home/product-card", `${name} must render the shared homepage product card.`);
}

expectContains(styles, '.catalog-page ul.products.home-product-grid', 'Catalog layout must opt into the shared responsive card grid.');
expectContains(styles, '.product-related-grid.home-product-grid', 'Product recommendations must use the shared card grid layout.');
expectContains(styles, '.media-article-products-grid.home-product-grid', 'Article products must use the shared card grid layout.');
expectContains(styles, '.recipe-product-grid.home-product-grid', 'Recipe products must use the shared card grid layout.');
expectContains(styles, '.corporate-gifts-showcase-grid.home-product-grid', 'Corporate gift products must use the shared card grid layout.');
expectContains(styles, 'display: flex !important;', 'The canonical catalog add-to-cart button must override the legacy hidden-button rule.');

if (recipe.includes("for ($card = 0; $card < 3; $card++)")) {
  failures.push('Recipe fallback must not render legacy placeholder product cards.');
}

if (failures.length) {
  console.error(failures.map((failure) => `- ${failure}`).join('\n'));
  process.exit(1);
}

console.log('Shared product card checks passed.');
