const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

async function assertHeroFits(page, viewportHeight) {
  const ctas = page.locator('.home-hero__actions a');
  assert((await ctas.count()) === 2, 'Hero must expose two CTA links');

  for (let index = 0; index < 2; index += 1) {
    const box = await ctas.nth(index).boundingBox();
    assert(box && box.y + box.height <= viewportHeight, `Hero CTA ${index + 1} must be visible without scrolling`);
  }
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    const desktop = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const response = await desktop.goto(BASE_URL, { waitUntil: 'networkidle' });
    assert(response && response.ok(), 'Homepage must return a successful response');
    assert(await desktop.locator('.home-hero h1').getByText('ШОКОЛАД', { exact: true }).isVisible(), 'Hero heading is missing');
    await assertHeroFits(desktop, 900);
    assert(await desktop.locator('.header-wishlist, [data-wishlist]').count() === 0, 'Wishlist must not be present in the header');
    assert(await desktop.locator('.home-catalog .home-product-card').count() === 4, 'Homepage catalog must contain four real products');

    const cartCount = desktop.locator('.header-cart .cart-count');
    const initialCartCount = Number(await cartCount.textContent());
    const firstAddButton = desktop.locator('.home-product-card__button.add_to_cart_button').first();
    await firstAddButton.click();
    await desktop.waitForFunction(() => document.querySelector('.home-product-card__button.added, .home-product-card__button.is-in-cart'));
    assert((await firstAddButton.textContent()).trim() === 'В корзине', 'Added product button must switch to “В корзине”');
    await desktop.waitForFunction((initial) => Number(document.querySelector('.header-cart .cart-count')?.textContent || 0) > initial, initialCartCount);
    assert((await desktop.locator('.header-cart').getAttribute('aria-label')).endsWith(String(initialCartCount + 1)), 'Cart accessible name must include the updated count');

    const navPosition = await desktop.locator('.nav').evaluate((node) => getComputedStyle(node).position);
    assert(navPosition === 'fixed', 'Desktop header must be fixed');

    const cacaoMapping = await desktop.locator('[data-cacao-option]').evaluateAll((options) => options.map((option) => ({
      actual: option.dataset.cacaoOption,
      visible: option.querySelector('strong')?.textContent.trim(),
      url: option.dataset.url,
    })));
    assert(JSON.stringify(cacaoMapping) === JSON.stringify([
      { actual: '59', visible: '59%', url: `${BASE_URL}/catalog/?cacao_percentage=59` },
      { actual: '65', visible: '65%', url: `${BASE_URL}/catalog/?cacao_percentage=65` },
      { actual: '68', visible: '68%', url: `${BASE_URL}/catalog/?cacao_percentage=68` },
      { actual: '70', visible: '70%', url: `${BASE_URL}/catalog/?cacao_percentage=70` },
      { actual: '80', visible: '80%', url: `${BASE_URL}/catalog/?cacao_percentage=80` },
    ]), 'Every selector percentage must match its WooCommerce product group and URL');

    const selected70 = desktop.locator('[data-cacao-option="70"]');
    assert(await selected70.getAttribute('aria-selected') === 'true', 'The 70% product group must be selected by default');
    assert((await selected70.locator('strong').textContent()).trim() === '70%', 'The default selector label must match the product percentage');
    await desktop.locator('[data-cacao-option="80"]').click();
    await desktop.waitForTimeout(400);
    assert(await desktop.locator('[data-cacao-option="80"]').getAttribute('aria-selected') === 'true', 'The 80% product group must become selected');
    assert((await desktop.locator('[data-cacao-title]').textContent()).includes('80%'), 'Selector content must update to the product percentage without a reload');
    const selectorUrl = await desktop.locator('.home-cacao__buy a').getAttribute('href');
    assert(selectorUrl && selectorUrl.includes('/catalog/?cacao_percentage=80'), 'Selector CTA must point to the filtered catalog');

    await desktop.locator('[data-cacao-option="80"]').focus();
    await desktop.keyboard.press('ArrowLeft');
    assert(await desktop.locator('[data-cacao-option="70"]').getAttribute('aria-selected') === 'true', 'Keyboard arrows must switch cacao options');

    const filtered = await desktop.goto(`${BASE_URL}/catalog/?cacao_percentage=80`, { waitUntil: 'networkidle' });
    assert(filtered && filtered.ok(), 'Filtered catalog must return a successful response');
    assert((await desktop.locator('.catalog-cacao-filter').textContent()).includes('80%'), 'Catalog must expose the active cacao filter');
    assert(await desktop.locator('.catalog-cacao-filter a').getAttribute('href') === `${BASE_URL}/catalog/`, 'Catalog must expose a reset link');
    const filteredTitles = await desktop.locator('.products .product h2, .products .product h3').allTextContents();
    assert(filteredTitles.length > 0, 'Filtered catalog must contain products');
    assert(filteredTitles.every((value) => value.trim().startsWith('80%')), 'Filtered catalog must contain only the selected cacao percentage');

    const unknown = await desktop.goto(`${BASE_URL}/catalog/?cacao_percentage=999`, { waitUntil: 'networkidle' });
    assert(unknown && unknown.ok(), 'Unknown cacao percentage must be ignored safely');

    const tablet = await browser.newPage({ viewport: { width: 768, height: 1024 } });
    await tablet.goto(BASE_URL, { waitUntil: 'networkidle' });
    const tabletCards = tablet.locator('.home-product-card');
    const tabletFirst = await tabletCards.nth(0).boundingBox();
    const tabletSecond = await tabletCards.nth(1).boundingBox();
    const tabletThird = await tabletCards.nth(2).boundingBox();
    assert(tabletFirst && tabletSecond && tabletThird && Math.abs(tabletFirst.y - tabletSecond.y) < 2 && tabletThird.y > tabletFirst.y, 'Tablet catalog must use a two-column grid');

    const mobile = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await mobile.goto(BASE_URL, { waitUntil: 'networkidle' });
    await assertHeroFits(mobile, 844);
    assert(await mobile.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), 'Mobile page must not overflow horizontally');
    await mobile.locator('.menu-toggle').focus();
    await mobile.keyboard.press('Enter');
    assert(await mobile.locator('#mobile-menu').getAttribute('aria-hidden') === 'false', 'Mobile menu must open from the keyboard');

    const reduced = await browser.newPage({
      viewport: { width: 1440, height: 900 },
      reducedMotion: 'reduce',
    });
    await reduced.goto(BASE_URL, { waitUntil: 'networkidle' });
    const duration = await reduced.locator('[data-cacao-panel]').evaluate((node) => getComputedStyle(node).transitionDuration);
    assert(duration === '0s' || duration === '0.001s', 'Reduced motion must disable selector transitions');

    const noScript = await browser.newPage({
      viewport: { width: 1440, height: 900 },
      javaScriptEnabled: false,
    });
    await noScript.goto(BASE_URL, { waitUntil: 'load' });
    const fallbackLinks = await noScript.locator('.home-cacao__noscript a').evaluateAll((links) => links.map((link) => ({
      text: link.textContent.trim(),
      href: link.href,
    })));
    assert(JSON.stringify(fallbackLinks) === JSON.stringify([
      { text: '59% — мягкий', href: `${BASE_URL}/catalog/?cacao_percentage=59` },
      { text: '65% — пряный', href: `${BASE_URL}/catalog/?cacao_percentage=65` },
      { text: '68% — характерный', href: `${BASE_URL}/catalog/?cacao_percentage=68` },
      { text: '70% — классический', href: `${BASE_URL}/catalog/?cacao_percentage=70` },
      { text: '80% — глубокий', href: `${BASE_URL}/catalog/?cacao_percentage=80` },
    ]), 'No-JavaScript fallback must expose every visible percentage with its real catalog URL');

    console.log('Homepage redesign contract verified');
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
