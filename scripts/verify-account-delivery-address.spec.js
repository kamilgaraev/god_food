const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const stylesheet = fs.readFileSync(
  path.join(__dirname, '../wp-content/themes/theobroma/style.css'),
  'utf8',
);

const select2BaseStyles = `
  .select2-container { box-sizing:border-box; display:inline-block; position:relative; vertical-align:middle; }
  .select2-selection--single { box-sizing:border-box; cursor:pointer; display:block; height:28px; user-select:none; }
  .select2-selection__rendered { display:block; overflow:hidden; padding-left:8px; padding-right:20px; text-overflow:ellipsis; white-space:nowrap; }
  .select2-selection__arrow { height:26px; position:absolute; top:1px; right:1px; width:20px; }
  .select2-selection__arrow b { border-color:#888 transparent transparent; border-style:solid; border-width:5px 4px 0; height:0; left:50%; margin-left:-4px; margin-top:-2px; position:absolute; top:50%; width:0; }
  .select2-dropdown { background:#fff; border:1px solid #aaa; box-sizing:border-box; position:absolute; z-index:1051; }
  .select2-search--dropdown { display:block; padding:4px; }
  .select2-search__field { box-sizing:border-box; width:100%; }
  .select2-results__options { margin:0; padding:0; list-style:none; }
  .select2-results__option { padding:6px; user-select:none; }
  .select2-results__option--highlighted[aria-selected] { color:#fff; background:#5897fb; }
`;

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const width of [390, 800, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 700 } });
      await page.setContent(`
        <style>${select2BaseStyles}\n${stylesheet}</style>
        <body class="logged-in woocommerce-account">
          <main class="shop-page"><div class="shop-shell"><div class="woocommerce">
            <div class="woocommerce-MyAccount-content">
              <form class="woocommerce-address-fields">
                <p class="form-row form-row-wide">
                  <label for="billing_first_name">Имя</label>
                  <input class="input-text" id="billing_first_name" type="text">
                </p>
                <p class="form-row form-row-wide" id="billing_country_field">
                  <label for="billing_country">Страна/регион <span class="required">*</span></label>
                  <select class="country_to_state country_select" id="billing_country" name="billing_country" hidden>
                    <option value="">Выберите страну/регион…</option>
                    <option value="RU">Россия</option>
                    <option value="BY">Беларусь</option>
                  </select>
                  <span class="select2 select2-container select2-container--default select2-container--open">
                    <span class="selection">
                      <span class="select2-selection select2-selection--single" role="combobox" aria-expanded="true">
                        <span class="select2-selection__rendered">Выберите страну/регион…</span>
                        <span class="select2-selection__arrow" role="presentation"><b role="presentation"></b></span>
                      </span>
                    </span>
                  </span>
                </p>
              </form>
              <section class="theobroma-address-book">
                <p class="theobroma-address-book__lead">Сохраните адрес, куда нужно доставлять ваши заказы.</p>
                <article class="theobroma-address-card">
                  <header><div><small>Основной адрес</small><h2>Адрес доставки</h2></div><a class="button" href="#">Добавить адрес доставки</a></header>
                  <p class="theobroma-address-card__empty">Адрес пока не указан.</p>
                </article>
              </section>
            </div>
          </div></div></main>
          <span class="select2-container select2-container--default select2-container--open" data-test-country-dropdown style="width:20rem;position:absolute;left:1rem;top:12rem">
            <span class="select2-dropdown select2-dropdown--below">
              <span class="select2-search select2-search--dropdown"><input class="select2-search__field" type="search"></span>
              <span class="select2-results">
                <ul class="select2-results__options">
                  <li class="select2-results__option" aria-selected="true">Россия</li>
                  <li class="select2-results__option select2-results__option--highlighted" aria-selected="false">Беларусь</li>
                </ul>
              </span>
            </span>
          </span>
        </body>
      `);

      const metrics = await page.evaluate(() => {
        const content = document.querySelector('.woocommerce-MyAccount-content').getBoundingClientRect();
        const addressForm = document.querySelector('.woocommerce-address-fields').getBoundingClientRect();
        const book = document.querySelector('.theobroma-address-book').getBoundingClientRect();
        const card = document.querySelector('.theobroma-address-card').getBoundingClientRect();
        const action = document.querySelector('.theobroma-address-card .button').getBoundingClientRect();
        const cardStyle = getComputedStyle(document.querySelector('.theobroma-address-card'));
        const actionStyle = getComputedStyle(document.querySelector('.theobroma-address-card .button'));
        const countryElement = document.querySelector('.select2-selection--single');
        const country = countryElement.getBoundingClientRect();
        const countryStyle = getComputedStyle(countryElement);
        const countryTextStyle = getComputedStyle(countryElement.querySelector('.select2-selection__rendered'));
        const arrowElement = document.querySelector('.select2-selection__arrow');
        const arrow = arrowElement.getBoundingClientRect();
        const arrowIconStyle = getComputedStyle(arrowElement.querySelector('b'));
        const dropdownElement = document.querySelector('[data-test-country-dropdown] .select2-dropdown');
        const dropdownStyle = getComputedStyle(dropdownElement);
        const searchElement = dropdownElement.querySelector('.select2-search__field');
        const search = searchElement.getBoundingClientRect();
        const searchStyle = getComputedStyle(searchElement);
        const highlightedStyle = getComputedStyle(dropdownElement.querySelector('.select2-results__option--highlighted'));
        const selectedStyle = getComputedStyle(dropdownElement.querySelector('.select2-results__option[aria-selected="true"]'));
        const textInput = document.querySelector('#billing_first_name').getBoundingClientRect();
        const textInputStyle = getComputedStyle(document.querySelector('#billing_first_name'));
        return {
          contentWidth: content.width,
          addressFormWidth: addressForm.width,
          bookWidth: book.width,
          cardWidth: card.width,
          cardContentWidth: card.width
            - parseFloat(cardStyle.paddingLeft)
            - parseFloat(cardStyle.paddingRight)
            - parseFloat(cardStyle.borderLeftWidth)
            - parseFloat(cardStyle.borderRightWidth),
          cardBorderWidth: cardStyle.borderTopWidth,
          cardBackground: cardStyle.backgroundColor,
          actionWidth: action.width,
          actionHeight: action.height,
          actionDisplay: actionStyle.display,
          actionAlignItems: actionStyle.alignItems,
          actionJustifyContent: actionStyle.justifyContent,
          actionBackground: actionStyle.backgroundColor,
          countryWidth: country.width,
          countryHeight: country.height,
          countryBackground: countryStyle.backgroundColor,
          countryBorderTop: countryStyle.borderTopWidth,
          countryBorderBottom: countryStyle.borderBottom,
          countryBorderRadius: countryStyle.borderRadius,
          countryPaddingLeft: countryTextStyle.paddingLeft,
          countryFontFamily: countryTextStyle.fontFamily,
          arrowTopOffset: arrow.top - country.top,
          arrowRightOffset: country.right - arrow.right,
          arrowHeight: arrow.height,
          arrowBorderRight: arrowIconStyle.borderRight,
          arrowBorderBottom: arrowIconStyle.borderBottom,
          dropdownBackground: dropdownStyle.backgroundColor,
          dropdownBorder: dropdownStyle.borderTop,
          dropdownFontFamily: dropdownStyle.fontFamily,
          searchHeight: search.height,
          searchBackground: searchStyle.backgroundColor,
          searchBorder: searchStyle.borderTop,
          highlightedBackground: highlightedStyle.backgroundColor,
          highlightedColor: highlightedStyle.color,
          selectedBackground: selectedStyle.backgroundColor,
          textInputHeight: textInput.height,
          textInputPaddingLeft: textInputStyle.paddingLeft,
          overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
      });

      assert.ok(metrics.bookWidth <= metrics.contentWidth + 0.5, `${width}px: address book must fit the account panel`);
      assert.ok(Math.abs(metrics.cardWidth - metrics.bookWidth) <= 0.5, `${width}px: delivery card must use the available width`);
      assert.equal(metrics.cardBorderWidth, '1px', `${width}px: delivery card must have a crisp boundary`);
      assert.equal(metrics.cardBackground, 'rgb(252, 249, 247)', `${width}px: delivery card must use the light account surface`);
      assert.ok(metrics.actionHeight >= 40, `${width}px: address action must have at least a 40px hit area`);
      assert.ok(['flex', 'inline-flex'].includes(metrics.actionDisplay), `${width}px: address action must use flexbox`);
      assert.equal(metrics.actionAlignItems, 'center', `${width}px: address action text must be vertically centered`);
      assert.equal(metrics.actionJustifyContent, 'center', `${width}px: address action text must be horizontally centered`);
      assert.equal(metrics.actionBackground, 'rgb(176, 144, 61)', `${width}px: address action must use the brand color`);
      assert.ok(Math.abs(metrics.countryWidth - metrics.addressFormWidth) <= 0.5, `${width}px: country field must fill the address form`);
      assert.ok(Math.abs(metrics.countryHeight - metrics.textInputHeight) <= 0.5, `${width}px: country field must match account text inputs`);
      assert.equal(metrics.countryBackground, 'rgb(252, 249, 247)', `${width}px: country field must use the account input surface`);
      assert.equal(metrics.countryBorderTop, '0px', `${width}px: country field must not use the browser box border`);
      assert.match(metrics.countryBorderBottom, /^1px solid rgb\(176, 144, 61\)/, `${width}px: country field must use the gold underline`);
      assert.equal(metrics.countryBorderRadius, '0px', `${width}px: country field must keep the square account style`);
      assert.equal(metrics.countryPaddingLeft, metrics.textInputPaddingLeft, `${width}px: country field text must align with account inputs`);
      assert.match(metrics.countryFontFamily, /Montserrat/i, `${width}px: country field must use the account typography`);
      assert.ok(Math.abs(metrics.arrowTopOffset) <= 0.5, `${width}px: country arrow must start at the field top edge`);
      assert.ok(Math.abs(metrics.arrowRightOffset) <= 0.5, `${width}px: country arrow must stay inside the field's right edge`);
      assert.ok(Math.abs(metrics.arrowHeight - metrics.countryHeight) <= 0.5, `${width}px: country arrow must be vertically aligned with the field`);
      assert.match(metrics.arrowBorderRight, /^1px solid rgb\(52, 52, 52\)/, `${width}px: country arrow must use the custom dark chevron`);
      assert.match(metrics.arrowBorderBottom, /^1px solid rgb\(52, 52, 52\)/, `${width}px: country arrow must use the custom dark chevron`);
      assert.equal(metrics.dropdownBackground, 'rgb(252, 249, 247)', `${width}px: country dropdown must use the account surface`);
      assert.match(metrics.dropdownBorder, /^1px solid rgba?\(176, 144, 61/, `${width}px: country dropdown must use a gold boundary`);
      assert.match(metrics.dropdownFontFamily, /Montserrat/i, `${width}px: country dropdown must use account typography`);
      assert.ok(metrics.searchHeight >= 40, `${width}px: country search must keep a usable hit area`);
      assert.equal(metrics.searchBackground, 'rgb(243, 235, 228)', `${width}px: country search must use the cream input surface`);
      assert.match(metrics.searchBorder, /^1px solid rgb\(176, 144, 61\)/, `${width}px: country search must use a gold boundary`);
      assert.equal(metrics.highlightedBackground, 'rgb(176, 144, 61)', `${width}px: highlighted country must use the brand color instead of Select2 blue`);
      assert.equal(metrics.highlightedColor, 'rgb(255, 255, 255)', `${width}px: highlighted country text must stay legible`);
      assert.equal(metrics.selectedBackground, 'rgb(243, 235, 228)', `${width}px: selected country must use a quiet cream highlight`);
      if (width <= 600) {
        assert.ok(Math.abs(metrics.actionWidth - metrics.cardContentWidth) <= 1, `${width}px: mobile action must span the card content width (content ${metrics.cardContentWidth}px, action ${metrics.actionWidth}px)`);
      }
      assert.ok(metrics.overflow <= 1, `${width}px: address page must not overflow horizontally`);

      await page.close();
    }

    console.log('Account delivery-address layout verified across responsive widths.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
