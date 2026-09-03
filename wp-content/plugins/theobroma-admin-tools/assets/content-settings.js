(() => {
  const root = document.querySelector('[data-cacao-profiles]');
  if (!root) return;

  const updateImage = (container, url) => {
    const input = container.querySelector('[data-cacao-image-url]');
    const preview = container.querySelector('[data-cacao-image-preview]');
    input.value = url;
    preview.hidden = !url;
    if (url) preview.src = url;
    else preview.removeAttribute('src');
  };

  root.addEventListener('input', (event) => {
    if (event.target.matches('[data-cacao-image-url]')) {
      updateImage(event.target.closest('[data-cacao-image]'), event.target.value);
    }
  });

  root.addEventListener('click', (event) => {
    const imageButton = event.target.closest('[data-select-cacao-image], [data-clear-cacao-image]');
    if (imageButton) {
      const container = imageButton.closest('[data-cacao-image]');
      if (imageButton.matches('[data-clear-cacao-image]')) {
        updateImage(container, '');
        container.querySelector('[data-cacao-image-url]').focus();
        return;
      }
      if (!window.wp?.media) return;
      const frame = window.wp.media({
        title: 'Изображение для процента какао',
        button: { text: 'Использовать изображение' },
        library: { type: 'image' },
        multiple: false,
      });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        updateImage(container, attachment.sizes?.large?.url || attachment.url);
        imageButton.focus();
      });
      frame.open();
      return;
    }
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
