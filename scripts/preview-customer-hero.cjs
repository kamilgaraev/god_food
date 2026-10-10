const fs = require('node:fs');
const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({executablePath:process.env.CHROME_PATH,args:['--no-proxy-server','--disable-http2']});
  try {
    const css = fs.readFileSync('wp-content/themes/theobroma/assets/css/home-redesign.css','utf8').split('/* The mobile pyramid rests')[1];
    for (const width of [320,390,600]) {
      const page = await browser.newPage({viewport:{width,height:1100}});
      await page.addInitScript(() => localStorage.setItem('theobroma_cookie_notice_accepted','1'));
      await page.goto('https://theobroma.one/',{waitUntil:'domcontentloaded',timeout:60000});
      await page.evaluate(() => document.fonts.ready);
      await page.evaluate(() => {
        document.querySelector('.home-hero__trust').after(document.querySelector('.home-hero__mobile-art'));
      });
      await page.addStyleTag({content:'/* The mobile pyramid rests'+css});
      await page.locator('.home-hero__mobile-art img').evaluate(image=>image.decode());
      await page.screenshot({path:`output/customer-polish/mobile-preview-${width}.png`});
      console.log(`${width}: preview saved`); await page.close();
    }
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
