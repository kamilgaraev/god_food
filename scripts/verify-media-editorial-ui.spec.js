const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const assertIncludes = (source, needle, message) => {
  if (!source.includes(needle)) throw new Error(message);
};

const listing = read('wp-content/themes/theobroma/template-parts/pages/media.php');
const article = read('wp-content/themes/theobroma/single.php');
const styles = read('wp-content/themes/theobroma/style.css');
const homeStyles = read('wp-content/themes/theobroma/assets/css/home-redesign.css');
const configure = read('scripts/configure-wordpress.php');

for (const [needle, message] of [
  ['<header class="media-intro">', 'Media listing must use a dedicated editorial intro.'],
  ['<span class="media-card-body">', 'Media cards must group their readable content.'],
  ["'theobroma-media-card'", 'Media cards must request the optimized card image size.'],
  ["'loading' => $media_post_index === 0 ? 'eager' : 'lazy'", 'Only the first media image may load eagerly.'],
  ['class="media-card-arrow"', 'Media cards must expose a clear reading affordance.'],
]) assertIncludes(listing, needle, message);

for (const [needle, message] of [
  ['get_header();', 'Articles must use the shared site header.'],
  ['get_footer();', 'Articles must use the shared site footer.'],
  ['<article class="media-article">', 'Article content must use article semantics.'],
  ['<header class="media-article-hero">', 'Articles must have an editorial hero.'],
  ['class="media-article-breadcrumb"', 'Articles must include shared breadcrumb navigation.'],
  ['class="media-article-meta"', 'Articles must surface date metadata near the title.'],
  ['class="media-article-cover"', 'Article cover must have a dedicated responsive frame.'],
]) assertIncludes(article, needle, message);

for (const [needle, message] of [
  ['/* Media editorial refresh */', 'Media UI styles must be isolated and documented.'],
  ['.media-card:focus-within', 'Media cards need a keyboard focus treatment.'],
  ['.media-card-image img {', 'Media cards need responsive image styling.'],
  ['.media-article-copy > * + *', 'Article body needs consistent vertical rhythm.'],
  ['@media (max-width:600px)', 'Media UI must define a mobile layout.'],
  ['@media (prefers-reduced-motion:reduce)', 'Media UI must respect reduced motion.'],
]) assertIncludes(styles, needle, message);

assertIncludes(homeStyles, '.media-article-products-grid.home-product-grid {', 'The late shared product stylesheet must own the article three-column grid.');
assertIncludes(homeStyles, 'grid-template-columns: repeat(3, minmax(0, 1fr));', 'Article products must fill a centered three-column desktop row.');
assertIncludes(configure, 'wp_update_image_subsizes($thumbnail_id)', 'Environment configuration must backfill missing media card thumbnails.');

console.log('Media editorial templates and responsive UI contract verified.');
