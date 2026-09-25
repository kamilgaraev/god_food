const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');
const url = process.env.THEOBROMA_URL || 'https://theobroma.one/';
(async () => {
  const browser = await chromium.launch({ channel: 'chrome' });
  try {
    for (const width of (process.env.HERO_WIDTHS || "320,390,600,601,768,900,1024,1199,1200,1280,1366,1440,1600,1920,2560").split(",").map(Number)) {
      const page = await browser.newPage({viewport:{width,height:1100}});
      await page.addInitScript(() => localStorage.setItem('theobroma_cookie_notice_accepted','0'));
      await page.goto(url, {waitUntil:'domcontentloaded'});
      await page.evaluate(() => document.fonts.ready);
      if (process.env.HERO_PATCH) await page.evaluate(() => {
        const img = document.createElement('img');
        img.className = 'home-hero__mobile-art';
        img.src = '/wp-content/themes/theobroma/assets/images/hero-chocolate.png';
        img.width = 776;
        img.height = 970;
        img.alt = '';
        img.setAttribute('aria-hidden', 'true');
        document.querySelector('.home-eyebrow').after(img);
      });
      await page.locator('.home-hero__mobile-art').evaluate(img => img.decode());
      if (process.env.HERO_PATCH) await page.evaluate(css => {
        const style = document.createElement('style');
        style.textContent = css;
        const alignment = document.querySelector('link[href*="hero-alignment.css"]');
        if (alignment) alignment.before(style);
        else document.head.append(style);
      }, fs.readFileSync(process.env.HERO_PATCH,'utf8'));
      const boxes = await page.evaluate(() => {
        const box=s=>document.querySelector(s).getBoundingClientRect().toJSON();
        return {title:box('.home-eyebrow'),art:box('.home-hero__mobile-art'),actions:box('.home-hero__actions'),trust:box('.home-hero__trust'),hero:box('.home-hero'),scroll:document.documentElement.scrollWidth};
      });
      assert(boxes.scroll<=width+1,`${width}: horizontal overflow`);
      assert(boxes.title.right<=width,`${width}: headline clipped`);
      const gap=boxes.trust.top-boxes.actions.bottom;
      assert(gap>=20 && gap<=42,`${width}: excessive space below buttons: ${gap}`);
      assert(boxes.trust.bottom<=boxes.hero.bottom+1,`${width}: proof points outside hero`);
      assert(boxes.hero.bottom-boxes.trust.bottom <= 55, `${width}: excessive empty space below proof points: ${boxes.hero.bottom-boxes.trust.bottom}`);
      if (width <= 600) {
        assert(boxes.art.width >= 180 && boxes.art.height >= 225, `${width}: mobile artwork too small`);
        assert(boxes.art.top >= boxes.title.bottom, `${width}: artwork overlaps heading`);
        assert(boxes.art.bottom <= boxes.actions.top, `${width}: artwork overlaps actions`);
      } else {
        assert(boxes.art.width === 0, `${width}: mobile artwork duplicated on desktop`);
      }
      if(width>600){
        const png=PNG.sync.read(await page.screenshot());
        let top=-1;
        const left=Math.max(Math.ceil(boxes.title.right+25),Math.floor(width*.52));
        for(let y=Math.ceil(Math.max(boxes.hero.top+40,180));y<Math.min(png.height,boxes.hero.bottom) && top<0;y++){
          let dark=0;
          for(let x=left;x<Math.min(png.width,width*.9);x++){
            const i=(y*png.width+x)*4;
            if(png.data[i]<140 && png.data[i+1]<105 && png.data[i+2]<100)dark++;
          }
          if(dark>8)top=y;
        }
        assert(top>=0,`${width}: chocolate missing`);
        const delta=Math.abs(boxes.title.top-top);
        assert(delta<85,`${width}: headline/chocolate top mismatch: ${delta}`);
      }
      await page.screenshot({path:`output/playwright/hero-aligned-${width}.png`});
      console.log(`${width}: layout passed`);
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exit(1)});
