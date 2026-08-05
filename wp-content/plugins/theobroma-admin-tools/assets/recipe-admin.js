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
}(jQuery));
