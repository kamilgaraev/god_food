const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const stylesheet = fs.readFileSync(
  path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css'),
  'utf8',
);

assert.doesNotMatch(
  stylesheet,
  /\.nav-links a:not\(\.header-icon\)\s*\{[^}]*border-bottom:/s,
  'The old full-height link border must be removed',
);
assert.match(
  stylesheet,
  /\.nav-links a:not\(\.header-icon\)::after\s*\{[^}]*bottom:\s*0\.375rem;[^}]*opacity:\s*0;[^}]*transform:\s*scaleX\(0\);[^}]*transition:\s*transform 220ms[^;]*,\s*opacity 180ms/s,
  'The underline must sit closer to the label and have a smooth hidden state',
);
assert.match(
  stylesheet,
  /\.nav-links a:not\(\.header-icon\):(?:hover|focus-visible)::after[^}]*\{[^}]*opacity:\s*1;[^}]*transform:\s*scaleX\(1\);/s,
  'Hover and keyboard focus must reveal the underline',
);

console.log('Header navigation underline reveals smoothly above the link edge');
