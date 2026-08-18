(function ($) {
    'use strict';

    $(document).on('click', '.theobroma-add-row', function () {
        var repeater = $(this).closest('.theobroma-repeater');
        var rows = repeater.find('.theobroma-repeater-rows');
        var index = rows.children().length;
        rows.append(repeater.find('template').html().replaceAll('__INDEX__', String(index)));
    });

    $(document).on('click', '.theobroma-remove-row', function () {
        $(this).closest('.theobroma-repeater-row').remove();
    });

    $(document).on('click', '.theobroma-select-media', function () {
        var field = $(this).closest('.theobroma-media-field');
        var frame = wp.media({ title: 'Выберите изображение', button: { text: 'Использовать' }, multiple: false });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
            field.find('input[type="hidden"]').val(attachment.id);
            field.find('img').attr('src', url).prop('hidden', false);
            field.find('.theobroma-remove-media').prop('hidden', false);
        });
        frame.open();
    });

    $(document).on('click', '.theobroma-remove-media', function () {
        var field = $(this).closest('.theobroma-media-field');
        field.find('input[type="hidden"]').val('');
        field.find('img').attr('src', '').prop('hidden', true);
        $(this).prop('hidden', true);
    });

    function refreshProductPicker(picker) {
        var checkboxes = picker.find('[data-product-option] input[type="checkbox"]');
        var selected = checkboxes.filter(':checked');
        var limit = Number(picker.data('limit')) || 3;
        var isFull = selected.length >= limit;

        checkboxes.each(function () {
            var checkbox = $(this);
            var option = checkbox.closest('[data-product-option]');
            var isSelected = checkbox.is(':checked');
            checkbox.prop('disabled', isFull && !isSelected);
            option.toggleClass('is-selected', isSelected).toggleClass('is-disabled', isFull && !isSelected);
        });
        picker.find('.theobroma-product-picker-count')
            .toggleClass('is-full', isFull)
            .find('strong').text(String(selected.length));
    }

    $('[data-product-picker]').each(function () {
        refreshProductPicker($(this));
    });

    $(document).on('change', '[data-product-picker] input[type="checkbox"]', function () {
        refreshProductPicker($(this).closest('[data-product-picker]'));
    });

    $(document).on('input', '[data-product-search]', function () {
        var input = $(this);
        var picker = input.closest('[data-product-picker]');
        var query = input.val().toLocaleLowerCase().trim();
        var visible = 0;
        picker.find('[data-product-option]').each(function () {
            var option = $(this);
            var matches = !query || String(option.data('search')).toLocaleLowerCase().includes(query);
            option.prop('hidden', !matches);
            if (matches) {
                visible += 1;
            }
        });
        picker.find('[data-product-empty]').prop('hidden', visible !== 0);
    });
}(jQuery));
