(() => {
  const root = document.querySelector('[data-cacao-profiles]');
  if (!root) return;

  root.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-add-cacao-profile]');
    if (addButton) {
      const template = root.querySelector('[data-cacao-profile-template]');
      const list = root.querySelector('[data-cacao-profile-list]');
      if (!template || !list) return;

      const index = Number.parseInt(root.dataset.nextIndex || '0', 10);
      list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
      root.dataset.nextIndex = String(index + 1);
      list.lastElementChild?.querySelector('input[type="number"]')?.focus();
      return;
    }

    const removeButton = event.target.closest('[data-remove-cacao-profile]');
    if (removeButton) {
      removeButton.closest('[data-cacao-profile-row]')?.remove();
    }
  });
})();
