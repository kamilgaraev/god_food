const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const baseUrl = (process.env.BASE_URL || 'http://localhost:8080').replace(/\/$/, '');

function translationX(transform) {
  if (!transform || transform === 'none') return 0;

  const values = transform.match(/^matrix(?:3d)?\((.+)\)$/)?.[1].split(',').map(Number) || [];
  return values.length === 16 ? values[12] : (values[4] || 0);
}

async function openHomepage(browser, options = {}) {
  const context = await browser.newContext({
    viewport: options.viewport || { width: 1440, height: 900 },
    reducedMotion: options.reducedMotion || 'no-preference',
  });
  const page = await context.newPage();
  const response = await page.goto(baseUrl, { waitUntil: 'networkidle', timeout: 45_000 });
  assert.ok(response?.ok(), 'homepage must load successfully');
  await page.evaluate(() => document.fonts.ready);
  return { context, page };
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const desktop = await openHomepage(browser);
    const track = desktop.page.locator('.home-benefit-strip__track');
    const groups = desktop.page.locator('.home-benefit-strip__group');

    assert.equal(await track.count(), 1, 'benefit strip must expose one animated track');
    assert.equal(await groups.count(), 2, 'benefit strip must duplicate its content for a seamless loop');

    const coverage = await groups.evaluateAll((nodes) => nodes.map((node) => node.getBoundingClientRect().width));
    assert.ok(coverage.every((width) => width >= 1440), 'each repeated group must cover the desktop viewport');

    const itemGaps = await groups.first().evaluate((group) => {
      const children = [...group.children].slice(0, 7);
      return children.slice(0, -1).map((child, index) => {
        const current = child.getBoundingClientRect();
        const next = children[index + 1].getBoundingClientRect();
        return next.left - current.right;
      });
    });
    assert.ok(itemGaps.every((gap) => gap > 0 && gap <= 24), `benefit items must remain compact, got gaps: ${itemGaps.join(', ')}`);

    const animation = await track.evaluate((node) => {
      const style = getComputedStyle(node);
      return { name: style.animationName, iterationCount: style.animationIterationCount };
    });
    assert.notEqual(animation.name, 'none', 'benefit track must be animated');
    assert.equal(animation.iterationCount, 'infinite', 'benefit track animation must loop forever');

    const startX = translationX(await track.evaluate((node) => getComputedStyle(node).transform));
    await desktop.page.waitForTimeout(350);
    const endX = translationX(await track.evaluate((node) => getComputedStyle(node).transform));
    assert.ok(endX > startX, `benefit track must move left to right, got ${startX}px -> ${endX}px`);
    await desktop.context.close();

    const mobile = await openHomepage(browser, { viewport: { width: 390, height: 844 } });
    assert.ok(
      await mobile.page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth),
      'animated benefit strip must not create horizontal page overflow on mobile',
    );
    assert.notEqual(
      await mobile.page.locator('.home-benefit-strip__track').evaluate((node) => getComputedStyle(node).animationName),
      'none',
      'benefit strip must animate on mobile',
    );
    await mobile.context.close();

    const reduced = await openHomepage(browser, { reducedMotion: 'reduce' });
    assert.equal(
      await reduced.page.locator('.home-benefit-strip__track').evaluate((node) => getComputedStyle(node).animationName),
      'none',
      'reduced-motion users must receive a static benefit strip',
    );
    await reduced.context.close();
  } finally {
    await browser.close();
  }

  console.log('Homepage benefit marquee verified.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
