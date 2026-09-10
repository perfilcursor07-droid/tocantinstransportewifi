const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const elements = {
    'interval-plan-fields': { dataset: { maxDays: '30', baseCents: '699', today: '2026-09-10' }, addEventListener(_, fn) { this.change = fn; } },
    'interval-start': { value: '2026-09-10' },
    'interval-end': { value: '2026-09-11' },
    'interval-summary': {},
    'interval-error': { classList: { toggle() {} } },
    'interval-plan-option': { classList: { contains() { return true; } } },
};
const hours = { value: '12' };
const price = {};
let ready;
const context = vm.createContext({ window: {}, document: {
    getElementById: id => elements[id],
    querySelector: selector => selector.includes(':checked') ? hours : selector.includes('price-display') ? price : null,
    addEventListener: (event, fn) => { if (event === 'DOMContentLoaded') ready = fn; },
}, console });
vm.runInContext(fs.readFileSync('public/js/interval-plan.js', 'utf8'), context);
const plan = context.window.IntervalPlan;
assert.equal(plan.selection().amount, 13.98);
hours.value = '24';
assert.equal(plan.selection().amount, 27.96);
elements['interval-end'].value = '2026-09-10';
assert.equal(plan.selection().valid, false);
ready();
context.window.selectWifiPlan = () => {};
elements['interval-start'].value = '2026-09-30';
elements['interval-plan-fields'].change({ target: { id: 'interval-start', value: '2026-09-30' } });
assert.equal(elements['interval-end'].value, '2026-10-01');
assert.equal(elements['interval-end'].min, '2026-10-01');
context.window.WIFI_SELECTED_PLAN = plan.selection();
assert.equal(plan.payload().interval_hours, 24);
context.window.WIFI_SELECTED_PLAN = { amount: 6.99, duration: 12 };
assert.equal(Object.keys(plan.payload()).length, 0);
console.log('Interval UI logic: 8 assertions passed.');
