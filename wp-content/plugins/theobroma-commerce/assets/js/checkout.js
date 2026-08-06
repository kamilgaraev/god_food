(function ($) {
  'use strict';

  var loadedCity = '';

  function toggleDeliveryFields() {
    var selected = document.querySelector('input[name^="shipping_method"]:checked');
    var courier = selected && selected.value.indexOf('theobroma_cdek') !== -1 && selected.value.indexOf('courier') !== -1;
    document.querySelectorAll('.theobroma-delivery-address').forEach(function (field) {
      field.hidden = !courier;
      field.querySelectorAll('input').forEach(function (input) {
        input.disabled = !courier;
      });
    });
  }

  function loadPoints() {
    var select = document.querySelector('[data-cdek-points]');
    var cityField = document.querySelector('#billing_city');
    if (!select || !cityField || !window.theobromaDelivery) return;

    var city = cityField.value.trim();
    if (!city || city === loadedCity) return;
    loadedCity = city;
    select.disabled = true;
    select.innerHTML = '<option value="">Загрузка ПВЗ…</option>';

    fetch(window.theobromaDelivery.pointsUrl + '?city=' + encodeURIComponent(city), { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) throw new Error('CDEK points unavailable');
        return response.json();
      })
      .then(function (points) {
        select.innerHTML = '<option value="">Выберите ПВЗ</option>';
        points.forEach(function (point) {
          var option = document.createElement('option');
          option.value = point.code;
          option.textContent = point.address + (point.work_time ? ' — ' + point.work_time : '');
          select.appendChild(option);
        });
        select.disabled = false;
      })
      .catch(function () {
        select.innerHTML = '<option value="">ПВЗ временно недоступны</option>';
      });
  }

  $(document.body).on('updated_checkout', function () {
    loadPoints();
    toggleDeliveryFields();
  });
  $(document.body).on('change', 'input[name^="shipping_method"]', toggleDeliveryFields);
  $(function () {
    loadPoints();
    toggleDeliveryFields();
  });
})(jQuery);
