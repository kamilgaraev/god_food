(() => {
  'use strict';
  const root = document.querySelector('[data-cs-admin]');
  if (!root) return;
  root.querySelectorAll('[data-cs-section]').forEach(section => {
    const list = section.querySelector('[data-cs-list]');
    const add = section.querySelector('[data-cs-add]');
    const status = section.querySelector('[data-cs-status]');
    const refresh = () => {
      const rows = [...list.children];
      rows.forEach((row, index) => {
        row.querySelector('[data-cs-number]').textContent = `${index + 1}.`;
        row.querySelectorAll('[name]').forEach(input => {
          input.name = input.name.replace(/\[items\]\[[^\]]*\]/, `[items][${index}]`);
        });
        row.querySelector('[data-cs-move="up"]').disabled = index === 0;
        row.querySelector('[data-cs-move="down"]').disabled = index === rows.length - 1;
        row.querySelectorAll('[data-cs-move], [data-cs-remove], [data-cs-media], [data-cs-clear]').forEach(button => {
          button.setAttribute('aria-label', `${button.textContent.trim()} — карточка ${index + 1}`);
        });
      });
      add.disabled = rows.length >= 12;
    };
    add.addEventListener('click', () => {
      if (list.children.length >= 12) return;
      const fragment = section.querySelector('[data-cs-template]').content.cloneNode(true);
      const row = fragment.querySelector('[data-cs-row]');
      list.append(fragment);
      refresh();
      row.querySelector('input[type="text"]').focus();
      status.textContent = 'Карточка добавлена. Заполните название и сохраните изменения.';
    });
    list.addEventListener('click', event => {
      const button = event.target.closest('button');
      const row = button?.closest('[data-cs-row]');
      if (!row) return;
      if (button.hasAttribute('data-cs-remove')) {
        const next = row.nextElementSibling || row.previousElementSibling;
        row.remove();
        refresh();
        (next || add).focus();
        status.textContent = 'Карточка удалена. Сохраните изменения.';
      } else if (button.dataset.csMove) {
        const up = button.dataset.csMove === 'up';
        const sibling = up ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling) return;
        if (up) list.insertBefore(row, sibling);
        else list.insertBefore(sibling, row);
        refresh();
        row.focus();
        status.textContent = 'Порядок изменён. Сохраните изменения.';
      } else if (button.hasAttribute('data-cs-clear')) {
        row.querySelector('[data-cs-image]').value = '0';
        row.querySelector('[data-cs-asset]').value = '';
        row.querySelector('[data-cs-preview]').replaceChildren();
        row.querySelector('[data-cs-no-photo]').hidden = false;
      } else if (button.hasAttribute('data-cs-media') && window.wp?.media) {
        const frame = wp.media({ title: 'Фото примера подарка', button: { text: 'Использовать фото' }, library: { type: 'image' }, multiple: false });
        frame.on('select', () => {
          const photo = frame.state().get('selection').first().toJSON();
          row.querySelector('[data-cs-image]').value = String(photo.id);
          row.querySelector('[data-cs-asset]').value = '';
          const image = document.createElement('img');
          image.src = photo.sizes?.medium?.url || photo.url;
          image.alt = photo.alt || '';
          row.querySelector('[data-cs-preview]').replaceChildren(image);
          row.querySelector('[data-cs-no-photo]').hidden = true;
          const alt = row.querySelector('[name$="[alt]"]');
          if (!alt.value) alt.value = photo.alt || '';
        });
        frame.open();
      }
    });
    refresh();
  });
})();
