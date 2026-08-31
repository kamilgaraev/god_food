const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';
const login = process.env.THEOBROMA_QA_LOGIN;
const password = process.env.THEOBROMA_QA_PASSWORD;
const widths = (process.env.THEOBROMA_QA_WIDTHS || '390,768,1440,2560')
  .split(',')
  .map(Number);
const routes = [
  { path: '/my-account/', marker: '.theobroma-account-dashboard' },
  { path: '/my-account/orders/', marker: '.woocommerce-orders-table, .woocommerce-info' },
  { path: '/my-account/bonuses/', marker: '.theobroma-bonuses' },
  { path: '/my-account/edit-address/', marker: '.theobroma-address-book' },
  { path: '/my-account/edit-address/billing/', marker: 'form h2, form h3, .woocommerce-address-fields' },
  { path: '/my-account/edit-address/shipping/', marker: 'form h2, form h3, .woocommerce-address-fields' },
  { path: '/my-account/edit-account/', marker: 'form.woocommerce-EditAccountForm' },
];
const outputRoot = path.resolve(__dirname, '..', 'output', 'playwright', 'account');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function slug(route) {
  return route.replace(/^\/+|\/+$/g, '').replaceAll('/', '-') || 'dashboard';
}

(async () => {
  assert(login && password, 'Set THEOBROMA_QA_LOGIN and THEOBROMA_QA_PASSWORD');
  fs.mkdirSync(outputRoot, { recursive: true });
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const authContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const authPage = await authContext.newPage();

  try {
    await authPage.goto(new URL('/my-account/', baseUrl).href, { waitUntil: 'networkidle' });
    const accountModal = authPage.locator('#account-modal');
    assert(await accountModal.getAttribute('aria-hidden') === 'false', 'Account modal did not open for a guest account page');
    await accountModal.locator('#account-email').fill(login);
    await accountModal.locator('[data-account-continue]').click();
    const loginForm = accountModal.locator('[data-account-login]');
    assert(await loginForm.isVisible(), 'Account modal did not switch to the login step');
    assert(await loginForm.locator('#account-login-email').inputValue() === login, 'Account modal did not carry the email into login');
    await loginForm.locator('#account-login-password').fill(password);
    await Promise.all([
      authPage.waitForLoadState('networkidle'),
      loginForm.locator('button[name="login"]').click(),
    ]);
    assert(!new URL(authPage.url()).pathname.includes('lost-password'), 'Login failed');
    assert(await authPage.locator('.woocommerce-MyAccount-navigation').count() === 1, 'Authenticated account navigation is missing');
    const storageState = await authContext.storageState();

    const results = [];
    for (const width of widths) {
      for (const route of routes) {
        const context = await browser.newContext({
          viewport: { width, height: width <= 430 ? 932 : width <= 768 ? 1024 : 1000 },
          storageState,
          reducedMotion: 'reduce',
        });
        const page = await context.newPage();
        const consoleErrors = [];
        const pageErrors = [];
        page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
        page.on('pageerror', (error) => pageErrors.push(error.message));

        const response = await page.goto(new URL(route.path, baseUrl).href, { waitUntil: 'networkidle' });
        const menu = await page.locator('.woocommerce-MyAccount-navigation a').allTextContents();
        const markerCount = await page.locator(route.marker).count();
        const metrics = await page.evaluate(() => ({
          viewportWidth: document.documentElement.clientWidth,
          scrollWidth: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth),
          heading: document.querySelector('h1, h2, h3')?.textContent?.replace(/\s+/g, ' ').trim() || '',
        }));
        const result = {
          width,
          route: route.path,
          status: response?.status() || null,
          menu: menu.map((item) => item.replace(/\s+/g, ' ').trim()),
          markerCount,
          consoleErrors,
          pageErrors,
          ...metrics,
        };
        results.push(result);
        await page.screenshot({
          path: path.join(outputRoot, `${width}-${slug(route.path)}.png`),
          fullPage: true,
          animations: 'disabled',
        });
        await context.close();

        assert(result.status === 200, `${width}px ${route.path}: HTTP ${result.status}`);
        assert(result.menu.length === 6, `${width}px ${route.path}: expected 6 account menu items, got ${result.menu.length}`);
        assert(result.markerCount > 0, `${width}px ${route.path}: content marker is missing`);
        assert(result.scrollWidth - result.viewportWidth <= 1, `${width}px ${route.path}: horizontal overflow ${result.scrollWidth - result.viewportWidth}px`);
        assert(result.consoleErrors.length === 0, `${width}px ${route.path}: console errors ${result.consoleErrors.join(' | ')}`);
        assert(result.pageErrors.length === 0, `${width}px ${route.path}: page errors ${result.pageErrors.join(' | ')}`);
        console.log(`PASS ${width}px ${route.path}`);
      }
    }

    fs.writeFileSync(path.join(outputRoot, 'report.json'), `${JSON.stringify(results, null, 2)}\n`);
  } finally {
    await authContext.close();
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
