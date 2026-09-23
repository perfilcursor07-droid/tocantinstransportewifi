const { chromium } = require(process.env.PLAYWRIGHT_MODULE);
const assert = require('node:assert/strict');
const path = require('node:path');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 950 } });
            await page.route('**/*', route => {
                const url = new URL(route.request().url());
                if (url.hostname === '127.0.0.1') return route.continue();
                if (url.hostname === 'cdn.tailwindcss.com') return route.fulfill({ path: path.resolve('public/js/tailwind.play.js'), contentType: 'application/javascript' });
                return route.abort();
            });
            const errors = [];
            page.on('pageerror', e => errors.push(e.message));
            await page.goto('http://127.0.0.1:8097/?source=mikrotik&mac=D6:DE:C4:66:F2:84&ip=10.5.50.249', { waitUntil: 'networkidle' });
            await page.locator('#interval-plan-option').click();
            assert.match(await page.locator('#interval-summary').textContent(), /13,98/);
            const start = await page.locator('#interval-start').inputValue();
            const next = new Date(Date.parse(start + 'T00:00:00Z') + 86400000).toISOString().slice(0, 10);
            assert.equal(await page.locator('#interval-end').inputValue(), start);
            assert.equal(await page.locator('[name="interval-hours"]').count(), 0);
            assert.match(await page.locator('#interval-plan-fields').textContent(), /24 horas corridas/);
            await page.locator('#interval-end').fill(next);
            assert.match(await page.locator('#interval-summary').textContent(), /27,96/);
            assert.equal(await page.locator('#connect-btn').isDisabled(), false);
            const container = page.locator('#interval-plan-option').locator('..');
            await container.screenshot({ path: `storage/app/interval-${width}.png` });
            const bounds = await page.locator('#interval-plan-fields').evaluate(el => {
                const r = el.getBoundingClientRect();
                return [...el.querySelectorAll('input')].every(input => {
                    const box = input.getBoundingClientRect();
                    return box.left >= r.left && box.right <= r.right + 1;
                });
            });
            assert.equal(bounds, true);
            await page.locator('[data-plan-default="true"]').click();
            assert.equal(await page.locator('#interval-plan-fields').isVisible(), false);
            assert.equal(await page.evaluate(() => window.WIFI_SELECTED_PLAN.amount), 6.99);
            assert.equal(await page.evaluate(() => window.IntervalPlan.payload().plan_type), undefined);
            console.log(JSON.stringify({ width, checks: 'dates, one or more days, totals, standard plan, input bounds', errors }));
            await page.goto('http://127.0.0.1:8097/admin/settings', { waitUntil: 'networkidle' });
            assert.equal(await page.locator('#plan_interval_max_days').inputValue(), '30');
            assert.equal(await page.locator('#plan_interval_price_24h').inputValue(), '13.98');
            await page.locator('#plan_interval_enabled').uncheck();
            assert.equal(await page.locator('#plan_interval_enabled').isChecked(), false);
            await page.locator('#plan_interval_enabled').check();
            await page.locator('#plan_interval_enabled').locator('..').locator('..').locator('..').screenshot({ path: `storage/app/interval-admin-${width}.png` });
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
