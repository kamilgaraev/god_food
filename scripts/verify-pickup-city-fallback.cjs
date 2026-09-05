const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('wp-content/plugins/theobroma-commerce/assets/js/checkout.js', 'utf8');
const body = source.slice(source.indexOf('  function loadPointsForCheckoutAddress('), source.indexOf('  function renderSuggestions('));
async function run(first, stale = false) {
  const calls = [], loaded = [], errors = [], input = {value: 'full address with office'};
  let city = 'Казань';
  const viewport = {left_bottom: {lat: 55, long: 49}, right_top: {lat: 56, long: 50}};
  const context = {state: {provider: 'ozon'}, config: {suggestionsUrl: '/suggestions'}, customer: () => ({city, country: 'RU'}), field: () => ({selectedOptions: [{textContent: 'Россия'}]}), document: {querySelector: () => input}, setStatus: (message, error) => {if (error) errors.push(message);}, renderSuggestions: () => {}, loadPoints: v => loaded.push(v), request: async url => {calls.push(url); if (stale) city = 'Москва'; return calls.length === 1 ? first : {suggestions: [{viewport}]};}};
  vm.runInNewContext(body + '\nloadPointsForCheckoutAddress("full address with office");', context);
  await new Promise(resolve => setImmediate(resolve));
  return {calls, loaded, errors, input, viewport};
}
(async () => {
  const fallback = await run({suggestions: []});
  assert.equal(fallback.calls.length, 2);
  assert.ok(decodeURIComponent(fallback.calls[1]).includes('Россия, Казань'));
  assert.deepEqual(fallback.loaded, [fallback.viewport]);
  assert.equal(fallback.input.value, '');
  const direct = await run({suggestions: [{viewport: {valid: true}}]});
  assert.equal(direct.calls.length, 1);
  assert.equal(direct.loaded.length, 1);
  const stale = await run({suggestions: []}, true);
  assert.equal(stale.calls.length, 1);
  assert.equal(stale.loaded.length, 0);
  console.log('PASS pickup lookup: address, city fallback, stale response');
})().catch(e => { console.error(e); process.exitCode = 1; });
