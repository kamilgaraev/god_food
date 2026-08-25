(function () {
    'use strict';

    function updateOptions(row) {
        var type = row.querySelector('[data-custom-field-type]');
        var options = row.querySelector('[data-custom-field-options]');
        if (!type || !options) return;
        options.closest('td').hidden = type.value !== 'select';
    }

    function reindex(builder) {
        builder.querySelectorAll('[data-custom-field-row]').forEach(function (row, index) {
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/\[custom_fields\]\[[^\]]+\]/, '[custom_fields][' + index + ']');
            });
            updateOptions(row);
        });
    }

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
