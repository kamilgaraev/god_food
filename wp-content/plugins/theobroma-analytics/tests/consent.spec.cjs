// php wp-content/plugins/theobroma-analytics/tests/run.php --export-bootstrap | node wp-content/plugins/theobroma-analytics/tests/consent.spec.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const bootstrap = fs.readFileSync(process.argv[2] || 0, 'utf8');
assert.ok(bootstrap.includes('mc.yandex.ru/metrika/tag.js'), 'Pipe the PHP-generated bootstrap into this test');
const header = fs.readFileSync(path.join(__dirname, '../../../themes/theobroma/assets/js/site-header.js'), 'utf8');
const cookieStart = header.indexOf('    const cookieNotice =');
const cookieEnd = header.indexOf('    const sourceTextReveals =', cookieStart);
assert.ok(cookieStart >= 0 && cookieEnd > cookieStart, 'Cookie controls must be present in the real header script');
const controls = header.slice(cookieStart, cookieEnd);
const key = 'theobroma_cookie_notice_accepted';
const disabledKey = 'disableYaCounter12345678';

function page(saved = null, { blockedStorage = false } = {}) {
    const storage = new Map(saved === null ? [] : [[key, saved]]);
    const listeners = new Map();
    const scripts = [];
    const calls = [];
    let focused = null;
    function element(choice) {
        const handlers = new Map();
        return {
            dataset: { cookieChoice: choice },
            attributes: {},
            addEventListener(type, handler) { handlers.set(type, handler); },
            setAttribute(name, value) { this.attributes[name] = value; },
            focus() { focused = this; },
            click() { handlers.get('click')?.({ preventDefault() {} }); },
        };
    }
    const reject = element('0');
    const accept = element('1');
    const settings = element();
    const notice = {
        hidden: true,
        querySelectorAll() { return [reject, accept]; },
        querySelector() { return reject; },
    };
    const window = {
        localStorage: {
            getItem(name) {
                if (blockedStorage) throw new Error('Storage blocked');
                return storage.get(name) ?? null;
            },
            setItem(name, value) {
                if (blockedStorage) throw new Error('Storage blocked');
                storage.set(name, value);
            },
        },
        addEventListener(type, handler) {
            if (!listeners.has(type)) listeners.set(type, []);
            listeners.get(type).push(handler);
        },
        dispatchEvent(event) { listeners.get(event.type)?.forEach(handler => handler(event)); },
    };
    const document = {
        head: { appendChild(script) { scripts.push(script); } },
        getElementsByTagName() { return [{ parentNode: { insertBefore(script) { scripts.push(script); } } }]; },
        createElement() { return {}; },
        querySelector() { return notice; },
        querySelectorAll() { return [settings]; },
    };
    const context = vm.createContext(Object.assign(window, { window, document, CustomEvent: class {
        constructor(type, options) { this.type = type; this.detail = options?.detail; }
    } }));
    vm.runInContext(bootstrap, context);
    vm.runInContext(controls, context);
    return {
        window, scripts, calls, storage, reject, accept, notice, settings,
        get focused() { return focused; },
        finishLoad() {
            const queued = window.ym?.a || [];
            window.ym = (...args) => calls.push(args);
            queued.forEach(args => window.ym(...args));
            scripts.at(-1).onload?.();
        },
        changeInAnotherTab(value) {
            if (value === null) storage.delete(key);
            else storage.set(key, value);
            window.dispatchEvent({ type: 'storage', key });
        },
        methods() { return calls.map(call => call[1]); },
    };
}

test('first visit starts analytics without requiring a banner choice', () => {
    const p = page();
    assert.equal(p.notice.hidden, false);
    assert.equal(p.scripts.length, 1);
    assert.equal(p.window[disabledKey], false);
    p.finishLoad();
    assert.deepEqual(p.methods(), ['init']);
    assert.equal(p.calls[0][0], 12345678);
    assert.equal(p.calls[0][2].webvisor, false);
});

