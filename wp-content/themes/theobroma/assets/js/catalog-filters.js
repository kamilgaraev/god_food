(() => {
  const catalogPage = document.querySelector('.catalog-page');
  if (!catalogPage) return;

  const catalogPaths = new Set([window.location.pathname]);
  let activeRequest = null;

  async function loadCatalog(url, { push = false, focusFilter = false } = {}) {
    const nextUrl = new URL(url, window.location.href);
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
      if (activeRequest !== request) return;

      catalogPage.className = nextCatalogPage.className;
      catalogPage.innerHTML = nextCatalogPage.innerHTML;
      catalogPaths.add(nextUrl.pathname);

      if (push) window.history.pushState({ theobromaCatalog: true }, '', nextUrl.href);
      if (focusFilter) catalogPage.querySelector('.catalog-filters .is-active')?.focus({ preventScroll: true });
      document.dispatchEvent(new CustomEvent('theobroma:catalog-updated'));
    } catch (error) {
      if (error.name !== 'AbortError') window.location.assign(nextUrl.href);
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
    loadCatalog(nextUrl.href, { push: true, focusFilter: true });
  });

  window.addEventListener('popstate', () => {
    if (catalogPaths.has(window.location.pathname)) loadCatalog(window.location.href);
  });
})();
