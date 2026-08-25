const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const fail = (message) => {
  throw new Error(message);
};

const home = read('wp-content/themes/theobroma/index.php');
const corporate = read('wp-content/themes/theobroma/template-parts/pages/corporate-gifts.php');
const css = read('wp-content/plugins/theobroma-photo-showcases/assets/frontend.css');
const configure = read('scripts/configure-wordpress.php');
const verifyWordPress = read('scripts/verify-wordpress.php');

const homeCall = "function_exists('theobroma_photo_showcase_html')";
const homeLocation = "theobroma_photo_showcase_html('home')";
if (!home.includes(homeCall) || !home.includes(homeLocation)) {
  fail('Homepage must guard and render the photo showcase plugin API.');
}
if (!(home.indexOf(homeLocation) > home.indexOf('home-composition') && home.indexOf(homeLocation) < home.indexOf('home-promo-grid'))) {
  fail('Homepage photo story must sit between composition and promo cards.');
}

const corporateLocation = "theobroma_photo_showcase_html('corporate')";
if (!corporate.includes(homeCall) || !corporate.includes(corporateLocation)) {
  fail('Corporate gifts must guard and render the photo showcase plugin API.');
}
if (!(corporate.indexOf(corporateLocation) > corporate.indexOf('corporate-gifts-branding') && corporate.indexOf(corporateLocation) < corporate.indexOf('corporate-gifts-cases'))) {
  fail('Corporate photo cases must sit between branding and business scenarios.');
}

for (const contract of [
  '.theobroma-photo-showcase--home .theobroma-photo-showcase__gallery',
  '.theobroma-photo-showcase--corporate .theobroma-photo-showcase__gallery',
  'grid-template-areas:',
  'scroll-snap-type: x mandatory',
  ':focus-visible',
  '@media (prefers-reduced-motion: reduce)',
]) {
  if (!css.includes(contract)) fail(`Frontend photo CSS is missing: ${contract}`);
}

const mobileBlock = css.match(/@media \(max-width:\s*600px\)[\s\S]*$/)?.[0] || '';
if (!mobileBlock.includes('grid-auto-flow: column') || !mobileBlock.includes('scroll-snap-align: start')) {
  fail('Mobile showcases must become a horizontal snap gallery.');
}
const pluginEntry = 'theobroma-photo-showcases/theobroma-photo-showcases.php';
if (!configure.includes(pluginEntry) || !verifyWordPress.includes(pluginEntry)) {
  fail('Reproducible WordPress setup and verification must require the photo showcase plugin.');
}

console.log('Photo showcase theme integration and responsive layouts verified.');
