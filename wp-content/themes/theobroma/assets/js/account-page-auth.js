(() => {
  const auth = document.querySelector('[data-account-page-auth]');
  if (!auth) return;

  const views = [...auth.querySelectorAll('[data-account-page-view]')];

  const show = (name) => {
    views.forEach((view) => {
      view.hidden = view.dataset.accountPageView !== name;
    });

    auth.querySelectorAll('[data-account-page-show]').forEach((button) => {
      button.setAttribute('aria-expanded', String(button.dataset.accountPageShow === name));
    });

    const activeView = auth.querySelector(`[data-account-page-view="${name}"]`);
    activeView?.querySelector('input:not([type="hidden"])')?.focus();
  };

  auth.addEventListener('click', (event) => {
    const button = event.target.closest('[data-account-page-show]');
    if (!button || !auth.contains(button)) return;
    show(button.dataset.accountPageShow);
  });
})();
