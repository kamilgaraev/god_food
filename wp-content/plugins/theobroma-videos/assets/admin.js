(() => {
    const root = document.querySelector('[data-video-admin]');
    if (!root) return;
    const form = root.querySelector('form');
    const status = root.querySelector('[data-admin-status]');
    let dirty = false;
    const markDirty = () => { dirty = true; };
    const renumber = (panel) => {
        panel.querySelectorAll('[data-video-item]').forEach((item, index) => {
            item.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/\[items\]\[[^\]]+\]/, `[items][${index}]`);
            });
            item.querySelector('[data-item-move="-1"]').disabled = index === 0;
            item.querySelector('[data-item-move="1"]').disabled = index === panel.querySelectorAll('[data-video-item]').length - 1;
        });
    };
    root.querySelectorAll('[data-admin-panel]').forEach(renumber);
    form.addEventListener('input', (event) => {
        markDirty();
        if (event.target.matches('[data-item-title]')) {
            event.target.closest('[data-video-item]').querySelector('[data-item-heading]').textContent = event.target.value || 'Новая запись';
        }
    });
    form.addEventListener('change', markDirty);
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
    root.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.matches('[data-admin-tab]')) {
            root.querySelectorAll('[data-admin-tab]').forEach((tab) => tab.setAttribute('aria-pressed', String(tab === button)));
            root.querySelectorAll('[data-admin-panel]').forEach((panel) => { panel.hidden = panel.dataset.adminPanel !== button.dataset.adminTab; });
            return;
        }
        const panel = button.closest('[data-admin-panel]');
        const item = button.closest('[data-video-item]');
        if (button.matches('[data-item-add]')) {
            const count = panel.querySelectorAll('[data-video-item]').length;
            if (count >= 30) { status.textContent = 'Можно добавить не более 30 записей в блок.'; return; }
            const template = root.querySelector(`[data-item-template="${panel.dataset.adminPanel}"]`);
            panel.querySelector('[data-video-items]').append(template.content.cloneNode(true));
            renumber(panel);
            panel.querySelector('[data-video-item]:last-child [data-item-title]').focus();
            markDirty();
        }
        if (!item) return;
        if (button.matches('[data-item-remove]')) {
            if (!window.confirm('Удалить запись из ленты? Файлы останутся в медиатеке.')) return;
            item.remove();
            renumber(panel);
            markDirty();
        }
        if (button.matches('[data-item-move]')) {
            const previous = item.previousElementSibling;
            const next = item.nextElementSibling;
            if (button.dataset.itemMove === '-1' && previous) previous.before(item);
            if (button.dataset.itemMove === '1' && next) next.after(item);
            renumber(panel);
            markDirty();
        }
        if (button.matches('[data-clear-poster]')) {
            item.querySelector('[data-poster-id]').value = '0';
            const preview = item.querySelector('[data-poster-preview]');
            preview.hidden = true;
            preview.removeAttribute('src');
            markDirty();
        }
        if (button.matches('[data-select-poster], [data-select-video]')) {
            const isPoster = button.matches('[data-select-poster]');
            const frame = wp.media({ title: isPoster ? 'Обложка видео' : 'Выберите видео', library: { type: isPoster ? 'image' : 'video' }, multiple: false });
            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                if (isPoster) {
                    item.querySelector('[data-poster-id]').value = attachment.id;
                    const preview = item.querySelector('[data-poster-preview]');
                    preview.src = attachment.sizes?.medium?.url || attachment.url;
                    preview.hidden = false;
                } else {
                    item.querySelector('[data-video-url]').value = attachment.url;
                }
                markDirty();
            });
            frame.open();
        }
    });
})();
