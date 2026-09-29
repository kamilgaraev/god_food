const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');
const base = process.env.THEOBROMA_URL || 'https://theobroma.one';
const schemas = async (page) => page.locator('script[type="application/ld+json"]').evaluateAll(nodes => nodes.flatMap(node => {
  const value = JSON.parse(node.textContent); return value['@graph'] || [value];
}));
(async () => {
  const browser = await chromium.launch({executablePath:process.env.CHROME_PATH,args:['--no-proxy-server','--disable-http2']});
  try {
    for (const width of [320,390,768,1440]) {
      const page = await browser.newPage({viewport:{width,height:1100},isMobile:width<600,hasTouch:width<600});
      await page.addInitScript(() => localStorage.setItem('theobroma_cookie_notice_accepted','1'));
      assert.equal((await page.goto(base, {waitUntil:'domcontentloaded'})).status(),200);
      await page.evaluate(() => document.fonts.ready);
      assert.match(await page.title(), /Натуральный пористый шоколад/);
      assert.match(await page.locator('.shipping').innerText(),/3000/);
      const docs = await schemas(page);
      assert.equal(docs.filter(doc=>doc['@type']==='Organization').length,1);
      const og = await page.locator('meta[property="og:image"]').getAttribute('content');
      assert(og.endsWith('/social-preview.jpg'));
      const button = page.locator('.home-gi__button');
      const tooltip = page.locator('#home-gi-help');
      await button.click(); assert(await tooltip.isVisible());
      const bounds = await tooltip.boundingBox();
      assert(bounds.x>=0 && bounds.x+bounds.width<=width+1,'Tooltip overflows viewport');
      await page.keyboard.press('Escape'); assert(!(await tooltip.isVisible()));
      await button.click(); assert(await tooltip.isVisible());
      await page.locator('.home-eyebrow').click(); assert(!(await tooltip.isVisible()));
      if(width>=768){
        await button.hover(); assert(await tooltip.isVisible());
        await page.locator('.home-eyebrow').hover(); assert(!(await tooltip.isVisible()));
        await button.focus(); assert(await tooltip.isVisible());
        await page.keyboard.press('Escape'); assert(!(await tooltip.isVisible()));
      }
      const labels = page.locator('.header-action-label');
      if(width<=600) for(const label of await labels.all()) assert(await label.isVisible());
      await page.screenshot({path:`output/customer-polish/home-final-${width}.png`});
      console.log(`${width}: home, logo, metadata, GI tap/hover/keyboard verified`);
      await page.close();
    }
    const page = await browser.newPage({viewport:{width:1440,height:1100}});
    const llms = await page.request.get(`${base}/llms.txt`);
    assert.equal(llms.status(),200); assert.match(llms.headers()['content-type'],/text\/plain/);
    assert.match(await llms.text(), /# Theobroma/);
    await page.goto(`${base}/corporate-gifts/`,{waitUntil:'domcontentloaded'});
    const faq = (await schemas(page)).filter(doc=>doc['@type']==='FAQPage');
    assert.equal(faq.length,1); assert.equal(faq[0].mainEntity.length,8);
    for(const question of faq[0].mainEntity) assert(await page.getByText(question.name,{exact:true}).count()>0);
    console.log('SEO: llms.txt, FAQ and social preview verified');
    await page.close();
    const audit = JSON.parse(fs.readFileSync('output/customer-polish/audit.json','utf8').replace(/^\uFEFF/,''));
    const added = audit.products.find(product=>product.id===337);
    for (const width of [390,1280]) {
      const productPage = await browser.newPage({viewport:{width,height:1100},deviceScaleFactor:2});
      await productPage.goto(added.url,{waitUntil:'domcontentloaded'});
      const image = productPage.locator('.commerce-modal-product [data-product-main-image]');
      await image.waitFor({state:'visible'}); await image.evaluate(image=>image.decode());
      const info = await image.evaluate(async image=>{
        const probe = new Image(); probe.src=image.currentSrc; await probe.decode();
        return {selected:image.currentSrc,original:image.dataset.productOriginalImage,srcset:image.srcset,pixels:probe.naturalWidth,display:image.getBoundingClientRect().width};
      });
      assert(info.srcset.includes('1121w')); assert(!info.selected.includes('-560x745'));
      assert(info.pixels>=Math.min(info.display*2,1121)-2,'Detail image undersized for Retina');
      assert.equal(await image.evaluate(image=>getComputedStyle(image).objectFit),'contain','The entire original photo must remain visible');
      assert.match(await productPage.locator('.commerce-modal-product .product-detail-price').innerText(),/299/);
      const productSchema = (await schemas(productPage)).filter(doc=>doc['@type']==='Product');
      assert.equal(productSchema.length,1); assert.equal(Number(productSchema[0].offers.price),299);
      await image.click();
      const lightbox = productPage.locator('[data-product-lightbox-image]');
      await lightbox.waitFor({state:'visible'}); await lightbox.evaluate(image=>image.decode());
      assert.equal(await lightbox.getAttribute('src'),info.original);
      await productPage.screenshot({path:`output/customer-polish/product-zoom-${width}.png`});
      await productPage.locator('.product-image-lightbox-close').click();
      await productPage.screenshot({path:`output/customer-polish/product-final-${width}.png`});
      console.log(`${width}@2x: product price, sharp photo and original zoom verified (${info.pixels}px for ${Math.round(info.display)}px)`);
      await productPage.close();
    }
    for(const width of [320,390,768]) {
      const email = await browser.newPage({viewport:{width,height:1000}});
      await email.goto(pathToFileURL(path.resolve('output/customer-polish/welcome-email.html')).href);
      await email.evaluate(()=>Promise.all([...document.images].map(image=>image.decode())));
      const overflow = await email.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);
      assert(!overflow,'Welcome email has horizontal overflow');
      assert(await email.getByText('Выбрать шоколад',{exact:true}).isVisible());
      const primary = email.getByText('Перейти в аккаунт',{exact:true});
      assert.equal(await primary.evaluate(link=>getComputedStyle(link).color),'rgb(255, 255, 255)','Primary CTA text must contrast with gold');
      await email.screenshot({path:`output/customer-polish/welcome-${width}.png`,fullPage:true});
      console.log(`${width}: welcome email layout verified`); await email.close();
    }
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
