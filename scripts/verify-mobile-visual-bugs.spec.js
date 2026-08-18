const http = require('node:http');
const { chromium } = require('playwright');

const baseUrl = new URL((process.env.THEOBROMA_URL || 'http://localhost:8080').replace(/\/$/, ''));
const snapshots = new Map();

function fetchText(path) {
  return new Promise((resolve, reject) => {
    const request = http.get({
      hostname: baseUrl.hostname,
      port: baseUrl.port || 80,
      path,
      headers: { Host: 'localhost:8080' },
    }, (response) => {
      let body = '';
      response.setEncoding('utf8');
      response.on('data', (chunk) => { body += chunk; });
      response.on('end', () => resolve(body));
    });
    request.on('error', reject);
  });
}

async function snapshotFor(path) {
  if (!snapshots.has(path)) {
    const source = await fetchText(path);
    const stylesheets = Array.from(source.matchAll(/<link\b[^>]*rel=['"]stylesheet['"][^>]*href=['"]([^'"]+)/gi), (match) => match[1]);
    let css = '';
    for (const href of stylesheets) {
      const url = new URL(href, 'http://localhost:8080');
      if (url.hostname === 'localhost') css += `\n${await fetchText(`${url.pathname}${url.search}`)}`;
    }
    snapshots.set(path, {
      html: source.replace(/<link\b[^>]*rel=['"]stylesheet['"][^>]*>/gi, '').replace(/<script\b[\s\S]*?<\/script>/gi, ''),
      css,
    });
  }
  return snapshots.get(path);
}

async function pageFor(browser, path, width = 390) {
  const page = await browser.newPage({ viewport: { width, height: 1200 }, reducedMotion: 'reduce' });
  const snapshot = await snapshotFor(path);
  await page.setContent(snapshot.html, { waitUntil: 'domcontentloaded' });
  await page.addStyleTag({ content: snapshot.css });
  await page.evaluate(() => document.fonts.ready);
  return page;
}

function check(condition, message, failures) {
  if (!condition) failures.push(message);
}

async function run() {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const failures = [];
  try {
    const home = await pageFor(browser, '/', 390);
    const homeMetrics = await home.evaluate(() => {
      const rect = (element) => {
        const value = element.getBoundingClientRect();
        return { top: value.top, right: value.right, bottom: value.bottom, left: value.left, width: value.width, height: value.height };
      };
      const values = Array.from(document.querySelectorAll('.value'), (card) => ({ card: rect(card), icon: rect(card.querySelector('img')) }));
      const contact = document.querySelector('.contact-card');
      const controls = Array.from(document.querySelectorAll('.contact-card .form-grid > input, .contact-card .form-grid > textarea, .contact-card .phone-field'), rect);
      const contactRect = rect(contact);
      const mail = document.querySelector('.footer-mail:nth-of-type(6)');
      const mailCards = Array.from(document.querySelectorAll('.footer-mail'));
      const mailStyle = getComputedStyle(mail, '::before');
      const phoneText = document.querySelector('.footer-phones a:first-child');
      const footerContent = [phoneText, document.querySelector('.footer-address'), mail.querySelector('strong')].filter(Boolean).map((element) => {
        const range = document.createRange();
        range.selectNodeContents(element);
        return {
          left: range.getBoundingClientRect().left - element.closest('.footer-card, .footer-phones').getBoundingClientRect().left,
          textAlign: getComputedStyle(element).textAlign,
          fontSize: parseFloat(getComputedStyle(element).fontSize),
        };
      });
      const firstProduct = document.querySelector('.home-product-card');
      const secondProduct = document.querySelectorAll('.home-product-card')[1];
      const productGrid = document.querySelector('.home-product-grid');
      const gridStyle = getComputedStyle(productGrid);
      return {
        values,
        contactRect,
        controls,
        mail: {
          height: rect(mail).height,
          iconTop: mailStyle.top,
          iconRight: mailStyle.right,
          iconBottom: mailStyle.bottom,
          strongAlign: getComputedStyle(mail.querySelector('strong')).textAlign,
          firstGap: rect(mailCards[1]).top - rect(mailCards[0]).bottom,
        },
        footerContent,
        phonesUseText: Boolean(phoneText) && !document.querySelector('.footer-phones img'),
        catalog: {
          cardWidth: rect(firstProduct).width,
          firstTop: rect(firstProduct).top,
          secondTop: rect(secondProduct).top,
          availableWidth: rect(productGrid).width - parseFloat(gridStyle.paddingLeft) - parseFloat(gridStyle.paddingRight),
        },
        ctaBackground: getComputedStyle(document.querySelector('.home-hero__actions .home-button--primary')).backgroundColor,
      };
    });
    await home.close();

    check(homeMetrics.values.every(({ card, icon }) => icon.left >= card.left - 1 && icon.right <= card.right + 1 && icon.top >= card.top - 1 && icon.bottom <= card.bottom + 1), '1. Home value icons must stay inside their cards', failures);
    const contactInsets = homeMetrics.controls.map((control) => ({ left: control.left - homeMetrics.contactRect.left, right: homeMetrics.contactRect.right - control.right }));
    check(contactInsets.every(({ left, right }) => Math.abs(left) <= 2 && Math.abs(right) <= 2), '2. Contact fields must use the shared visible container', failures);
    check(parseFloat(homeMetrics.mail.iconBottom) <= 12 && parseFloat(homeMetrics.mail.iconRight) <= 12 && homeMetrics.mail.strongAlign === 'left' && homeMetrics.mail.height <= 112 && homeMetrics.mail.firstGap >= 12 && homeMetrics.mail.firstGap <= 24, '3. Mobile footer mail cards must be compact with a bottom-right icon', failures);
    const footerLefts = homeMetrics.footerContent.map(({ left }) => left);
    check(homeMetrics.phonesUseText && homeMetrics.footerContent.length === 3 && homeMetrics.footerContent.every(({ textAlign }) => textAlign === 'left') && Math.max(...footerLefts) - Math.min(...footerLefts) <= 2 && homeMetrics.footerContent[0].fontSize === homeMetrics.footerContent[2].fontSize, '3a. Mobile footer card content must share one left-aligned typography grid', failures);
    check(homeMetrics.catalog.cardWidth >= homeMetrics.catalog.availableWidth - 2 && homeMetrics.catalog.secondTop > homeMetrics.catalog.firstTop, '6. Mobile homepage catalog must show one product per row', failures);
    check(homeMetrics.ctaBackground === 'rgb(176, 144, 61)', '7. Primary home CTA must use the brand gold color', failures);

    const wideMobile = await pageFor(browser, '/', 509);
    const wideMetrics = await wideMobile.evaluate(() => {
      const bounds = (element) => element.getBoundingClientRect();
      const contact = bounds(document.querySelector('.contact-card'));
      const controls = Array.from(document.querySelectorAll('.contact-card .form-grid > input, .contact-card .form-grid > textarea, .contact-card .phone-field'), (element) => {
        const control = bounds(element);
        return { left: control.left - contact.left, right: contact.right - control.right };
      });
      const mail = document.querySelector('.footer-mail:nth-of-type(6)');
      const mailIcon = getComputedStyle(mail, '::before');
      const grid = document.querySelector('.home-product-grid');
      const gridStyle = getComputedStyle(grid);
      const products = document.querySelectorAll('.home-product-card');
      return {
        controls,
        mailHeight: bounds(mail).height,
        mailIconBottom: parseFloat(mailIcon.bottom),
        mailIconRight: parseFloat(mailIcon.right),
        catalogCardWidth: bounds(products[0]).width,
        catalogFirstTop: bounds(products[0]).top,
        catalogSecondTop: bounds(products[1]).top,
        catalogAvailableWidth: bounds(grid).width - parseFloat(gridStyle.paddingLeft) - parseFloat(gridStyle.paddingRight),
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      };
    });
    await wideMobile.close();
    const wideContactLeft = Math.min(...wideMetrics.controls.map(({ left }) => left));
    const wideContactRight = Math.min(...wideMetrics.controls.map(({ right }) => right));
    check(Math.abs(wideContactLeft) <= 2 && Math.abs(wideContactRight) <= 2, '2. Contact fields must stay on the shared visible container at 509px', failures);
    check(wideMetrics.mailHeight <= 112 && wideMetrics.mailIconBottom <= 12 && wideMetrics.mailIconRight <= 12, '3. Footer cards must stay compact at 509px', failures);
    check(wideMetrics.catalogCardWidth >= wideMetrics.catalogAvailableWidth - 2 && wideMetrics.catalogSecondTop > wideMetrics.catalogFirstTop, '6. Homepage catalog must show one product per row at 509px', failures);
    check(!wideMetrics.overflow, 'Mobile homepage must not overflow horizontally at 509px', failures);

    const transition = await pageFor(browser, '/', 521);
    const transitionMetrics = await transition.evaluate(() => {
      const viewportWidth = document.documentElement.clientWidth;
      const rect = (selector) => document.querySelector(selector).getBoundingClientRect();
      const contained = (element) => {
        const box = element.getBoundingClientRect();
        return box.left >= -1 && box.right <= viewportWidth + 1;
      };
      const contact = rect('.contact-card');
      const products = Array.from(document.querySelectorAll('.home-product-card'));
      return {
        aboutContained: [document.querySelector('.story'), ...document.querySelectorAll('.value')].every(contained),
        contactHeadingContained: contained(document.querySelector('.contact-card h2')),
        contactControlsContained: Array.from(document.querySelectorAll('.contact-card .form-grid > input, .contact-card .form-grid > textarea, .contact-card .phone-field')).every((element) => {
          const box = element.getBoundingClientRect();
          return box.left >= contact.left - 1 && box.right <= contact.right + 1;
        }),
        catalogCardWidth: products[0].getBoundingClientRect().width,
        firstCardLeft: products[0].getBoundingClientRect().left,
        firstCardRight: products[0].getBoundingClientRect().right,
        secondCardLeft: products[1].getBoundingClientRect().left,
        firstCardTop: products[0].getBoundingClientRect().top,
        secondCardTop: products[1].getBoundingClientRect().top,
        viewportWidth,
      };
    });
    await transition.close();
    check(transitionMetrics.aboutContained, '8. The complete about composition must fit at 521px', failures);
    check(transitionMetrics.contactHeadingContained && transitionMetrics.contactControlsContained, '8. Contact heading and fields must fit at 521px', failures);
    check(transitionMetrics.catalogCardWidth >= transitionMetrics.viewportWidth - 2 * 18 - 2 && transitionMetrics.firstCardLeft >= -1 && transitionMetrics.firstCardRight <= transitionMetrics.viewportWidth + 1 && transitionMetrics.secondCardTop > transitionMetrics.firstCardTop, '8. Homepage catalog must show one product per row at 521px', failures);

    const cooperation = await pageFor(browser, '/cooperation/', 390);
    const benefitMetrics = await cooperation.evaluate(() => Array.from(document.querySelectorAll('.cooperation-benefit-grid article'), (card) => {
      const heading = card.querySelector('h3');
      const range = document.createRange();
      range.selectNodeContents(heading);
      const headingRect = range.getBoundingClientRect();
      const iconRect = card.querySelector('img').getBoundingClientRect();
      return { headingRight: headingRect.right, iconLeft: iconRect.left, iconWidth: iconRect.width, paddingRight: parseFloat(getComputedStyle(heading).paddingRight) };
    }));
    await cooperation.close();
    check(benefitMetrics.every(({ headingRight, iconLeft, iconWidth, paddingRight }) => headingRight <= iconLeft - 8 && paddingRight >= iconWidth + 8), '4. Cooperation headings must reserve clear space for their icons', failures);

    const buy = await pageFor(browser, '/buy/', 390);
    const buyMetrics = await buy.evaluate(() => {
      document.querySelectorAll('.buy-panel').forEach((panel) => { panel.hidden = panel.id !== 'bulletcities3'; });
      const decor = document.querySelector('.buy-decor-right');
      const decorRect = decor.getBoundingClientRect();
      const overlaps = Array.from(document.querySelectorAll('#bulletcities3 .buy-partner-card')).some((card) => {
        const cardRect = card.getBoundingClientRect();
        return decorRect.left < cardRect.right && decorRect.right > cardRect.left && decorRect.top < cardRect.bottom && decorRect.bottom > cardRect.top;
      });
      return { display: getComputedStyle(decor).display, overlaps };
    });
    await buy.close();
    check(buyMetrics.display === 'none' || !buyMetrics.overlaps, '5. Buy-page decoration must not cover partner cards', failures);

    if (failures.length) throw new Error(`Mobile visual regressions:\n- ${failures.join('\n- ')}`);
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
