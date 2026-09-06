const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const locationSource = fs.readFileSync('wp-content/plugins/theobroma-commerce/assets/js/checkout.js', 'utf8');
const locationFunction = locationSource.slice(locationSource.indexOf('  function detectCity()'), locationSource.indexOf('  function open(provider)'));
async function locationTest({accuracy = 20, edit = false, savedCity = '', expectedAddress = 'Тверская, 7'} = {}) {
  const fields = Object.fromEntries(['city', 'country', 'address', 'postcode', 'address_2'].map(n => [n, {value: n === 'country' ? 'RU' : n === 'city' ? savedCity : ''}]));
  fields.country.options = [{value: 'RU'}];
  let geo, sent, query;
  const checkout = {};
  const state = {provider: 'cdek', selected: null};
  const ctx = {state, navigator: {geolocation: {getCurrentPosition: cb => { geo = cb; }}}, field: n => fields[n], dialog: () => ({open: true}), config: {suggestionsUrl: '/suggestions'}, document: {querySelector: () => ({value: ''})}, setCheckoutValue: (k, v) => {checkout[k] = v;}, renderSuggestions() {}, renderPoints() {}, loadPointsForCheckoutAddress: q => {query = q;}, request: async (url, opts) => {sent = {url, opts}; return {city: 'Москва', country: 'RU', address: 'Тверская, 7', postcode: '125009'};}};
  vm.createContext(ctx); vm.runInContext(locationFunction + '\ndetectCity();', ctx);
  if (edit) fields.address.value = 'Мой адрес';
  geo({coords: {latitude: 55.757, longitude: 37.613, accuracy}});
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(fields.address.value, edit ? 'Мой адрес' : expectedAddress);
  if (edit) assert.equal(sent, undefined, 'Do not send location after manual edits');
  else {assert.equal(sent.opts.method, 'POST'); assert.equal(sent.url, '/suggestions');}
  if (!edit && !savedCity && accuracy <= 150) {assert.equal(fields.postcode.value, '125009'); assert.equal(query, 'Москва, Тверская, 7');}
}
const cartSource = fs.readFileSync('wp-content/themes/theobroma/assets/js/commerce-modals.js', 'utf8');
const cartFunction = cartSource.slice(cartSource.indexOf('    const openCart ='), cartSource.indexOf('    const updateCart ='));
async function cartTest() {
  const events = []; let complete;
  const attrs = new Map();
  const opener = {getAttribute: k => attrs.get(k), setAttribute: (k,v) => attrs.set(k,v), removeAttribute: k => attrs.delete(k)};
  const ctx = {trigger: null, config: {cartUrl: '/cart/'}, window: {location: {assign: () => events.push('fallback')}}, request: () => new Promise(r => {complete = r;}), renderCart: () => events.push('render'), showModal: () => events.push('show'), setLoading: () => events.push('empty'), focusFirstModalControl: () => events.push('focus')};
  vm.createContext(ctx); vm.runInContext(cartFunction + '\nglobalThis.openCart = openCart;', ctx);
  const opening = ctx.openCart(opener);
  assert.deepEqual(events, [], 'Do not flash an empty overlay before data arrives');
  assert.equal(attrs.get('aria-busy'), 'true');
  await ctx.openCart(opener); // Duplicate clicks must not issue another request.
  complete({success: true, data: {}}); await opening;
  assert.deepEqual(events, ['render', 'show', 'focus']);
  assert.equal(attrs.has('aria-busy'), false);
}
(async () => {
  await locationTest();
  await locationTest({edit: true});
  await locationTest({accuracy: 1000, expectedAddress: ''});
  await locationTest({savedCity: 'Казань', expectedAddress: ''});
  await cartTest();
  console.log('PASS address: POST, city/address/postcode, manual edit, low accuracy, saved destination; cart: render before open, duplicate click');
})().catch(error => {console.error(error); process.exit(1);});
