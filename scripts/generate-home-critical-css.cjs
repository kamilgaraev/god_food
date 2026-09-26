const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');
const { chromium } = require('playwright');

const theme = path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma');
const files = ['style.css', 'assets/css/home-redesign.css'];
const coverage = new Map(files.map((file) => [file, { text: '', ranges: new Map() }]));
const widths = (process.env.CRITICAL_WIDTHS || '320,390,600,601,768,900,1199,1200,1440,1920').split(',').map(Number);

function enclosingAtRules(text, ranges) {
  const offsets = [...new Set(ranges.map((range) => range.start))].sort((a, b) => a - b);
  const result = new Map();
  const stack = [];
  let segmentStart = 0;
  let comment = false;
  let quote = '';
  let parens = 0;
  let next = 0;

  for (let i = 0; i <= offsets[offsets.length - 1]; i++) {
    while (next < offsets.length && offsets[next] === i) {
      result.set(i, stack.filter((item) => item.startsWith('@')));
      next++;
    }
    const char = text[i];
    if (comment) {
      if (char === '*' && text[i + 1] === '/') { comment = false; i++; }
      continue;
    }
    if (quote) {
      if (char === '\\') i++;
      else if (char === quote) quote = '';
      continue;
    }
    if (char === '/' && text[i + 1] === '*') { comment = true; i++; continue; }
    if (char === '"' || char === "'") { quote = char; continue; }
    if (char === '(') parens++;
    else if (char === ')') parens = Math.max(0, parens - 1);
    else if (char === '{') {
      stack.push(text.slice(segmentStart, i).replace(/\/\*[\s\S]*?\*\//g, '').trim());
      segmentStart = i + 1;
    }
    else if (char === '}') { stack.pop(); segmentStart = i + 1; }
    else if (char === ';' && parens === 0) segmentStart = i + 1;
  }
  return result;
}

function localizeUrls(css, file) {
  return css.replace(/url\(\s*(['"]?)([^'"\)]+)\1\s*\)/g, (match, quote, url) => {
    if (/^(?:data:|https?:|\/|#)/i.test(url)) return match;
    const asset = path.posix.normalize(path.posix.join(path.posix.dirname(file), url));
    return `url('__THEME_URI__/${asset}')`;
  });
}

function singleLineMediaBlock(text, start) {
  const prefix = text.slice(Math.max(0, start - 24), start);
  const match = prefix.match(/@media\s*$/);
  if (!match) return '';
  const begin = start - match[0].length;
  const open = text.indexOf('{', start);
  if (open < 0) return '';
  let depth = 0;
  for (let i = open; i < text.length; i++) {
    if (text[i] === '{') depth++;
    else if (text[i] === '}' && --depth === 0) {
      const block = text.slice(begin, i + 1);
      return block.includes('\n') || block.length > 3000 ? '' : block;
    }
  }
  return '';
}

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROME_PATH || undefined,
    args: ['--no-proxy-server', '--disable-http2'],
  });
  try {
    const sourcePage = await browser.newPage();
    await sourcePage.goto(process.env.THEOBROMA_URL || 'https://theobroma.one/', { waitUntil: 'domcontentloaded', timeout: 60000 });
    const firstScreenHtml = await sourcePage.evaluate(() => {
      const copy = document.documentElement.cloneNode(true);
      const main = copy.querySelector('main');
      if (!main?.querySelector('.home-hero')) throw new Error('Home hero not found');
      for (const child of [...main.children]) {
        if (!child.classList.contains('home-hero')) child.remove();
      }
      for (const child of [...copy.querySelector('body').children]) {
        if (child.matches('.site-header,.mobile-menu,main,.skip-link')) continue;
        child.remove();
      }
      copy.querySelectorAll('script,link[rel="preload"],link[rel="manifest"],#theobroma-home-redesign-inline-css').forEach((node) => node.remove());
      const neededStyles = new Set(['theobroma-style-css', 'theobroma-home-redesign-css', 'theobroma-hero-alignment-css', 'theobroma-design-system-css']);
      copy.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
        if (!neededStyles.has(link.id)) link.remove();
        else { link.media = 'all'; link.removeAttribute('onload'); }
      });
      return `<!doctype html>${copy.outerHTML}`;
    });
    await sourcePage.close();

    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await page.coverage.startCSSCoverage();
      await page.setContent(firstScreenHtml, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await page.waitForFunction(() => ['theobroma-style-css', 'theobroma-home-redesign-css'].every((id) => document.getElementById(id)?.sheet), undefined, { timeout: 60000 });
      await page.evaluate(() => document.fonts.ready);
      const entries = await page.coverage.stopCSSCoverage();
      for (const entry of entries) {
        const file = files.find((candidate) => entry.url.endsWith(`/${candidate}`) || entry.url.includes(`/${candidate}?`));
        if (!file) continue;
        const item = coverage.get(file);
        if (item.text) assert.equal(entry.text, item.text, `${file} changed between viewport runs`);
        item.text = entry.text;
        for (const range of entry.ranges) item.ranges.set(`${range.start}:${range.end}`, { start: range.start, end: range.end });
      }
      console.log(`${width}: CSS coverage captured`);
      await page.close();
    }
  } finally { await browser.close(); }

  const hashes = {};
  const chunks = [];
  for (const file of files) {
    const item = coverage.get(file);
    assert(item.text, `${file} not found in page`);
    const local = fs.readFileSync(path.join(theme, file), 'utf8');
    const normalized = local.replace(/\r\n/g, '\n');
    assert(item.text === local || item.text === normalized, `${file} on site differs from local checkout`);
    hashes[file] = crypto.createHash('sha256').update(normalized).digest('hex');
    const ranges = [...item.ranges.values()].sort((a, b) => a.start - b.start || a.end - b.end);
    const wrappers = enclosingAtRules(item.text, ranges);
    const rules = [];
    if (file === 'style.css') {
      rules.push(...[...item.text.matchAll(/@font-face\s*\{[^}]*\}/g)].map((match) => match[0]));
    }
    for (const range of ranges) {
      const rule = item.text.slice(range.start, range.end).trim();
      if (rule.startsWith('(')) {
        const block = singleLineMediaBlock(item.text, range.start);
        if (block) rules.push(block);
        continue;
      }
      if (!rule.includes('{') || !rule.endsWith('}') || /^(?:\(|@(?:media|supports|container|layer)\b)/.test(rule)) continue;
      const parents = wrappers.get(range.start) || [];
      rules.push(`${parents.map((parent) => `${parent}{`).join('')}${rule}${'}'.repeat(parents.length)}`);
    }
    chunks.push(localizeUrls([...new Set(rules)].join('\n'), file));
  }
  const header = `/* Generated by scripts/generate-home-critical-css.cjs. source-sha256 style.css=${hashes['style.css']} home-redesign.css=${hashes['assets/css/home-redesign.css']} */`;
  const output = `${header}\n${chunks.join('\n')}\n`;
  const dest = path.join(theme, 'assets', 'css', 'home-critical.css');
  fs.writeFileSync(dest, output);
  console.log(`Wrote ${dest}: ${output.length} bytes, ${zlib.gzipSync(output).length} gzip`);
})().catch((error) => { console.error(error); process.exit(1); });
