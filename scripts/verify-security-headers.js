const assert = require('node:assert/strict');

const baseUrl = (process.env.THEOBROMA_BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const routes = ['/', '/catalog/', '/cart/', '/my-account/'];

async function main() {
  for (const route of routes) {
    const response = await fetch(`${baseUrl}${route}`, { redirect: 'manual' });
    assert.ok(response.status >= 200 && response.status < 400, `${route} returned ${response.status}`);
    assert.equal(response.headers.get('x-content-type-options'), 'nosniff', `${route} must prevent MIME sniffing`);
    assert.equal(response.headers.get('x-frame-options'), 'SAMEORIGIN', `${route} must prevent cross-origin framing`);
    assert.equal(response.headers.get('referrer-policy'), 'strict-origin-when-cross-origin', `${route} must limit referrer disclosure`);
    assert.equal(
      response.headers.get('permissions-policy'),
      'camera=(), microphone=(), geolocation=(), payment=(self)',
      `${route} must disable unused browser capabilities`,
    );
    if (baseUrl.startsWith('http://')) {
      assert.equal(response.headers.get('strict-transport-security'), null, `${route} must not send HSTS over HTTP`);
    }
  }
  console.log(`Security headers verified on ${routes.length} public and private routes.`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
