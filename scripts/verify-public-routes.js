const baseUrl = process.env.THEOBROMA_LOCAL_URL || 'http://localhost:8080';

const redirects = {
  '/sample-page/': '/',
  '/%d0%bf%d1%80%d0%b8%d0%b2%d0%b5%d1%82-%d0%bc%d0%b8%d1%80/': '/',
  '/author/kamilgaraev/': '/',
  '/category/%d0%b1%d0%b5%d0%b7-%d1%80%d1%83%d0%b1%d1%80%d0%b8%d0%ba%d0%b8/': '/media/',
  '/offer/': '/oferta/',
  '/policy-2/': '/policy/',
  '/buy-old/': '/buy/',
};

(async () => {
  for (const [route, target] of Object.entries(redirects)) {
    const response = await fetch(new URL(route, baseUrl), { redirect: 'manual' });
    const location = response.headers.get('location');
    if (response.status !== 301) throw new Error(`${route}: expected 301, received ${response.status}`);
    if (new URL(location, baseUrl).pathname !== target) throw new Error(`${route}: expected ${target}, received ${location}`);
  }
  console.log(`Public route redirects verified: ${Object.keys(redirects).length}`);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