test('existing refusal prevents even downloading the external tag', () => {
    const p = page('0');
    assert.equal(p.scripts.length, 0);
    assert.equal(p.window[disabledKey], true);
    assert.equal(p.notice.hidden, true);
});

test('existing permission starts analytics and keeps the notice dismissed', () => {
    const p = page('1');
    assert.equal(p.scripts.length, 1);
    assert.equal(p.notice.hidden, true);
    p.finishLoad();
    assert.deepEqual(p.methods(), ['init']);
});

test('refusal while tag downloads prevents its later initialization', () => {
    const p = page();
    p.reject.click();
    assert.equal(p.storage.get(key), '0');
    assert.equal(p.notice.hidden, true);
    assert.equal(p.window[disabledKey], true);
    p.finishLoad();
    assert.deepEqual(p.methods(), []);
    assert.equal(page(p.storage.get(key)).scripts.length, 0);
});

test('refusal stops an initialized counter immediately', () => {
    const p = page();
    p.finishLoad();
    p.reject.click();
    assert.deepEqual(p.methods(), ['init', 'destruct']);
    assert.equal(p.window[disabledKey], true);
    p.window.dispatchEvent({ type: 'theobroma:cookie-consent' });
    assert.deepEqual(p.methods(), ['init', 'destruct']);
});

test('footer settings allow changing the choice and restore keyboard focus', () => {
    const p = page('0');
    p.settings.click();
    assert.equal(p.notice.hidden, false);
    assert.equal(p.settings.attributes['aria-expanded'], 'true');
    assert.equal(p.focused, p.reject);
    p.accept.click();
    assert.equal(p.storage.get(key), '1');
    assert.equal(p.notice.hidden, true);
    assert.equal(p.settings.attributes['aria-expanded'], 'false');
    assert.equal(p.focused, p.settings);
    assert.equal(p.scripts.length, 1);
    p.finishLoad();
    p.settings.click();
    p.reject.click();
    assert.deepEqual(p.methods(), ['init', 'destruct']);
});

test('repeated acceptance does not duplicate scripts or counters', () => {
    const p = page();
    p.accept.click();
    p.accept.click();
    assert.equal(p.scripts.length, 1);
    p.finishLoad();
    p.accept.click();
    assert.deepEqual(p.methods(), ['init']);
});

test('a stopped counter can be explicitly enabled again on the same page', () => {
    const p = page();
    p.finishLoad();
    p.reject.click();
    p.accept.click();
    assert.equal(p.scripts.length, 1);
    assert.equal(p.window[disabledKey], false);
    assert.deepEqual(p.methods(), ['init', 'destruct', 'init']);
});

test('changing the choice during download initializes only the final allowed state', () => {
    const p = page();
    p.reject.click();
    p.accept.click();
    p.finishLoad();
    assert.equal(p.scripts.length, 1);
    assert.deepEqual(p.methods(), ['init']);
});

test('refusal in another tab stops tracking and synchronizes the notice', () => {
    const p = page();
    p.finishLoad();
    p.changeInAnotherTab('0');
    assert.deepEqual(p.methods(), ['init', 'destruct']);
    assert.equal(p.window[disabledKey], true);
    assert.equal(p.notice.hidden, true);
});

test('refusal still stops tracking for this page when storage is unavailable', () => {
    const p = page(null, { blockedStorage: true });
    p.reject.click();
    p.finishLoad();
    assert.equal(p.window[disabledKey], true);
    assert.equal(p.notice.hidden, true);
    assert.deepEqual(p.methods(), []);
});

test('a failed download can be retried after an explicit choice', () => {
    const p = page();
    p.scripts[0].onerror();
    p.accept.click();
    assert.equal(p.scripts.length, 2);
    p.finishLoad();
    assert.deepEqual(p.methods(), ['init']);
});
