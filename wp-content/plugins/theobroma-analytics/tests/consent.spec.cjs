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
const noticeKey = 'theobroma_cookie_notice_dismissed';
const disabledKey = 'disableYaCounter12345678';

function page(saved = null, { blockedStorage = false, dismissed = null } = {}) {
    const storage = new Map(saved === null ? [] : [[key, saved]]);
    if (dismissed !== null) storage.set(noticeKey, dismissed);
    const listeners = new Map();
    const scripts = [];
    const calls = [];
    let focused = null;
    function element() {
        const handlers = new Map();
        return {
            attributes: {},
            addEventListener(type, handler) { handlers.set(type, handler); },
            setAttribute(name, value) { this.attributes[name] = value; },
            focus() { focused = this; },
            click() { handlers.get('click')?.({ preventDefault() {} }); },
        };
    }
    const dismiss = element();
    const settings = element();
    const notice = {
        hidden: true,
        querySelector() { return dismiss; },
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
        window, scripts, calls, storage, dismiss, notice, settings,
        get focused() { return focused; },
        finishLoad() {
            const queued = window.ym?.a || [];
            window.ym = (...args) => calls.push(args);
            queued.forEach(args => window.ym(...args));
            scripts.at(-1).onload?.();
        },
        changeInAnotherTab(value, name = key) {
            if (value === null) storage.delete(name);
            else storage.set(name, value);
            window.dispatchEvent({ type: 'storage', key: name });
        },
        legacyChoice(choice) { window.dispatchEvent({ type: 'theobroma:cookie-consent', detail: { choice } }); },
        methods() { return calls.map(call => call[1]); },
    };
}

test('first visit starts analytics before the information notice is dismissed', () => {
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

test('dismissing the notice persists only acknowledgement, without changing analytics', () => {
    const p = page();
    p.dismiss.click();
    assert.equal(p.storage.get(noticeKey), '1');
    assert.equal(p.storage.has(key), false);
    assert.equal(p.notice.hidden, true);
    assert.equal(p.window[disabledKey], false);
    p.finishLoad();
    assert.deepEqual(p.methods(), ['init']);
    const next = page(null, { dismissed: p.storage.get(noticeKey) });
    assert.equal(next.notice.hidden, true);
    assert.equal(next.scripts.length, 1);
});

test('reading and dismissing the notice never overwrites a saved refusal', () => {
    const p = page('0');
    p.settings.click();
    p.dismiss.click();
    assert.equal(p.storage.get(key), '0');
    assert.equal(p.storage.get(noticeKey), '1');
    assert.equal(p.scripts.length, 0);
    assert.equal(p.window[disabledKey], true);
    assert.equal(page('0', { dismissed: '1' }).scripts.length, 0);
});

test('footer information link opens the notice and restores keyboard focus on dismissal', () => {
    const p = page(null, { dismissed: '1' });
    p.settings.click();
    assert.equal(p.notice.hidden, false);
    assert.equal(p.settings.attributes['aria-expanded'], 'true');
    assert.equal(p.focused, p.dismiss);
    p.dismiss.click();
    assert.equal(p.notice.hidden, true);
    assert.equal(p.settings.attributes['aria-expanded'], 'false');
    assert.equal(p.focused, p.settings);
    p.settings.click();
    assert.equal(p.notice.hidden, false);
});

test('repeated dismissal does not duplicate scripts or counters', () => {
    const p = page();
    p.dismiss.click();
    p.dismiss.click();
    assert.equal(p.scripts.length, 1);
    p.finishLoad();
    p.dismiss.click();
    assert.deepEqual(p.methods(), ['init']);
});

// Older open pages and external integrations may still emit consent events.
test('a legacy refusal while the tag downloads prevents later initialization', () => {
    const p = page();
    p.legacyChoice('0');
    p.finishLoad();
    assert.equal(p.window[disabledKey], true);
    assert.deepEqual(p.methods(), []);
});

test('legacy consent events can stop and re-enable an initialized counter', () => {
    const p = page();
    p.finishLoad();
    p.legacyChoice('0');
    p.legacyChoice('1');
    assert.equal(p.scripts.length, 1);
    assert.equal(p.window[disabledKey], false);
    assert.deepEqual(p.methods(), ['init', 'destruct', 'init']);
});

test('changing the choice during download initializes only the final allowed state', () => {
    const p = page();
    p.legacyChoice('0');
    p.legacyChoice('1');
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

test('acknowledgement in another tab hides the notice without stopping tracking', () => {
    const p = page();
    p.finishLoad();
    p.changeInAnotherTab('1', noticeKey);
    assert.equal(p.notice.hidden, true);
    assert.equal(p.storage.has(key), false);
    assert.deepEqual(p.methods(), ['init']);
});

test('the notice can be dismissed for this page when storage is unavailable', () => {
    const p = page(null, { blockedStorage: true });
    p.dismiss.click();
    p.finishLoad();
    assert.equal(p.window[disabledKey], false);
    assert.equal(p.notice.hidden, true);
    assert.deepEqual(p.methods(), ['init']);
});

test('a failed download can still be retried through the legacy integration', () => {
    const p = page();
    p.scripts[0].onerror();
    p.legacyChoice('1');
    assert.equal(p.scripts.length, 2);
    p.finishLoad();
    assert.deepEqual(p.methods(), ['init']);
});

test('the notice contains one acknowledgement button and no consent controls', () => {
    const footer = fs.readFileSync(path.join(__dirname, '../../../themes/theobroma/footer.php'), 'utf8');
    const notice = footer.slice(footer.indexOf('<aside class="cookie-notice"'), footer.indexOf('</aside>'));
    assert.equal((notice.match(/<button\b/g) || []).length, 1);
    assert.match(notice, /data-cookie-dismiss>Понятно<\/button>/);
    assert.doesNotMatch(footer, /data-cookie-choice|Только необходимые|Оставить аналитику|Настройки cookie|Вы можете отключить/);
    assert.match(footer, />О cookie<\/a>/);
});
