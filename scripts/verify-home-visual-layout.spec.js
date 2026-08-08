const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const failures = [];

function assert(condition, message) {
  if (!condition) {
    failures.push(message);
  }
}

async function loadHomepage(browser, viewport) {
  const page = await browser.newPage({ viewport });
  await page.goto(BASE_URL, { waitUntil: 'networkidle' });
  await page.addStyleTag({ content: '.cookie-notice { display: none !important; }' });
  return page;
}

async function box(page, selector) {
  const element = page.locator(selector);
  if (!await element.count()) {
    failures.push(`${selector} is missing`);
    return null;
  }

  const value = await element.first().boundingBox();
  if (!value) {
    failures.push(`${selector} must have a layout box`);
    return null;
  }

  return value;
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });

  try {
    for (const viewport of [
      { width: 2295, height: 1119 },
      { width: 1440, height: 900 },
      { width: 1200, height: 1222 },
      { width: 1101, height: 1000 },
      { width: 768, height: 1024 },
      { width: 390, height: 844 },
    ]) {
      const page = await loadHomepage(browser, viewport);
      const { documentWidth, viewportWidth } = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
      }));

      assert(documentWidth === viewportWidth, 'document must not overflow horizontally');

      if (viewport.width === 1440) {
        const hero = await box(page, '.home-hero');
        await box(page, '.home-benefit-strip');
        const catalog = await box(page, '.home-catalog');

        if (hero) {
          assert(hero.height >= 400 && hero.height <= 500, 'desktop hero must stay within 400–500px');
        }
        if (catalog) {
          assert(catalog.y <= 570, 'catalog must enter the first 570px of the page');
        }
      }

      if (viewport.width >= 1200) {
        const cacaoCircle = await box(page, '.home-cacao__image-wrap');

        if (cacaoCircle) {
          assert(cacaoCircle.width >= 380, `${viewport.width}px cacao image circle must be at least 380px`);
          assert(cacaoCircle.width <= 420, `${viewport.width}px cacao image circle must not exceed 420px`);
        }
      }

      if (viewport.width === 1200) {
        const catalogGrid = await box(page, '.home-product-grid');

        if (catalogGrid) {
          const rightMargin = viewport.width - catalogGrid.x - catalogGrid.width;
          assert(catalogGrid.x >= 40 && rightMargin >= 40, '1200px catalog grid must keep balanced page margins');
        }
      }

      if (viewport.width === 390) {
        const heroTitleBounds = await page.locator('.home-hero h1').evaluate((title) => {
          const range = document.createRange();
          range.selectNodeContents(title);
          const rect = range.getBoundingClientRect();

          return { left: rect.left, right: rect.right };
        });

        assert(heroTitleBounds.left >= 0, 'mobile hero title glyphs must not be clipped on the left');
        assert(heroTitleBounds.right <= viewport.width, 'mobile hero title glyphs must not be clipped on the right');
      }

      await page.locator('.home-cacao').scrollIntoViewIfNeeded();
      await page.waitForTimeout(100);

      const stickyNav = await box(page, '.nav');
      const cacaoPanel = await box(page, '.home-cacao__panel');

      if (stickyNav) {
        assert(stickyNav.width <= viewport.width, `${viewport.width}px sticky header must fit the viewport`);
        assert(stickyNav.height <= 80, `${viewport.width}px sticky header must remain compact after scroll`);
      }

      if (cacaoPanel) {
        assert(cacaoPanel.x >= 0, `${viewport.width}px cacao panel must not be clipped on the left`);
        assert(cacaoPanel.x + cacaoPanel.width <= viewport.width, `${viewport.width}px cacao panel must not be clipped on the right`);
      }

      if (viewport.width <= 800) {
        const mobileHeaderCenters = await page.evaluate(() => {
          const centerY = (selector) => {
            const rect = document.querySelector(selector)?.getBoundingClientRect();
            return rect ? rect.top + rect.height / 2 : null;
          };

          return {
            brand: centerY('.nav .brand'),
            cart: centerY('.nav .header-cart'),
            menu: centerY('.nav .menu-toggle'),
          };
        });

        const centers = Object.values(mobileHeaderCenters);
        assert(centers.every(Number.isFinite), `${viewport.width}px mobile header controls must be present`);
        assert(Math.max(...centers) - Math.min(...centers) <= 8, `${viewport.width}px mobile header controls must stay on one row after scroll`);
      }

      const headerActionMetrics = await page.evaluate(() => {
        const metric = (selector) => {
          const element = document.querySelector(selector);
          const rect = element?.getBoundingClientRect();
          const style = element ? getComputedStyle(element) : null;
          const icon = element?.querySelector('img');

          return rect && style ? {
            width: rect.width,
            height: rect.height,
            background: style.backgroundColor,
            iconFilter: icon ? getComputedStyle(icon).filter : null,
          } : null;
        };

        return {
          account: metric('.header-account'),
          cart: metric('.header-cart'),
        };
      });

      if (viewport.width > 800) {
        assert(headerActionMetrics.account?.width >= 40, `${viewport.width}px account control must remain a visible 40px target`);
        assert(headerActionMetrics.account?.height >= 40, `${viewport.width}px account control must remain a visible 40px target`);
        assert(headerActionMetrics.account?.iconFilter !== 'none', `${viewport.width}px account icon must contrast with the light header`);
      }

      assert(headerActionMetrics.cart?.height >= 36, `${viewport.width}px cart control must remain a visible tap target`);
      assert(headerActionMetrics.cart?.background !== 'rgba(0, 0, 0, 0)', `${viewport.width}px cart control must retain its filled treatment`);

      if (viewport.width === 1101) {
        const stickyHeaderLayout = await page.evaluate(() => {
          const bounds = (selector) => {
            const rect = document.querySelector(selector)?.getBoundingClientRect();
            return rect ? { left: rect.left, right: rect.right } : null;
          };

          return {
            leftNav: bounds('.nav-links-study a:last-child'),
            brand: bounds('.nav .brand'),
            rightNav: bounds('.header-where'),
            where: bounds('.header-where'),
            cart: bounds('.header-cart'),
          };
        });

        assert(stickyHeaderLayout.leftNav?.right + 8 <= stickyHeaderLayout.brand?.left, '1101px sticky left navigation must not overlap the logo');
        assert(stickyHeaderLayout.brand?.right + 8 <= stickyHeaderLayout.rightNav?.left, '1101px sticky right navigation must not overlap the logo');
        assert(stickyHeaderLayout.where?.left < stickyHeaderLayout.cart?.left, '1101px sticky header must preserve where-to-buy then cart order');
      }

      if (viewport.width === 2295) {
        const wideGrid = await box(page, '.home-product-grid');
        const wideCard = await box(page, '.home-product-grid .home-product-card');
        await box(page, '.home-cacao');

        if (wideCard) {
          assert(wideCard.width <= 340, 'ultrawide product cards must not exceed 340px');
        }
        if (wideGrid) {
          assert(wideGrid.width <= 1440, 'catalog grid must be capped at 1440px');
        }
      }

      await page.close();
    }

    if (failures.length) {
      throw new Error(failures.join('\n'));
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
