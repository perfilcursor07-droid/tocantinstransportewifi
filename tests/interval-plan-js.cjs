const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const elements = {
    'interval-plan-fields': { dataset: { maxDays: '30', dailyCents: '1398', today: '2026-09-10', timezone: 'America/Araguaina' }, addEventListener(_, fn) { this.change = fn; } },
    'interval-start': { value: '2026-09-10' },
    'interval-end': { value: '2026-09-10' },
    'interval-summary': {},
    'interval-error': { classList: { toggle() {} } },
    'interval-plan-option': { classList: { contains() { return true; } } },
};
const price = {};
const paymentWindowTitle = {};
const paymentWindow = {};
const paymentWindowNote = {};
let ready;
const context = vm.createContext({ window: {}, document: {
    getElementById: id => elements[id],
    querySelector: selector => selector.includes('price-display') ? price
        : selector === '[data-payment-window-title]' ? paymentWindowTitle
        : selector === '[data-payment-window]' ? paymentWindow
        : selector === '[data-payment-window-note]' ? paymentWindowNote : null,
    addEventListener: (event, fn) => { if (event === 'DOMContentLoaded') ready = fn; },
}, console });
vm.runInContext(fs.readFileSync('public/js/interval-plan.js', 'utf8'), context);
const plan = context.window.IntervalPlan;
assert.equal(plan.selection().amount, 13.98);
assert.match(paymentWindowTitle.textContent, /Pagando agora/);
assert.match(paymentWindow.textContent, /^De \d{2}\/\d{2}\/\d{4} \d{2}:\d{2} até \d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/);
assert.match(paymentWindowNote.textContent, /confirmação do PIX/);
elements['interval-end'].value = '2026-09-09';
assert.equal(plan.selection().valid, false);
ready();
context.window.selectWifiPlan = () => {};
elements['interval-start'].value = '2026-09-30';
elements['interval-plan-fields'].change({ target: { id: 'interval-start', value: '2026-09-30' } });
assert.equal(elements['interval-end'].value, '2026-09-30');
assert.equal(elements['interval-end'].min, '2026-09-30');
context.window.WIFI_SELECTED_PLAN = plan.selection();
assert.equal(plan.payload().interval_hours, 24);
context.window.WIFI_SELECTED_PLAN = { amount: 6.99, duration: 12 };
assert.equal(Object.keys(plan.payload()).length, 0);
console.log('Interval UI logic: 10 assertions passed.');
