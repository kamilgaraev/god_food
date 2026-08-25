(function () {
    'use strict';

    function updateOptions(row) {
        var type = row.querySelector('[data-custom-field-type]');
        var options = row.querySelector('[data-custom-field-options-wrap]');
        if (!type || !options) return;
        options.hidden = type.value !== 'select';
    }

    function reindex(builder) {
        var rows = builder.querySelectorAll('[data-custom-field-row]');
        rows.forEach(function (row, index) {
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/\[custom_fields\]\[[^\]]+\]/, '[custom_fields][' + index + ']');
            });
            var number = row.querySelector('[data-custom-field-number]');
            if (number) number.textContent = String(index + 1);
            var up = row.querySelector('[data-move-custom-field="up"]');
            var down = row.querySelector('[data-move-custom-field="down"]');
            if (up) up.disabled = index === 0;
            if (down) down.disabled = index === rows.length - 1;
            updateOptions(row);
        });
        var empty = builder.querySelector('[data-custom-fields-empty]');
        if (empty) empty.hidden = rows.length > 0;
        var panel = builder.closest('[data-form-panel]');
        var count = panel && panel.querySelector('[data-custom-field-count]');
        if (count) count.textContent = String(rows.length);
    }

    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-form-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-form-panel]'));

    function activateTab(tab, moveFocus) {
        tabs.forEach(function (item) {
            var active = item === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });
        panels.forEach(function (panel) {
            panel.hidden = panel.dataset.formPanel !== tab.dataset.formTab;
        });
        if (moveFocus) tab.focus();
    }

    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { activateTab(tab, false); });
        tab.addEventListener('keydown', function (event) {
            var next = index;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            else return;
            event.preventDefault();
            activateTab(tabs[next], true);
        });
    });

    document.querySelectorAll('[data-standard-field]').forEach(function (field) {
        var enabled = field.querySelector('[data-field-enabled]');
        var required = field.querySelector('[data-field-required]');
        if (!enabled || !required) return;
        required.addEventListener('change', function () {
            if (required.checked) enabled.checked = true;
        });
        enabled.addEventListener('change', function () {
            if (!enabled.checked) required.checked = false;
        });
    });

    document.querySelectorAll('[data-custom-fields]').forEach(function (builder) {
        reindex(builder);

        builder.addEventListener('change', function (event) {
            if (event.target.matches('[data-custom-field-type]')) {
                updateOptions(event.target.closest('[data-custom-field-row]'));
            }
        });

        builder.addEventListener('click', function (event) {
            var add = event.target.closest('[data-add-custom-field]');
            if (add) {
                var template = builder.querySelector('[data-custom-field-template]');
                var list = builder.querySelector('[data-custom-fields-list]');
                if (template && list) {
                    list.appendChild(template.content.cloneNode(true));
                    reindex(builder);
                    var label = list.lastElementChild && list.lastElementChild.querySelector('input[type="text"]');
                    if (label) label.focus();
                }
                return;
            }

            var row = event.target.closest('[data-custom-field-row]');
            if (!row) return;
            if (event.target.closest('[data-remove-custom-field]')) {
                row.remove();
                reindex(builder);
                return;
            }
            var move = event.target.closest('[data-move-custom-field]');
            if (!move) return;
            if (move.dataset.moveCustomField === 'up' && row.previousElementSibling) {
                row.parentNode.insertBefore(row, row.previousElementSibling);
            } else if (move.dataset.moveCustomField === 'down' && row.nextElementSibling) {
                row.parentNode.insertBefore(row.nextElementSibling, row);
            }
            reindex(builder);
        });
    });
}());
