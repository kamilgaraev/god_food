(function ($) {
  'use strict';

  var draftAmount = null;

  $(document.body).on('input', '#theobroma_bonus_amount', function () {
    draftAmount = $(this).val();
  });

  $(document.body).on('updated_checkout', function () {
    if (draftAmount !== null) {
      $('#theobroma_bonus_amount').val(draftAmount);
    }
  });

  function setStatus($section, message, isError) {
    $section.find('.theobroma-loyalty-status')
      .toggleClass('is-error', Boolean(isError))
      .text(message || '');
  }

  $(document.body).on('click', '[data-theobroma-bonus-apply]', function () {
    var $button = $(this);
    var $section = $button.closest('.theobroma-loyalty-checkout');
    var $input = $section.find('#theobroma_bonus_amount');

    $button.prop('disabled', true);
    setStatus($section, 'Пересчитываем итог…', false);

    $.post(theobromaLoyalty.ajaxUrl, {
      action: 'theobroma_set_bonus',
      nonce: theobromaLoyalty.nonce,
      amount: $input.val()
    }).done(function (response) {
      if (!response || !response.success) {
        setStatus($section, response && response.data ? response.data.message : 'Не удалось применить бонусы.', true);
        return;
      }
      draftAmount = null;
      $input.val(response.data.accepted);
      setStatus($section, response.data.message, false);
      $(document.body).trigger('update_checkout');
    }).fail(function (xhr) {
      var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Не удалось применить бонусы.';
      setStatus($section, message, true);
    }).always(function () {
      $button.prop('disabled', false);
    });
  });
})(jQuery);
