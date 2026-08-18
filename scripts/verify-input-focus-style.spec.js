const fs = require('node:fs');
const path = require('node:path');

const stylesheet = fs.readFileSync(
  path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'style.css'),
  'utf8',
);

const textFieldFocusRule = /:where\(input:not\(\[type="checkbox"\]\):not\(\[type="radio"\]\),textarea,select\):focus\s*\{[^}]*outline:none;/;

if (!textFieldFocusRule.test(stylesheet)) {
  throw new Error('Text inputs must suppress the browser focus outline without affecting checkbox or radio controls.');
}

console.log('Input focus style verification passed.');
