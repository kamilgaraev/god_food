const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const asset = (name) => pathToFileURL(path.join(root, 'wp-content/themes/theobroma/assets', name)).href;
(async () => {
  const browser = await chromium.launch(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : { channel: 'chrome' });
  try {
    const page = await browser.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
    const html = `<!doctype html><meta charset="utf-8"><style>
    @font-face{font-family:Cormorant;src:url('${asset('fonts/cormorant-400.ttf')}')}
    @font-face{font-family:Montserrat;src:url('${asset('fonts/montserrat-400.ttf')}')}
    *{box-sizing:border-box}body{margin:0;width:1200px;height:630px;overflow:hidden;background:#fbf7f1 url('${asset('images/hero-bg.webp')}') center/cover;color:#171411}
    main{height:100%;padding:58px 72px;border-top:8px solid #b0903d;position:relative}
    .logo{width:290px;height:auto}.copy{position:relative;z-index:1;width:620px}
    h1{font:400 76px/.95 Cormorant,Georgia,serif;margin:44px 0 22px}p{font:400 22px/1.5 Montserrat,Arial,sans-serif;margin:0;max-width:540px;color:#756b63}
    .pyramid{position:absolute;right:20px;bottom:0;width:440px;height:550px;object-fit:contain}
    .domain{margin-top:32px;color:#9b7d30;font-size:19px}
    </style><main><div class="copy"><img class="logo" src="${asset('images/logo.png')}" alt="Theobroma — Пища Богов"><h1>Абсолютно натуральный<br>шоколад</h1><p>Кусковый пористый шоколад.<br>Четыре ингредиента и ничего лишнего.</p><p class="domain">theobroma.one</p></div><img class="pyramid" src="${asset('images/hero-chocolate-mobile.webp')}" alt=""></main>`;
    const temp = path.join(root, 'output/customer-polish/social-preview.html');
    fs.mkdirSync(path.dirname(temp), { recursive: true }); fs.writeFileSync(temp, html);
    await page.goto(pathToFileURL(temp).href);
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => Promise.all([...document.images].map(image => image.decode())));
    const output = path.join(root, 'wp-content/themes/theobroma/assets/images/social-preview.jpg');
    await page.screenshot({ path: output, type: 'jpeg', quality: 92 });
    console.log(`Social preview: 1200×630, ${fs.statSync(output).size} bytes`);
  } finally { await browser.close(); }
})().catch(error => {console.error(error);process.exit(1)});
