(function () {
  'use strict';

  const selector = document.querySelector('.home-cacao');

  if (selector) {
    const tabs = Array.from(selector.querySelectorAll('[data-cacao-option]'));
    const panel = selector.querySelector('[data-cacao-panel]');
    const image = panel ? panel.querySelector('img') : null;
    const title = panel ? panel.querySelector('[data-cacao-title]') : null;
    const description = panel ? panel.querySelector('[data-cacao-description]') : null;
    const fact = panel ? panel.querySelector('[data-cacao-fact]') : null;
    const price = panel ? panel.querySelector('.home-cacao__buy strong') : null;
    const link = panel ? panel.querySelector('.home-cacao__buy a') : null;

    function revealSelectedTab(tab) {
      if (!tab || !window.matchMedia('(max-width: 800px)').matches) return;
      const rail = tab.parentElement;
      if (!rail) return;
      rail.scrollTo({
        left: tab.offsetLeft - (rail.clientWidth - tab.offsetWidth) / 2,
        behavior: 'auto',
      });
    }

    function selectTab(tab, focus) {
      if (!tab || !panel) return;

      tabs.forEach((item) => {
        const selected = item === tab;
        item.setAttribute('aria-selected', selected ? 'true' : 'false');
        item.tabIndex = selected ? 0 : -1;
      });

      panel.classList.add('is-changing');
      window.setTimeout(() => {
        if (image) {
          image.src = tab.dataset.image || '';
          image.alt = tab.dataset.imageAlt || '';
        }
        if (title) title.textContent = tab.dataset.title || '';
        if (description) description.textContent = tab.dataset.description || '';
        if (fact) fact.textContent = tab.dataset.fact || '';
        if (price) price.textContent = tab.dataset.price || '';
        if (link) link.href = tab.dataset.url || '#';
        panel.setAttribute('aria-labelledby', tab.id);
        panel.classList.remove('is-changing');
      }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 140);

      if (focus) tab.focus();
      revealSelectedTab(tab);
    }

    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => selectTab(tab, false));
      tab.addEventListener('keydown', (event) => {
        let targetIndex = index;
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') targetIndex = (index + 1) % tabs.length;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') targetIndex = (index - 1 + tabs.length) % tabs.length;
        if (event.key === 'Home') targetIndex = 0;
        if (event.key === 'End') targetIndex = tabs.length - 1;
        if (targetIndex === index) return;
        event.preventDefault();
        selectTab(tabs[targetIndex], true);
      });
    });

    window.requestAnimationFrame(() => revealSelectedTab(tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')));
  }

  function markAdded(button) {
    const node = button && button.jquery ? button.get(0) : button;
    if (!node || !node.matches('.home-product-card__button')) return;
    node.textContent = 'В корзине';
    node.classList.add('is-in-cart');
    node.setAttribute('aria-label', 'Товар добавлен в корзину');
  }

  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart', function (_event, _fragments, _hash, button) {
      markAdded(button);
    });
  }

  document.body.addEventListener('wc-blocks_added_to_cart', (event) => markAdded(event.target));
}());
