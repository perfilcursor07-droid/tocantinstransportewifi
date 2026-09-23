(() => {
    'use strict';
    const money = cents => (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const dateNumber = value => /^\d{4}-\d{2}-\d{2}$/.test(value) ? Date.parse(`${value}T00:00:00Z`) : NaN;

    window.IntervalPlan = {
        selection() {
            const fields = document.getElementById('interval-plan-fields');
            const start = document.getElementById('interval-start').value;
            const end = document.getElementById('interval-end').value;
            const hours = 24;
            const days = Math.round((dateNumber(end) - dateNumber(start)) / 86400000) + 1;
            const valid = Number.isFinite(days) && days >= 1 && days <= Number(fields.dataset.maxDays)
                && start >= fields.dataset.today;
            const dailyCents = Number(fields.dataset.dailyCents);
            const totalCents = valid ? dailyCents * days : 0;
            document.getElementById('interval-summary').textContent = valid
                ? `${days} dias · Total ${money(totalCents)}` : '';
            const error = document.getElementById('interval-error');
            error.textContent = valid ? '' : `Escolha de 1 a ${fields.dataset.maxDays} dias.`;
            error.classList.toggle('hidden', valid);
            document.querySelector('#interval-plan-option [data-plan-price-display]').textContent = valid ? money(totalCents) : '--';
            return { amount: totalCents / 100, duration: hours, name: 'Plano por intervalo', suffix: `/ ${days || 0} dia(s)`,
                plan_type: 'interval', interval_start: start, interval_end: end, interval_hours: hours, valid };
        },
        payload() {
            const plan = window.WIFI_SELECTED_PLAN;
            if (plan?.plan_type !== 'interval') return {};
            return { plan_type: 'interval', interval_start: plan.interval_start, interval_end: plan.interval_end, interval_hours: plan.interval_hours };
        },
    };

    let inFlight;
    let retryTimer;
    let retries = 0;
    window.IntervalAccess = {
        async connect(portal) {
            if (!portal.deviceMac || document.visibilityState === 'hidden') return { state: 'none' };
            if (inFlight) return inFlight;
            inFlight = (async () => {
                let result;
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 10000);
                try {
                    const response = await fetch('/api/interval/connect', {
                        method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ mac_address: portal.deviceMac, ip_address: portal.deviceIp || null }),
                        signal: controller.signal,
                    });
                    if (!response.ok) throw new Error('interval_access_unavailable');
                    result = await response.json();
                } catch (_) {
                    result = { state: 'error', message: 'Não foi possível consultar suas diárias. Tente novamente.' };
                } finally {
                    clearTimeout(timeout);
                }
                const panel = document.getElementById('interval-access-status');
                if (panel) {
                    panel.hidden = result.state === 'none' || (result.state === 'error' && !portal.intervalAccess);
                    panel.querySelector('[data-interval-message]').textContent = result.message || '';
                    panel.querySelector('button').hidden = !['ready', 'error'].includes(result.state);
                }
                portal.intervalAccess = result;
                if (result.expires_at) portal.intervalExpiresAt = result.expires_at;
                if (['ready', 'error'].includes(result.state) && retries < 6) {
                    clearTimeout(retryTimer);
                    retries++;
                    retryTimer = setTimeout(() => window.IntervalAccess.connect(portal), 15000);
                }
                return result;
            })();
            try { return await inFlight; } finally { inFlight = null; }
        },
    };

    document.addEventListener('DOMContentLoaded', () => {
        const fields = document.getElementById('interval-plan-fields');
        fields?.addEventListener('change', event => {
            if (event.target.id === 'interval-start' && Number.isFinite(dateNumber(event.target.value))) {
                const end = document.getElementById('interval-end');
                const minimum = event.target.value;
                end.min = minimum;
                end.max = new Date(dateNumber(event.target.value) + (Number(fields.dataset.maxDays) - 1) * 86400000).toISOString().slice(0, 10);
                if (!end.value || end.value < minimum) end.value = minimum;
            }
            const card = document.getElementById('interval-plan-option');
            if (card.classList.contains('plan-card-selected')) window.selectWifiPlan(card);
        });
        document.querySelector('#interval-access-status button')?.addEventListener('click', () => {
            retries = 0;
            if (window.wifiPortal) window.IntervalAccess.connect(window.wifiPortal);
        });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && window.wifiPortal) {
                retries = 0;
                window.IntervalAccess.connect(window.wifiPortal);
            }
        });
    });
})();
