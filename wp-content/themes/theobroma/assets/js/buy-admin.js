(function ($) {
    'use strict';

    function renderEmptyPreview($preview) {
        $preview
            .removeClass('has-image')
            .empty()
            .append($('<span>', {
                class: 'dashicons dashicons-format-image',
                'aria-hidden': 'true'
            }));
    }

    function renderPreview($preview, url) {
        $preview
            .addClass('has-image')
            .empty()
            .append($('<img>', { src: url, alt: '' }));
    }

    $(function () {
        $('[data-image-field]').each(function () {
            var $field = $(this);
            var $id = $field.find('[data-image-id]');
            var $clear = $field.find('[data-image-clear]');
            var $preview = $field.find('[data-image-preview]');
            var $remove = $field.find('[data-image-remove]');
            var frame;

            $field.find('[data-image-select]').on('click', function (event) {
                event.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: (window.theobromaBuyAdmin || {}).title || 'Выберите изображение',
                    button: { text: (window.theobromaBuyAdmin || {}).button || 'Использовать изображение' },
                    library: { type: 'image' },
                    multiple: false
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = attachment.url;

                    if (attachment.sizes && attachment.sizes.medium) {
                        url = attachment.sizes.medium.url;
                    }
                    $id.val(attachment.id);
                    $clear.val('0');
                    renderPreview($preview, url);
                    $remove.prop('hidden', false);
                });

                frame.open();
            });

            $remove.on('click', function (event) {
                event.preventDefault();
                $id.val('0');
                $clear.val('1');
                renderEmptyPreview($preview);
                $remove.prop('hidden', true);
            });
        });
    });
}(jQuery));
