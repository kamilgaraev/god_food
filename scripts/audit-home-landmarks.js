const { chromium } = require('playwright');

const sourceBase = process.env.THEOBROMA_SOURCE_URL || 'https://theobroma.one/';
const localBase = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080/';
const widths = (process.argv.find((argument) => argument.startsWith('--widths='))?.split('=')[1] || '390,430,768').split(',').map(Number);
const landmarks = [
  'ПРОДУКЦИЯ ПИЩА БОГОВ',
  'THEOBROMA — АБСОЛЮТНО НАТУРАЛЬНЫЙ ШОКОЛАД',
  'ПРИЗНАНИЕ ЭКСПЕРТОВ',
  'НАТУРАЛЬНЫЙ СОСТАВ',
  'БЕЗ БЕЛОГО САХАРА',
  'ОТЗЫВЫ О НАШИХ ПРОДУКТАХ',
  'ОСТАЛИСЬ ВОПРОСЫ?',
  'КАРТА САЙТА',
];

async function measure(browser, url, width) {
  const context = await browser.newContext({ viewport: { width, height: width <= 430 ? 932 : 1024 }, reducedMotion: 'reduce' });
  const page = await context.newPage();
  await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
  await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' });
  await page.evaluate(async () => {
    if (document.fonts?.ready) await document.fonts.ready;
    for (let y = 0; y < document.documentElement.scrollHeight; y += innerHeight * 0.8) {
      scrollTo(0, y);
      await new Promise((resolve) => setTimeout(resolve, 20));
    }
    scrollTo(0, 0);
  });
  const result = await page.evaluate((needles) => {
    const normalize = (value) => value.replace(/\s+/g, ' ').trim().toUpperCase();
    const values = {};
    for (const needle of needles) {
      const matches = [...document.querySelectorAll('h1,h2,h3,h4,p,div')].filter((element) => {
        const rect = element.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 && normalize(element.textContent) === needle;
      }).sort((first, second) => {
        const a = first.getBoundingClientRect();
        const b = second.getBoundingClientRect();
        return a.width * a.height - b.width * b.height;
      });
      if (!matches.length) {
        values[needle] = null;
        continue;
      }
      const element = matches[0];
      const rect = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      const section = element.closest('.r.t-rec,section,footer');
      const sectionRect = section?.getBoundingClientRect();
      values[needle] = {
        y: rect.y + scrollY,
        x: rect.x,
        width: rect.width,
        height: rect.height,
        fontSize: parseFloat(style.fontSize),
        lineHeight: parseFloat(style.lineHeight),
        section: sectionRect ? {
          y: sectionRect.y + scrollY,
          height: sectionRect.height,
          className: section.className,
          id: section.id,
        } : null,
      };
    }
    const blocks = [...document.querySelectorAll('.r.t-rec,main > section')].map((element) => {
      const rect = element.getBoundingClientRect();
      return {
        y: rect.y + scrollY,
        height: rect.height,
        id: element.id,
        className: element.className,
        text: normalize(element.textContent).slice(0, 80),
      };
    }).filter((block) => block.height > 20 && block.y >= 650 && block.y < 2300);
    return { height: document.documentElement.scrollHeight, landmarks: values, blocks };
  }, landmarks);
  await context.close();
  return result;
}

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    for (const width of widths) {
      const source = await measure(browser, sourceBase, width);
      const local = await measure(browser, localBase, width);
      console.log(JSON.stringify({ width, source, local }, null, 2));
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
