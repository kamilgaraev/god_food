(() => {
    'use strict';

    const root = document.querySelector('[data-photo-admin]');
    if (!root) return;

    const tabs = [...root.querySelectorAll('[data-showcase-tab]')];
    const panels = [...root.querySelectorAll('[data-showcase-panel]')];

    const activateTab = (tab) => {
        const location = tab.dataset.showcaseTab;
        tabs.forEach((item) => {
            const active = item === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', String(active));
            item.tabIndex = active ? 0 : -1;
        });
        panels.forEach((panel) => { panel.hidden = panel.dataset.showcasePanel !== location; });
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const nextIndex = event.key === 'Home'
                ? 0
                : event.key === 'End'
                    ? tabs.length - 1
                    : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            tabs[nextIndex].focus();
            activateTab(tabs[nextIndex]);
        });
    });

    root.querySelectorAll('[data-photo-collection]').forEach((collection) => {
        const list = collection.querySelector('[data-photo-list]');
        const empty = collection.querySelector('[data-photo-empty]');
        const template = collection.querySelector('[data-photo-template]');
        const openButton = collection.querySelector('[data-open-media]');
        const maxPhotos = Number(collection.dataset.maxPhotos || 8);
        let dragged = null;

        const refresh = () => {
            const rows = [...list.querySelectorAll('[data-photo-row]')];
            rows.forEach((row, index) => {
                row.querySelector('[data-photo-number]').textContent = String(index + 1).padStart(2, '0');
                row.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/\[images]\[[^\]]+]\[(attachment_id|alt|caption)]$/, `[images][${index}][$1]`);
                });
                row.querySelector('[data-move-photo="up"]').disabled = index === 0;
                row.querySelector('[data-move-photo="down"]').disabled = index === rows.length - 1;
            });
            empty.hidden = rows.length !== 0;
            openButton.disabled = rows.length >= maxPhotos;
        };

        const move = (row, direction) => {
            const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) return;
            if (direction === 'up') list.insertBefore(row, sibling);
            else list.insertBefore(sibling, row);
            refresh();
            row.querySelector(`[data-move-photo="${direction}"]`).focus();
        };

        list.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-remove-photo]');
            if (remove) {
                remove.closest('[data-photo-row]').remove();
                refresh();
                return;
            }
            const mover = event.target.closest('[data-move-photo]');
            if (mover) move(mover.closest('[data-photo-row]'), mover.dataset.movePhoto);
        });

        list.addEventListener('dragstart', (event) => {
            dragged = event.target.closest('[data-photo-row]');
            if (!dragged) return;
            dragged.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
        });
        list.addEventListener('dragover', (event) => {
            if (!dragged) return;
            event.preventDefault();
            const target = event.target.closest('[data-photo-row]');
            if (!target || target === dragged) return;
            const box = target.getBoundingClientRect();
            list.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? target : target.nextSibling);
        });
        list.addEventListener('dragend', () => {
            if (dragged) dragged.classList.remove('is-dragging');
            dragged = null;
            refresh();
        });

        openButton.addEventListener('click', () => {
            if (!window.wp?.media) return;
            const frame = window.wp.media({
                title: 'Выберите фотографии',
                button: { text: 'Добавить в подборку' },
                library: { type: 'image' },
                multiple: true,
            });
            frame.on('select', () => {
                const existing = new Set([...list.querySelectorAll('[data-photo-row]')].map((row) => Number(row.dataset.attachmentId)));
                frame.state().get('selection').each((item) => {
                    if (list.children.length >= maxPhotos) return;
                    const attachment = item.toJSON();
                    if (existing.has(Number(attachment.id))) return;
                    const fragment = template.content.cloneNode(true);
                    const row = fragment.querySelector('[data-photo-row]');
                    const image = row.querySelector('img');
                    const preview = attachment.sizes?.medium?.url || attachment.sizes?.thumbnail?.url || attachment.url;
                    row.dataset.attachmentId = String(attachment.id);
                    row.querySelector('[data-photo-id]').value = String(attachment.id);
                    row.querySelector('input[name$="[alt]"]').value = attachment.alt || '';
                    image.src = preview;
                    image.alt = '';
                    existing.add(Number(attachment.id));
                    list.appendChild(fragment);
                });
                refresh();
            });
            frame.open();
        });

        refresh();
    });
})();
