const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

async function main() {
    let fetchImpl;
    let calls = 0;
    const timeouts = new Map();
    let timerId = 0;
    const context = vm.createContext({
        window: {}, document: { addEventListener() {}, querySelector() { return null; } },
        console: { log() {}, warn() {}, error() {} }, AbortController,
        setTimeout(fn) { timeouts.set(++timerId, fn); return timerId; },
        clearTimeout(id) { timeouts.delete(id); },
        fetch: (...args) => { calls++; return fetchImpl(...args); },
    });
    vm.runInContext(fs.readFileSync('public/js/portal.js', 'utf8'), context);
    const portal = Object.create(context.window.WiFiPortal.prototype);
    portal.updateBypassBanner = () => {};
    portal.syncAfterCopyHints = () => {};
    const success = () => ({ ok: true, json: async () => ({ success: true, expires_in: 180 }) });

    fetchImpl = async () => { throw new Error('network down'); };
    await portal.detectAndBypass(1);
    assert.equal(portal._bypassMode, 'error');
    fetchImpl = async () => success();
    await portal.detectAndBypass(1);
    assert.equal(portal._bypassMode, 'success');
    assert.equal(calls, 2);
    await portal.detectAndBypass(1);
    assert.equal(calls, 2, 'active grant must not submit another request');
    portal._bypassExpiresAt = 0;
    await portal.detectAndBypass(1);
    assert.equal(calls, 3, 'expired grant may request the remaining allowance');

    portal._bypassRan = false;
    fetchImpl = (_, options) => new Promise((resolve, reject) => {
        assert.equal(options.keepalive, true);
        options.signal.addEventListener('abort', () => reject(new Error('timeout')));
    });
    const pending = portal.detectAndBypass(1);
    await portal.detectAndBypass(1);
    assert.equal(calls, 4, 'double click must not submit concurrently');
    [...timeouts.values()][0]();
    await pending;
    assert.equal(portal._bypassRan, false);
    assert.equal(timeouts.size, 0);

    fetchImpl = async () => success();
    await portal.detectAndBypass(1);
    assert.equal(portal._bypassMode, 'success');
    portal._bypassRan = false;
    let finishOld;
    fetchImpl = () => new Promise(resolve => { finishOld = resolve; });
    const oldRequest = portal.detectAndBypass(1);
    portal._bypassPaymentId = 2;
    portal._bypassMode = null;
    finishOld(success());
    await oldRequest;
    assert.equal(portal._bypassMode, null, 'old modal response must not affect new PIX');
    const errors = [];
    portal.showErrorMessage = message => errors.push(message);
    portal.showSuccessMessage = () => {};
    context.navigator = { clipboard: { writeText: async () => {} } };
    assert.equal(await portal.copyPixCode('PIX', { silent: true }), true);
    context.navigator.clipboard.writeText = async () => { throw new Error('clipboard blocked'); };
    context.document.createElement = () => ({ select() {} });
    context.document.body = { appendChild() {}, removeChild() {} };
    context.document.execCommand = () => true;
    assert.equal(await portal.copyPixCode('PIX', { silent: true }), true);
    context.document.execCommand = () => false;
    assert.equal(await portal.copyPixCode('PIX', { silent: true }), false);
    assert.equal(errors.length, 1, 'clipboard failure must not be presented as copied');
    console.log('Temporary bypass JS: network retry, expiry, timeout, double click and stale response passed.');
}
main().catch(error => { console.error(error); process.exitCode = 1; });
