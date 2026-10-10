(function () {
  'use strict';
  const endpoint = window.theobromaErrorEndpoint;
  if (!endpoint) return;
  let sent = 0;
  const seen = new Set();

  function report(kind, source, line) {
    if (sent >= 3) return;
    let path = '';
    try {
      const url = new URL(source, window.location.href);
      if (url.origin === window.location.origin) path = url.pathname.slice(0, 180);
    } catch (_) { /* Source may be unavailable for cross-origin scripts. */ }
    const payload = { kind, path, line: Number(line) || 0 };
    const key = JSON.stringify(payload);
    if (seen.has(key)) return;
    seen.add(key);
    sent++;
    const body = new Blob([key], { type: 'application/json' });
    if (navigator.sendBeacon && navigator.sendBeacon(endpoint, body)) return;
    fetch(endpoint, { method: 'POST', body: key, headers: { 'Content-Type': 'application/json' }, keepalive: true }).catch(() => {});
  }

  window.addEventListener('error', function (event) {
    if (!event.filename && !event.error) return;
    report('error', event.filename, event.lineno);
  });
  window.addEventListener('unhandledrejection', function () {
    report('rejection', '', 0);
  });
})();
