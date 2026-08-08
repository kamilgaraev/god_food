const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const header = read('wp-content/themes/theobroma/header.php');
const footer = read('wp-content/themes/theobroma/footer.php');
const contactSection = read('wp-content/themes/theobroma/template-parts/contact-section.php');
const home = read('wp-content/themes/theobroma/index.php');
const functions = read('wp-content/themes/theobroma/functions.php');

assert.match(footer, /<footer\b[^>]*\bid="contacts"/, 'footer must own the #contacts anchor');
assert.doesNotMatch(contactSection, /\bid="contacts"/, 'contact form must not duplicate the #contacts anchor');
assert.match(contactSection, /<section\b[^>]*\bid="contact-form"/, 'contact form needs its own #contact-form anchor');
assert.match(header, /home_url\('\/#contacts'\)/, 'navigation contact links must target the footer');
assert.match(home, /href="#contact-form"/, 'homepage order CTA must target the contact form');
assert.doesNotMatch(home, /href="#contacts"/, 'homepage order CTA must not target the footer');
assert.match(functions, /'#contact-form'/, 'form result redirects must return to the contact form');

console.log('Contact anchors verified');
