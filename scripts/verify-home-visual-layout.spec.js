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
      { width: 440, height: 956 },
      { width: 390, height: 844 },
      { width: 320, height: 720 },
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

      if (viewport.width <= 800) {
        const compactHero = await box(page, '.home-hero');
        const heroTitle = await box(page, '.home-hero h1');
        const heroTrust = await box(page, '.home-hero__trust');
        const heroLead = await box(page, '.home-hero__lead');
        const heroActions = await box(page, '.home-hero__actions');
        const benefitStrip = await box(page, '.home-benefit-strip');
        if (compactHero) {
          assert(compactHero.height >= 420 && compactHero.height <= 450, 'mobile and tablet hero must keep the trust and product copy visually connected');
        }
        if (heroTitle && heroTrust) {
          assert(heroTrust.y >= heroTitle.y + heroTitle.height - 1, 'mobile trust metrics must follow the title');
        }
        if (heroTrust && heroLead) {
          assert(heroLead.y >= heroTrust.y + heroTrust.height, 'mobile product copy must follow trust metrics');
        }
        if (heroActions) {
          assert(heroActions.y + heroActions.height <= viewport.height, 'both mobile hero actions must be visible without scrolling');
        }
        if (compactHero && benefitStrip) {
          assert(Math.abs(benefitStrip.y - (compactHero.y + compactHero.height)) <= 2, 'benefit strip must immediately follow the mobile hero');
        }
      }

      if (viewport.width === 1200) {
        const catalogGrid = await box(page, '.home-product-grid');
        const heroTitle = await box(page, '.home-hero h1');
        const cacaoHeading = await box(page, '.home-cacao__selector h2');
        const cacaoSection = await box(page, '.home-cacao');
        const cacaoImage = await box(page, '.home-cacao__image-wrap');
        const cacaoCopy = await box(page, '.home-cacao__copy');
        const composition = await box(page, '.home-composition');
        const promoGrid = await box(page, '.home-promo-grid');
        const feature = await box(page, '.feature');
        const heroLeadCopy = await box(page, '.home-hero__lead > p');
        const heroActions = await box(page, '.home-hero__actions');

        const referenceContent = await page.evaluate(() => ({
          heroChocolateCount: document.querySelectorAll('.home-hero__chocolate').length,
          heroChocolatePreloadCount: document.querySelectorAll('link[rel="preload"][href*="hero-chocolate"]').length,
          heroCopy: document.querySelector('.home-hero__lead > p')?.textContent.trim(),
          secondCta: document.querySelector('.home-hero__actions a:nth-child(2)')?.textContent.trim(),
          trust: document.querySelector('.home-hero__trust')?.textContent.replace(/\s+/g, ' ').trim(),
          cacaoLabels: Array.from(document.querySelectorAll('.home-cacao__tabs button strong'), (node) => node.textContent.trim()),
          selectedCacao: document.querySelector('.home-cacao__tabs button[aria-selected="true"] strong')?.textContent.trim(),
        }));

        assert(referenceContent.heroChocolateCount === 0, 'reference hero must not contain a chocolate image');
        assert(referenceContent.heroChocolatePreloadCount === 0, 'removed hero chocolate must not retain a high-priority preload');
        assert(referenceContent.heroCopy === 'Четыре ингредиента. Пористая кусковая текстура, которой нет ни у одной плитки в магазине.', 'hero copy must match the reference');
        assert(referenceContent.secondCta === 'Подарочные наборы', 'secondary hero action must match the reference');
        assert(referenceContent.trust.includes('ГИ 35') && referenceContent.trust.includes('вместо 70'), 'hero trust block must include the reference glycemic comparison');
        assert(referenceContent.trust.includes('4,9') && referenceContent.trust.includes('1 200 отзывов'), 'hero trust block must include the reference rating');
        assert(JSON.stringify(referenceContent.cacaoLabels) === JSON.stringify(['55%', '72%', '85%', '92%', '99%']), 'cacao labels must match the reference scale');
        assert(referenceContent.selectedCacao === '72%', '72% must be selected by default in the reference scale');

        if (catalogGrid) {
          const rightMargin = viewport.width - catalogGrid.x - catalogGrid.width;
          assert(catalogGrid.x >= 40 && rightMargin >= 40, '1200px catalog grid must keep balanced page margins');
        }

        if (heroTitle) {
          const heroTitleGlyphs = await page.locator('.home-hero h1').evaluate((title) => {
            const range = document.createRange();
            range.selectNodeContents(title);
            const rect = range.getBoundingClientRect();
            return { width: rect.width };
          });
          assert(heroTitleGlyphs.width >= viewport.width * .88, '1200px hero title glyphs must span at least 88% of the viewport like the reference');
        }
        if (heroLeadCopy && heroActions) {
          const copyCenter = heroLeadCopy.y + heroLeadCopy.height / 2;
          const actionsCenter = heroActions.y + heroActions.height / 2;
          assert(Math.abs(copyCenter - actionsCenter) <= 20, 'hero copy and actions must share the reference bottom row');
        }
        if (cacaoHeading) {
          const headingStyle = await page.locator('.home-cacao__selector h2').evaluate((element) => {
            const style = getComputedStyle(element);
            return { lineHeight: parseFloat(style.lineHeight) };
          });
          assert(cacaoHeading.height >= headingStyle.lineHeight * 1.75, '1200px cacao heading must use the reference two-line measure');
          assert(cacaoHeading.height <= headingStyle.lineHeight * 2.15, '1200px cacao heading must stay within two lines');
        }
        if (cacaoImage && cacaoCopy) {
          assert(cacaoCopy.y >= cacaoImage.y + cacaoImage.height - 2, 'cacao copy must sit below the product circle like the reference');
          assert(Math.abs((cacaoCopy.x + cacaoCopy.width / 2) - (cacaoImage.x + cacaoImage.width / 2)) <= 24, 'cacao image and copy must share one centered column');
        }

        const sectionOrder = await page.evaluate(() => {
          const top = (selector) => document.querySelector(selector)?.getBoundingClientRect().top + window.scrollY;
          return {
            cacao: top('.home-cacao'),
            composition: top('.home-composition'),
            promo: top('.home-promo-grid'),
            feature: top('.feature'),
            promoCards: document.querySelectorAll('.home-promo-card').length,
          };
        });

        assert(sectionOrder.cacao < sectionOrder.composition, 'composition must follow the cacao selector');
        assert(sectionOrder.composition < sectionOrder.promo, 'promo cards must follow composition');
        assert(sectionOrder.promo < sectionOrder.feature, 'the legacy brand feature must come after the reference-visible promo cards');
        assert(sectionOrder.promoCards === 2, 'the reference-visible area must contain two promo cards');
        if (composition && promoGrid && feature) {
          assert(promoGrid.y >= composition.y + composition.height - 2, 'promo cards must start immediately after composition');
          assert(feature.y >= promoGrid.y + promoGrid.height - 2, 'brand feature must not interrupt the reference-visible section sequence');
          assert(composition.height >= 265 && composition.height <= 285, 'composition section must preserve the 270px reference rhythm');
          assert(promoGrid.height >= 325 && promoGrid.height <= 345, 'promo section must preserve the reference outer breathing room');
        }
        if (cacaoSection && composition && promoGrid) {
          assert(cacaoSection.height <= 660, '1200px cacao selector must stay compact enough to match the reference crop');
          assert(composition.height <= 290, '1200px composition block must match the compact reference height');
          assert(promoGrid.y - cacaoSection.y <= 950, 'promo cards must enter the 1222px reference crop below the cacao selector');
        }

        const referenceTypography = await page.evaluate(() => ({
          catalogTextTransform: getComputedStyle(document.querySelector('.home-section-heading h2')).textTransform,
          compositionTextTransform: getComputedStyle(document.querySelector('.home-composition h2')).textTransform,
          giftHeadingColor: getComputedStyle(document.querySelector('.home-promo-card--gift h2')).color,
        }));
        const catalogSection = await box(page, '.home-catalog');
        if (catalogSection) {
          assert(catalogSection.y >= 470 && catalogSection.y <= 500, '1200px catalog must begin at the same vertical rhythm as the reference');
          assert(catalogSection.height <= 620, '1200px catalog must end within the reference crop');
        }
        const catalogFooterDisplay = await page.locator('.home-catalog__footer').evaluate((element) => getComputedStyle(element).display);
        assert(catalogFooterDisplay === 'none', 'desktop catalog must not duplicate the hero catalog call to action');
        if (cacaoSection) {
          assert(cacaoSection.y <= 1120, 'the cacao selector must enter the first 1222px reference crop');
        }
        assert(referenceTypography.catalogTextTransform === 'none', 'catalog heading must preserve reference title case');
        assert(referenceTypography.compositionTextTransform === 'none', 'composition heading must preserve reference title case');
        assert(referenceTypography.giftHeadingColor !== 'rgb(0, 0, 0)', 'gift card heading must remain legible on the dark card');
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

        const heroActions = await page.locator('.home-hero__actions .home-button').evaluateAll((buttons) => buttons.map((button) => {
          const rect = button.getBoundingClientRect();
          return { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom };
        }));
        assert(heroActions.length === 2, 'mobile hero must render both calls to action');
        assert(heroActions.every((action) => action.left >= 0 && action.right <= viewport.width), 'both mobile hero calls to action must fit the viewport');
        assert(heroActions.every((action) => action.top >= 0 && action.bottom <= viewport.height), 'both mobile hero calls to action must be visible without scrolling');

        const selectedCacaoTab = await page.locator('.home-cacao__tabs button[aria-selected="true"]').boundingBox();
        assert(selectedCacaoTab && selectedCacaoTab.x >= 0 && selectedCacaoTab.x + selectedCacaoTab.width <= viewport.width, 'the selected mobile cacao tab must be fully visible on initial load');
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
