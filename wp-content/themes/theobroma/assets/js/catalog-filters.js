(() => {
  const catalogPage = document.querySelector('.catalog-page');
  if (!catalogPage) return;

  const catalogPaths = new Set([window.location.pathname]);
  let activeRequest = null;

  function syncActiveFilter(activeHref = null) {
    catalogPage.querySelectorAll('.catalog-filters a').forEach((link) => {
      const isActive = activeHref ? link.href === activeHref : link.classList.contains('is-active');
      link.classList.toggle('is-active', isActive);
      if (isActive) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
  }

  function animateProductsIn() {
    const products = catalogPage.querySelector('ul.products');
    if (!products) return;

    products.classList.add('is-filter-entering');
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => products.classList.remove('is-filter-entering'));
    });
  }

  syncActiveFilter();

  async function loadCatalog(url, { push = false, focusFilter = false } = {}) {
    const nextUrl = new URL(url, window.location.href);
    const startingUrl = window.location.href;
    activeRequest?.abort();

    const request = new AbortController();
    activeRequest = request;
    catalogPage.setAttribute('aria-busy', 'true');

    try {
      const response = await window.fetch(nextUrl.href, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: request.signal,
      });
      if (!response.ok) throw new Error(`Catalog request failed with ${response.status}`);

      const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
      const nextCatalogPage = responseDocument.querySelector('.catalog-page');
      if (!nextCatalogPage) throw new Error('Catalog response is missing .catalog-page');
      if (activeRequest !== request || window.location.href !== startingUrl) return;

      const currentFilters = catalogPage.querySelector('.catalog-filters');
      const nextFilters = nextCatalogPage.querySelector('.catalog-filters');
      const nextActiveHref = nextFilters?.querySelector('.is-active')?.href || nextUrl.href;
      syncActiveFilter(nextActiveHref);
      if (currentFilters && nextFilters) nextFilters.replaceWith(currentFilters);

      catalogPage.className = nextCatalogPage.className;
      catalogPage.replaceChildren(...nextCatalogPage.childNodes);
      animateProductsIn();
      catalogPaths.add(nextUrl.pathname);

      if (push) window.history.pushState({ theobromaCatalog: true }, '', nextUrl.href);
      if (focusFilter) catalogPage.querySelector('.catalog-filters .is-active')?.focus({ preventScroll: true });
      document.dispatchEvent(new CustomEvent('theobroma:catalog-updated'));
    } catch (error) {
      if (error.name !== 'AbortError' && window.location.href === startingUrl) window.location.assign(nextUrl.href);
    } finally {
      if (activeRequest === request) {
        activeRequest = null;
        catalogPage.removeAttribute('aria-busy');
      }
    }
  }

  catalogPage.addEventListener('click', (event) => {
    const link = event.target.closest('.catalog-filters a');
    if (
      !link ||
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey ||
      link.target === '_blank'
    ) return;

    const nextUrl = new URL(link.href, window.location.href);
    if (nextUrl.origin !== window.location.origin) return;

    event.preventDefault();
    if (nextUrl.href === window.location.href) return;
    syncActiveFilter(nextUrl.href);
    loadCatalog(nextUrl.href, { push: true, focusFilter: true });
  });

  window.addEventListener('popstate', () => {
    if (catalogPaths.has(window.location.pathname)) loadCatalog(window.location.href);
  });
})();
