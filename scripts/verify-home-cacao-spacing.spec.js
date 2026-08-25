const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const cssPath = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'assets', 'css', 'home-redesign.css');
const css = fs.readFileSync(cssPath, 'utf8');

function rule(selector) {
  const escapedSelector = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const matches = Array.from(css.matchAll(new RegExp(`${escapedSelector}\\s*\\{([^}]*)\\}`, 'g')));
  assert(matches.length, `Missing CSS rule for ${selector}`);
  return matches.map((match) => match[1]).join('\n');
}

const section = rule('.home-cacao');
const panel = rule('.home-cacao__panel');
const imageWrap = rule('.home-cacao__image-wrap');
const image = rule('.home-cacao__image-wrap img');

assert.match(section, /padding:\s*2\.375rem\s+var\(--home-gutter\)\s+2\.375rem;/, 'Desktop cacao section must have equal top and bottom spacing');
assert.match(
  css,
  /@media\s*\(max-width:\s*1199px\)\s*\{\s*\.home-cacao\s*\{[^}]*border-block:\s*0;/,
  'Mobile and tablet cacao section must not render separator borders',
);
assert.match(panel, /margin-top:\s*0;/, 'Desktop cacao panel must not negate the section top spacing');
assert.match(imageWrap, /padding:\s*0;/, 'Cacao image must use the full circular frame');
assert.match(image, /object-fit:\s*cover;/, 'Cacao image must cover its circular frame');
