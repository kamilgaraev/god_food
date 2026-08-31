const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'wp-content/plugins/theobroma-commerce/assets/js/delivery-selector-core.js'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'wp-content/plugins/theobroma-commerce/assets/css/checkout-delivery.css'), 'utf8');
const context = { window: {} };
vm.runInNewContext(source, context);
const core = context.window.TheobromaDeliveryCore;

assert.deepEqual(
  Array.from(core.filterPoints([
    { id: '1', name: 'Ozon ПВЗ', address: 'Москва, Тверская' },
    { id: '2', name: 'СДЭК', address: 'Казань, Баумана' },
  ], 'твер')), [{ id: '1', name: 'Ozon ПВЗ', address: 'Москва, Тверская' }]
);
assert.equal(core.canRenderMap({ mapEnabled: true, mapKey: 'key' }), true);
assert.equal(core.canRenderMap({ mapEnabled: false, mapKey: 'key' }), false);
assert.equal(core.canRenderMap({ mapEnabled: true, mapKey: '' }), false);

const payload = core.buildQuotePayload('ozon', 'pickup', { id: '125' }, {
  city: 'Москва',
  postcode: '101000',
  address: 'Тверская, 1',
  first_name: 'Иван',
  last_name: 'Иванов',
  phone: '+7 999 000-00-00',
});
assert.equal(payload.provider, 'ozon');
assert.equal(payload.kind, 'pickup');
assert.equal(payload.point_id, '125');
assert.equal(payload.city, 'Москва');
assert.equal(payload.phone, '+7 999 000-00-00');

assert.match(styles, /\.commerce-cart-checkout \.woocommerce-checkout-review-order-table\s*\{[^}]*display:\s*table/s);
assert.match(styles, /\.commerce-cart-checkout \.woocommerce-shipping-totals\s*\{[^}]*display:\s*table-row/s);

console.log('delivery selector core: ok');
