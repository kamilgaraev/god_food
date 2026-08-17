const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.resolve(__dirname, '../wp-content/themes/theobroma/assets/css/home-redesign.css'),
  'utf8',
);

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent('<a class="home-button home-button--secondary" href="#gifts">Подарочные наборы</a>');
    await page.addStyleTag({ content: stylesheet });
    await page.waitForTimeout(300);

    const button = page.locator('.home-button--secondary');
    const defaultState = await button.evaluate((element) => {
      const style = getComputedStyle(element);
      return {
        color: style.color,
        borderColor: style.borderColor,
        backgroundColor: style.backgroundColor,
      };
    });

    assert.equal(defaultState.color, 'rgb(176, 144, 61)', 'Gift button text must be gold by default');
    assert.equal(defaultState.borderColor, 'rgb(176, 144, 61)', 'Gift button border must be gold by default');
    assert.equal(defaultState.backgroundColor, 'rgba(0, 0, 0, 0)', 'Gift button background must be transparent by default');

    await button.hover();
    await page.waitForTimeout(300);

    const hoverState = await button.evaluate((element) => {
      const style = getComputedStyle(element);
      return {
        color: style.color,
        borderColor: style.borderColor,
        backgroundColor: style.backgroundColor,
      };
    });

    assert.equal(hoverState.color, 'rgb(255, 255, 255)', 'Gift button text must be white on hover');
    assert.equal(hoverState.borderColor, 'rgb(176, 144, 61)', 'Gift button border must stay gold on hover');
    assert.equal(hoverState.backgroundColor, 'rgb(176, 144, 61)', 'Gift button background must be gold on hover');
  } finally {
    await browser.close();
  }

  console.log('Homepage gift button uses the gold outline and gold hover fill');
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
