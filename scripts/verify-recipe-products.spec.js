const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

const admin = read('wp-content/plugins/theobroma-admin-tools/theobroma-admin-tools.php');
const adminScript = read('wp-content/plugins/theobroma-admin-tools/assets/recipe-admin.js');
const adminStyles = read('wp-content/plugins/theobroma-admin-tools/assets/recipe-admin.css');
const recipe = read('wp-content/themes/theobroma/single-theobroma_recipe.php');
const storefrontStyles = read('wp-content/themes/theobroma/assets/css/home-redesign.css');

const recipeEditor = admin.slice(
  admin.indexOf('public static function render_recipe_box'),
  admin.indexOf('private static function render_repeater'),
);

assert.match(recipeEditor, /data-product-picker data-limit="3"/, 'Recipe editor must expose a three-product picker.');
assert.match(recipeEditor, /type="checkbox" name="theobroma_product_ids\[\]"/, 'Recipe products must be unique checkbox choices.');
assert.doesNotMatch(recipeEditor, /<select name="theobroma_product_ids\[\]"/, 'Recipe editor must not use duplicate-prone product slots.');
assert.match(admin, /array_unique\(array_filter\(array_map\('absint'/, 'Saved product IDs must be deduplicated.');
assert.match(admin, /\$product->get_status\(\) !== 'publish'/, 'Only published WooCommerce products may be attached.');
assert.match(admin, /count\(\$valid_product_ids\) === 3/, 'Server-side saving must stop at three valid products.');

assert.match(adminScript, /checkbox\.prop\('disabled', isFull && !isSelected\)/, 'The fourth product must be blocked in the editor.');
assert.match(adminScript, /\[data-product-search\]/, 'The product picker must remain searchable.');
assert.match(adminStyles, /\.theobroma-product-options \{[^}]*grid-template-columns:repeat\(3/s, 'Product choices must render as an admin card grid.');

assert.match(recipe, /array_slice\(array_values\(array_unique/, 'The storefront must cap and deduplicate legacy product metadata.');
assert.match(recipe, /recipe-product-grid--count-<\?php echo esc_attr/, 'The storefront must expose the selected product count for centering.');
assert.match(storefrontStyles, /\.recipe-product-grid\.home-product-grid \{\s*grid-template-columns: repeat\(3,/s, 'Recipe products must use a three-column desktop grid.');
assert.match(storefrontStyles, /\.recipe-product-grid--count-1\.home-product-grid/, 'A single selected product must be centered.');
assert.match(storefrontStyles, /\.recipe-product-grid--count-2\.home-product-grid/, 'Two selected products must be centered.');

console.log('Recipe product picker and storefront layout checks passed.');
