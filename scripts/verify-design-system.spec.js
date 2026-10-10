const fs = require('node:fs');
const path = require('node:path');
const { chromium, webkit } = require('playwright');
const base = process.env.THEOBROMA_URL || 'https://theobroma.uit-dev.ru';
const preview = process.env.DS_PREVIEW !== '0';
const engine = process.env.DS_ENGINE || 'chromium';
const widths = [320, 390, 430, 579, 600, 768, 820, 1024, 1199, 1200, 1440, 1920, 2560];
const routes = ['/', '/catalog/', '/product/theobroma-100-70/', '/buy/', '/recipes/', '/cooperation/', '/delivery/', '/my-account/', '/corporate-gifts/', '/chocolate-samples/', '/media/'];
const dir = path.join(__dirname, '../output/design-system', preview ? 'preview' : 'live', engine);
fs.mkdirSync(dir, { recursive:true });
const css = preview ? fs.readFileSync(path.join(__dirname, '../wp-content/themes/theobroma/assets/css/design-system.css'), 'utf8') : '';
(async () => {
  const browser = await (engine === 'webkit' ? webkit : chromium).launch(engine === 'webkit' ? {headless:true} : {channel:'chrome',headless:true});
  const page = await browser.newPage({ viewport:{width:1440,height:1000}, reducedMotion:'reduce' });
  const report = {engine,preview,checks:[],errors:[]};
  try {
    for (const route of routes) {
      const response = await page.goto(base + route, {waitUntil:'domcontentloaded'});
      if (!response || response.status() >= 400) { report.errors.push({route,status:response?.status()}); continue; }
      if (preview) await page.addStyleTag({content:css});
      await page.evaluate(() => document.fonts.ready);
      for (const width of widths) {
        await page.setViewportSize({width,height:1000});
        const metrics = await page.evaluate(() => {
          const visible = e => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden' && !e.closest('[hidden]');
          const headings = [...document.querySelectorAll('main h1,main h2,main h3')].filter(e=>visible(e)&&!e.classList.contains('screen-reader-text')).map(e=>{
            const r=e.getBoundingClientRect(),s=getComputedStyle(e);
            return {text:e.textContent.trim().slice(0,70),size:s.fontSize,line:s.lineHeight,left:r.left,right:r.right,clipped:e.scrollHeight>e.clientHeight+2&&s.overflow==='hidden'};
          });
          const buttons=[...document.querySelectorAll('main .button,main .home-button,main .home-product-card__button')].filter(visible).map(e=>{
            const r=e.getBoundingClientRect(),s=getComputedStyle(e);
            return {text:e.textContent.trim().slice(0,40),height:r.height,size:s.fontSize,bg:s.backgroundColor,color:s.color,radius:s.borderRadius};
          });
          return {overflow:document.documentElement.scrollWidth>innerWidth+1,headings,buttons};
        });
        const badHeadings=metrics.headings.filter(h=>h.left < -2 || h.right>width+2 || h.clipped);
        const badButtons=metrics.buttons.filter(b=>b.height<43);
        if (metrics.overflow || badHeadings.length || badButtons.length) report.errors.push({route,width,overflow:metrics.overflow,badHeadings,badButtons});
        report.checks.push({route,width,...metrics});
        if ([390,768,1440].includes(width)) {
          await page.screenshot({path:path.join(dir,(route.replaceAll('/','_')||'home')+width+'.png')});
        }
      }
      console.log(route + ': inspected ' + widths.length + ' widths');
    }
    fs.writeFileSync(path.join(dir,'report.json'),JSON.stringify(report,null,2));
    console.log(JSON.stringify({engine,preview,count:report.checks.length,errors:report.errors},null,2));
    if (report.errors.length) process.exitCode=1;
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
