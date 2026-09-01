const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const stylesheet = path.join(root, 'wp-content/themes/theobroma/assets/css/woocommerce-order-ui.css');
const baseStylesheet = path.join(root, 'wp-content/themes/theobroma/style.css');

assert.equal(fs.existsSync(stylesheet), true, 'WooCommerce order UI stylesheet must exist');
const styles = fs.readFileSync(stylesheet, 'utf8');
const baseStyles = fs.readFileSync(baseStylesheet, 'utf8');

const markup = `
  <main class="shop-page"><div class="shop-shell"><div class="woocommerce">
    <div class="woocommerce-order">
      <p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">Ваш заказ принят. Благодарим вас.</p>
      <ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
        <li>Номер заказа:<strong>182</strong></li>
        <li>Дата:<strong>01.09.2026</strong></li>
        <li>Email:<strong>buyer@example.test</strong></li>
        <li>Итого:<strong>1 516 р.</strong></li>
        <li>Способ оплаты:<strong>Оплата при получении</strong></li>
      </ul>
      <section class="woocommerce-order-details">
        <h2 class="woocommerce-order-details__title">Информация о заказе</h2>
        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
          <thead><tr><th>Товар</th><th>Итого</th></tr></thead>
          <tbody><tr><td data-title="Товар">68% горький шоколад 200г × 1</td><td data-title="Итого">1 426 р.</td></tr></tbody>
          <tfoot><tr><th scope="row">Доставка:</th><td data-title="Доставка">90 р. – Пункт Ozon</td></tr><tr><th scope="row">Итого:</th><td data-title="Итого">1 516 р.</td></tr></tfoot>
        </table>
      </section>
      <section class="woocommerce-customer-details">
        <h2>Данные получателя</h2>
        <div class="woocommerce-columns woocommerce-columns--2">
          <div class="woocommerce-column woocommerce-column--billing-address"><h3>Платёжный адрес</h3><address>Казань, Спартаковская улица, 12</address></div>
          <div class="woocommerce-column woocommerce-column--shipping-address"><h3>Адрес доставки</h3><address>Пункт Ozon, Спартаковская улица, 14</address></div>
        </div>
      </section>
    </div>
    <table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders">
      <thead><tr><th>Заказ</th><th>Дата</th><th>Статус</th><th>Итого</th><th>Действия</th></tr></thead>
      <tbody><tr><td data-title="Заказ">№182</td><td data-title="Дата">01.09.2026</td><td data-title="Статус">В обработке</td><td data-title="Итого">1 516 р.</td><td data-title="Действия"><a class="button" href="#">Посмотреть</a></td></tr></tbody>
    </table>
  </div></div></main>`;

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
    await page.setContent(`<style>${baseStyles}</style><style>${styles}</style><body class="logged-in woocommerce-account woocommerce-view-order">${markup}</body>`);

    const desktop = await page.evaluate(() => {
      const overview = getComputedStyle(document.querySelector('.woocommerce-order-overview'));
      const heading = getComputedStyle(document.querySelector('.woocommerce-order-details__title'));
      const columns = getComputedStyle(document.querySelector('.woocommerce-customer-details .woocommerce-columns'));
      const button = getComputedStyle(document.querySelector('.woocommerce-orders-table .button'));
      return {
        overviewBackground: overview.backgroundColor,
        headingTransform: heading.textTransform,
        headingSpacing: heading.letterSpacing,
        columnCount: columns.gridTemplateColumns.split(' ').length,
        buttonRadius: parseFloat(button.borderRadius),
      };
    });

    assert.equal(desktop.overviewBackground, 'rgb(243, 235, 228)');
    assert.equal(desktop.headingTransform, 'none');
    assert.ok(desktop.headingSpacing === 'normal' || desktop.headingSpacing === '0px');
    assert.equal(desktop.columnCount, 2);
    assert.ok(desktop.buttonRadius >= 20);

    await page.setViewportSize({ width: 390, height: 844 });
    const mobile = await page.evaluate(() => {
      const orderRow = getComputedStyle(document.querySelector('.woocommerce-orders-table tbody tr'));
      const columns = getComputedStyle(document.querySelector('.woocommerce-customer-details .woocommerce-columns'));
      return {
        rowDisplay: orderRow.display,
        columnCount: columns.gridTemplateColumns.split(' ').length,
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      };
    });

    assert.equal(mobile.rowDisplay, 'grid');
    assert.equal(mobile.columnCount, 1);
    assert.ok(mobile.overflow <= 0, `mobile order UI overflows by ${mobile.overflow}px`);
  } finally {
    await browser.close();
  }
  console.log('WooCommerce order UI verification passed.');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
