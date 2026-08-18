const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright');

const themeDirectory = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma');
const footerTemplate = path.join(themeDirectory, 'footer.php');
const stylesheet = fs.readFileSync(path.join(themeDirectory, 'style.css'), 'utf8');

const contacts = {
  footer_address: 'Адрес производства:\nМосковская обл.,\nНаро-Фоминский г.о.,\nд.Мартемьяново 230Б. 143345',
  footer_phone_1: '+7 499 755 54 90',
  footer_phone_2: '+7 (800) 444-70-54',
  footer_info_email: 'info@theobroma.msk.ru',
  footer_opt_email: 'opt@theobroma.msk.ru',
  footer_press_email: 'press@theobroma.msk.ru',
};

function renderFooterFixture() {
  const temporaryDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'theobroma-footer-'));
  const harnessPath = path.join(temporaryDirectory, 'render.php');
  const encodedContacts = Buffer.from(JSON.stringify(contacts)).toString('base64');
  const templatePath = footerTemplate.replaceAll('\\', '/').replaceAll("'", "\\'");

  fs.writeFileSync(harnessPath, `<?php
$content = json_decode(base64_decode('${encodedContacts}'), true);
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function theobroma_content($key) { global $content; return $content[$key] ?? ''; }
function theobroma_page_url($title) { return '#'; }
function get_template_directory_uri() { return '/theme'; }
function home_url($path = '/') { return $path; }
function wp_footer() {}
include '${templatePath}';
`, 'utf8');

  try {
    const result = spawnSync('php', [harnessPath], { encoding: 'utf8' });
    if (result.status !== 0) {
      throw new Error(`footer PHP fixture failed:\n${result.stderr || result.stdout}`);
    }
    return result.stdout;
  } finally {
    fs.rmSync(temporaryDirectory, { recursive: true, force: true });
  }
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await page.setContent(`<style>${stylesheet}</style>${renderFooterFixture()}`);

    const phoneLinks = await page.locator('.footer-phones a').evaluateAll((links) => links.map((link) => ({
      text: link.textContent.trim(),
      href: link.getAttribute('href'),
    })));
    const emailLinks = await page.locator('.footer-mail strong a').evaluateAll((links) => links.map((link) => ({
      text: link.textContent.trim(),
      href: link.getAttribute('href'),
    })));
    const addressLink = await page.locator('.footer-address a').evaluate((link) => ({
      text: link.textContent.trim(),
      href: link.getAttribute('href'),
      target: link.getAttribute('target'),
      rel: link.getAttribute('rel'),
    }));
    const phoneStyles = await page.locator('.footer-phones a').evaluateAll((links) => links.map((link) => {
      const style = getComputedStyle(link);
      const bounds = link.getBoundingClientRect();
      return {
        position: style.position,
        fontFamily: style.fontFamily,
        fontSize: parseFloat(style.fontSize),
        width: bounds.width,
        height: bounds.height,
      };
    }));

    const expectedPhones = [
      { text: contacts.footer_phone_1, href: 'tel:+74997555490' },
      { text: contacts.footer_phone_2, href: 'tel:+78004447054' },
    ];
    const expectedEmails = [
      { text: contacts.footer_info_email, href: `mailto:${contacts.footer_info_email}` },
      { text: contacts.footer_opt_email, href: `mailto:${contacts.footer_opt_email}` },
      { text: contacts.footer_press_email, href: `mailto:${contacts.footer_press_email}` },
    ];
    const expectedAddress = {
      text: contacts.footer_address,
      href: `https://yandex.ru/maps/?text=${encodeURIComponent(contacts.footer_address)}`,
      target: '_blank',
      rel: 'noopener noreferrer',
    };

    if (JSON.stringify(phoneLinks) !== JSON.stringify(expectedPhones)) {
      throw new Error(`footer phones must be clickable: expected ${JSON.stringify(expectedPhones)}, received ${JSON.stringify(phoneLinks)}`);
    }
    if (JSON.stringify(emailLinks) !== JSON.stringify(expectedEmails)) {
      throw new Error(`footer emails must be clickable: expected ${JSON.stringify(expectedEmails)}, received ${JSON.stringify(emailLinks)}`);
    }
    if (JSON.stringify(addressLink) !== JSON.stringify(expectedAddress)) {
      throw new Error(`footer address must open Yandex Maps: expected ${JSON.stringify(expectedAddress)}, received ${JSON.stringify(addressLink)}`);
    }
    if (phoneStyles.some((style) => style.position !== 'absolute'
      || !style.fontFamily.includes('Cormorant')
      || style.fontSize < 20
      || style.width <= 0
      || style.height <= 0)) {
      throw new Error(`footer phone links must preserve their typography and clickable area: received ${JSON.stringify(phoneStyles)}`);
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
