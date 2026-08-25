const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const themeRoot = path.resolve(__dirname, '../wp-content/themes/theobroma');
const homepage = fs.readFileSync(path.join(themeRoot, 'index.php'), 'utf8');
const stylesheet = fs.readFileSync(path.join(themeRoot, 'assets/css/home-redesign.css'), 'utf8');
const composition = homepage.match(/<section class="home-composition"[\s\S]*?<\/section>/)?.[0];

async function inspectComposition(page, width) {
  await page.setViewportSize({ width, height: 900 });

  return page.locator('.home-composition').evaluate((section) => {
    const listStyle = getComputedStyle(section.querySelector('dl'));
    const statBorders = Array.from(section.querySelectorAll('dl > div'), (stat) => {
      const style = getComputedStyle(stat);

      return {
        top: style.borderTopWidth,
        right: style.borderRightWidth,
        bottom: style.borderBottomWidth,
        left: style.borderLeftWidth,
      };
    });

    return {
      kickerCount: section.querySelectorAll('.home-kicker').length,
      background: getComputedStyle(section).backgroundColor,
      numberColor: getComputedStyle(section.querySelector('dt')).color,
      statColumns: listStyle.gridTemplateColumns.split(' ').filter(Boolean).length,
      statBorders,
    };
  });
}

function assertResponsiveCross(metrics, width) {
  assert.equal(metrics.statColumns, 2, `${width}px composition statistics must use two columns`);
  assert.deepEqual(metrics.statBorders, [
    { top: '0px', right: '0px', bottom: '0px', left: '0px' },
    { top: '0px', right: '0px', bottom: '0px', left: '1px' },
    { top: '1px', right: '0px', bottom: '0px', left: '0px' },
    { top: '1px', right: '0px', bottom: '0px', left: '1px' },
  ], `${width}px composition statistics must be divided by a centered cross without an outer border`);
}

(async () => {
  assert(composition, 'Homepage composition section must exist');

  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(composition);
    await page.addStyleTag({ content: stylesheet });

    const desktop = await inspectComposition(page, 1440);
    assert.equal(desktop.kickerCount, 0, 'Composition must start with its heading, without the "СОСТАВ" kicker');
    assert.equal(desktop.background, 'rgb(255, 255, 255)', 'Composition must use a clean white background');
    assert.equal(desktop.numberColor, 'rgb(176, 144, 61)', 'Composition numbers must use the brand gold');

    const tablet = await inspectComposition(page, 900);
    assertResponsiveCross(tablet, 900);

    const mobile = await inspectComposition(page, 390);
    assertResponsiveCross(mobile, 390);

    if (process.env.COMPOSITION_SCREENSHOT) {
      await page.locator('.home-composition').screenshot({ path: process.env.COMPOSITION_SCREENSHOT });
    }
  } finally {
    await browser.close();
  }

  console.log('Homepage composition uses the approved white and gold treatment with responsive cross lines');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
