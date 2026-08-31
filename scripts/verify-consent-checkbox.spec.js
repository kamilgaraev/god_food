const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.join(__dirname, '..', 'wp-content', 'themes', 'theobroma', 'style.css'),
  'utf8',
);

const fixtures = [
  { html: '<label class="consent"><input type="checkbox"><span>Согласие на обработку данных</span></label>' },
  { html: '<section class="cooperation-form"><label class="consent"><input type="checkbox"><span>Согласие на обработку данных</span></label></section>' },
  { html: '<section class="corporate-gifts-request"><form><label class="consent"><input type="checkbox"><span>Согласие на обработку данных</span></label></form></section>', expectedLabelMarginTop: 0 },
  { html: '<div class="commerce-cart-checkout"><p class="commerce-checkout-consent"><label class="consent"><input type="checkbox"><span>Согласие на обработку данных</span></label></p></div>' },
  { html: '<div class="commerce-cart-checkout"><label class="woocommerce-form__label-for-checkbox"><input class="woocommerce-form__input-checkbox" type="checkbox"><span>Публичная оферта</span></label></div>' },
];

async function checkboxMetrics(page, fixture) {
  await page.setContent(`<style>${stylesheet}</style>${fixture.html}`);
  const checkbox = page.locator('input[type="checkbox"]');
  await page.keyboard.press('Tab');
  await checkbox.evaluate((input) => { input.checked = true; });

  return checkbox.evaluate((input) => {
    const inputRect = input.getBoundingClientRect();
    const textRect = input.nextElementSibling.getBoundingClientRect();
    const style = getComputedStyle(input);

    return {
      rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
      width: inputRect.width,
      height: inputRect.height,
      centerDelta: Math.abs(
        inputRect.top + inputRect.height / 2 - (textRect.top + textRect.height / 2),
      ),
      opacity: style.opacity,
      appearance: style.appearance,
      borderWidth: style.borderTopWidth,
      borderColor: style.borderTopColor,
      backgroundColor: style.backgroundColor,
      backgroundImage: style.backgroundImage,
      backgroundPosition: style.backgroundPosition,
      outlineWidth: style.outlineWidth,
      outlineOffset: style.outlineOffset,
      labelMarginTop: parseFloat(getComputedStyle(input.closest('label')).marginTop),
    };
  });
}

async function checkoutDividerSpacing(page) {
  await page.setContent(`
    <style>${stylesheet}</style>
    <div class="commerce-cart-checkout">
      <form class="checkout">
        <div id="payment">
          <div class="form-row place-order" style="border-top: 1px solid #ddd">
            <div class="woocommerce-terms-and-conditions-wrapper">
              <p class="commerce-checkout-consent">
                <label class="consent"><input type="checkbox"><span>Согласие на обработку данных</span></label>
              </p>
              <label class="woocommerce-form__label-for-checkbox">
                <input class="woocommerce-form__input-checkbox" type="checkbox"><span>Публичная оферта</span>
              </label>
            </div>
          </div>
        </div>
      </form>
    </div>
  `);

  return page.evaluate(() => {
    const divider = document.querySelector('.place-order').getBoundingClientRect();
    const consents = document.querySelector('.woocommerce-terms-and-conditions-wrapper').getBoundingClientRect();
    return {
      rootFontSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
      spacing: consents.top - divider.top,
    };
  });
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const deviceScaleFactor of [1, 2]) {
      for (const width of [390, 800, 1440]) {
        const page = await browser.newPage({
          viewport: { width, height: 600 },
          deviceScaleFactor,
        });

        for (const fixture of fixtures) {
          const metrics = await checkboxMetrics(page, fixture);
          const expectedSize = metrics.rootFontSize * 1.25;
          assert.ok(Math.abs(metrics.width - expectedSize) <= 0.02, 'Consent checkbox width must follow the 1.25rem design scale');
          assert.ok(Math.abs(metrics.height - expectedSize) <= 0.02, 'Consent checkbox height must follow the 1.25rem design scale');
          assert.ok(metrics.centerDelta <= 1, 'Checkbox and consent text must share the same vertical center');
          assert.equal(metrics.opacity, '1', 'The real checkbox control must remain visible');
          assert.equal(metrics.appearance, 'none', 'Consent checkboxes must use the shared cross-browser rendering');
          assert.equal(metrics.borderWidth, '1px', 'Consent checkbox border must stay crisp');
          assert.equal(metrics.borderColor, 'rgb(176, 144, 61)', 'Consent checkbox must use the brand-gold border');
          assert.equal(metrics.backgroundColor, 'rgb(176, 144, 61)', 'Checked consent checkbox must use the brand-gold fill');
          assert.notEqual(metrics.backgroundImage, 'none', 'Checked consent checkbox must display a checkmark');
          assert.equal(metrics.backgroundPosition, '50% 50%', 'The checkmark must be geometrically centered');
          assert.equal(metrics.outlineWidth, '2px', 'Keyboard focus must have a clear outline');
          assert.equal(metrics.outlineOffset, '3px', 'Keyboard focus outline must not blur into the border');
          if (fixture.expectedLabelMarginTop !== undefined) {
            assert.equal(metrics.labelMarginTop, fixture.expectedLabelMarginTop, 'Shared checkbox styling must preserve the form layout');
          }
        }

        const dividerSpacing = await checkoutDividerSpacing(page);
        assert.ok(
          dividerSpacing.spacing >= dividerSpacing.rootFontSize,
          `Checkout consent controls must start at least 1rem below the divider (received ${dividerSpacing.spacing}px)`,
        );

        await page.close();
      }
    }

    console.log('Consent checkbox visual contract verified across form contexts, viewport scales, and DPR values.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
