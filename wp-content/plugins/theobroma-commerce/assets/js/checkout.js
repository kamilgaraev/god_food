(function ($, window, document) {
  'use strict';

  var config = window.theobromaDelivery || {};
  var core = window.TheobromaDeliveryCore;
  var state = { provider: '', kind: 'pickup', points: [], selected: null, map: null, placemarks: null, suggestTimer: null };

  function dialog() { return document.querySelector('[data-delivery-dialog]'); }
  function field(name) { return document.querySelector('[data-delivery-field="' + name + '"]'); }
  function checkoutElement(selector) {
    var checkout = document.querySelector('.commerce-cart-checkout');
    return (checkout && checkout.querySelector(selector)) || document.querySelector(selector);
  }
  function checkoutValue(selector) {
    var element = checkoutElement(selector);
    return element ? element.value.trim() : '';
  }
  function checkoutCustomer() {
    return {
      country: checkoutValue('#billing_country') || 'RU',
      state: checkoutValue('#billing_state'),
      city: checkoutValue('#billing_city'),
      postcode: checkoutValue('#billing_postcode'),
      address: checkoutValue('#billing_address_1'),
      address_2: checkoutValue('#billing_address_2'),
      first_name: checkoutValue('#billing_first_name'),
      last_name: checkoutValue('#billing_last_name'),
      middle_name: checkoutValue('#billing_middle_name'),
      phone: checkoutValue('#billing_phone')
    };
  }
  function customer() {
    var details = checkoutCustomer();
    ['city', 'postcode', 'address', 'address_2'].forEach(function (name) {
      if (field(name) && field(name).value.trim()) details[name] = field(name).value.trim();
    });
    return details;
  }
  function setStatus(message, error) {
    var element = document.querySelector('[data-delivery-status]');
    if (!element) return;
    element.textContent = message || '';
    element.classList.toggle('is-error', Boolean(error));
  }
  function request(url, options) {
    options = options || {};
    options.credentials = 'same-origin';
    options.headers = Object.assign({}, options.headers || {});
    return fetch(url, options).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok) throw new Error(data.message || 'Сервис доставки временно недоступен.');
        return data;
      });
    });
  }
  function providerTitle() { return state.provider === 'ozon' ? 'Ozon Доставка' : 'СДЭК'; }
  function closeDialog() {
    var root = dialog();
    if (!root) return;
    if (typeof root.close === 'function' && root.open) root.close(); else root.removeAttribute('open');
  }

  function open(provider) {
    state.provider = provider;
    state.kind = 'pickup';
    state.selected = null;
    var root = dialog();
    if (!root) return;
    root.querySelector('[data-delivery-provider]').textContent = providerTitle();
    var initial = checkoutCustomer();
    ['city', 'postcode', 'address', 'address_2'].forEach(function (name) {
      if (field(name)) field(name).value = initial[name] || '';
    });
    switchKind('pickup');
    setStatus('');
    var search = root.querySelector('[data-delivery-search]');
    var clear = root.querySelector('[data-delivery-search-clear]');
    var pickupAddress = initial.address && initial.city
      && initial.address.toLocaleLowerCase('ru-RU').indexOf(initial.city.toLocaleLowerCase('ru-RU')) === -1
      ? initial.city + ', ' + initial.address
      : (initial.address || initial.city);
    if (search) search.value = pickupAddress;
    if (clear) clear.hidden = pickupAddress === '';
    renderSuggestions([]);
    if (typeof root.showModal === 'function') root.showModal(); else root.setAttribute('open', '');
    loadPointsForCheckoutAddress(pickupAddress);
  }

  function switchKind(kind) {
    state.kind = kind;
    document.querySelectorAll('[data-delivery-kind]').forEach(function (button) {
      button.setAttribute('aria-selected', button.dataset.deliveryKind === kind ? 'true' : 'false');
    });
    var pickup = document.querySelector('[data-delivery-pickup]');
    var courier = document.querySelector('[data-delivery-courier]');
    if (pickup) pickup.hidden = kind !== 'pickup';
    if (courier) courier.hidden = kind !== 'courier';
    setStatus(kind === 'pickup' && !state.selected ? 'Выберите удобный пункт выдачи.' : '');
  }

  function viewportQuery(viewport) {
    if (!viewport || !viewport.left_bottom || !viewport.right_top) return '';
    return '&left_bottom_lat=' + encodeURIComponent(viewport.left_bottom.lat)
      + '&left_bottom_long=' + encodeURIComponent(viewport.left_bottom.long)
      + '&right_top_lat=' + encodeURIComponent(viewport.right_top.lat)
      + '&right_top_long=' + encodeURIComponent(viewport.right_top.long);
  }

  function loadPoints(viewport) {
    var city = customer().city;
    var url = config.pointsUrl + '?provider=' + encodeURIComponent(state.provider) + '&city=' + encodeURIComponent(city) + viewportQuery(viewport);
    if (state.provider === 'cdek' && !city) {
      state.points = [];
      renderPoints([]);
      setStatus('Укажите город в оформлении заказа.', true);
      return;
    }
    setStatus('Загружаем пункты выдачи…');
    request(url).then(function (data) {
      state.points = Array.isArray(data.points) ? data.points : [];
      renderPoints(state.points);
      setStatus(state.points.length ? 'Выберите удобный пункт выдачи.' : 'Пункты выдачи не найдены.', !state.points.length);
      renderMap();
    }).catch(function (error) {
      state.points = [];
      renderPoints([]);
      setStatus(error.message, true);
    });
  }

  function loadPointsForCheckoutAddress(value) {
    if (state.provider !== 'ozon' || !config.suggestionsUrl || value.trim().length < 3) {
      loadPoints();
      return;
    }
    setStatus('Ищем пункты рядом с адресом…');
    request(config.suggestionsUrl + '?query=' + encodeURIComponent(value)).then(function (data) {
      var suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
      var first = suggestions[0] || null;
      renderSuggestions([]);
      loadPoints(first && first.viewport ? first.viewport : null);
    }).catch(function () {
      loadPoints();
    });
  }

  function renderSuggestions(items) {
    var list = document.querySelector('[data-delivery-suggestions]');
    if (!list) return;
    list.innerHTML = '';
    list.hidden = !items.length;
    items.forEach(function (item) {
      var button = document.createElement('button');
      button.type = 'button';
      button.setAttribute('role', 'option');
      button.textContent = item.label || '';
      button.addEventListener('click', function () {
        var search = document.querySelector('[data-delivery-search]');
        var clear = document.querySelector('[data-delivery-search-clear]');
        if (search) search.value = item.label || '';
        if (clear) clear.hidden = false;
        renderSuggestions([]);
        state.selected = null;
        loadPoints(item.viewport || null);
      });
      list.appendChild(button);
    });
  }

  function suggestAddress(value) {
    if (state.provider !== 'ozon' || !config.suggestionsUrl || value.trim().length < 3) {
      renderSuggestions([]);
      return;
    }
    var city = customer().city;
    var query = city && value.toLocaleLowerCase('ru-RU').indexOf(city.toLocaleLowerCase('ru-RU')) === -1
      ? city + ', ' + value
      : value;
    request(config.suggestionsUrl + '?query=' + encodeURIComponent(query)).then(function (data) {
      renderSuggestions(Array.isArray(data.suggestions) ? data.suggestions : []);
      if (data.configured === false) {
        setStatus('Подсказки адреса доступны после настройки HTTP Геокодера. Пункты можно выбрать из списка.', false);
      }
    }).catch(function () { renderSuggestions([]); });
  }

  function pointButton(point) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'theobroma-delivery-point';
    button.dataset.pointId = String(point.id || '');
    button.setAttribute('aria-pressed', state.selected && String(state.selected.id) === String(point.id) ? 'true' : 'false');
    var title = document.createElement('strong');
    title.textContent = point.name || providerTitle() + ' ПВЗ';
    var address = document.createElement('span');
    address.textContent = point.address || '';
    button.append(title, address);
    if (point.work_time) {
      var hours = document.createElement('small');
      hours.textContent = point.work_time;
      button.appendChild(hours);
    }
    button.addEventListener('click', function () { selectPoint(point); });
    return button;
  }
  function renderPoints(points) {
    var list = document.querySelector('[data-delivery-list]');
    if (!list) return;
    list.innerHTML = '';
    if (!points.length) {
      var empty = document.createElement('p');
      empty.className = 'theobroma-delivery-empty';
      empty.textContent = 'Измените запрос или проверьте город.';
      list.appendChild(empty);
      return;
    }
    points.forEach(function (point) { list.appendChild(pointButton(point)); });
  }
  function selectPoint(point) {
    state.selected = point;
    renderPoints(core.filterPoints(state.points, checkoutValue('[data-delivery-search]')));
    setStatus('Выбран: ' + (point.address || point.name || point.id));
    if (state.map && point.latitude && point.longitude) {
      state.map.setCenter([Number(point.latitude), Number(point.longitude)], 15, { duration: 250 });
    }
  }

  function renderMap() {
    var container = document.querySelector('[data-delivery-map]');
    if (!container || !core.canRenderMap(config) || !window.ymaps) {
      if (container) container.hidden = true;
      return;
    }
    container.hidden = false;
    window.ymaps.ready(function () {
      var points = state.points.filter(function (point) { return point.latitude && point.longitude; });
      if (!points.length) { container.hidden = true; return; }
      if (!state.map) {
        state.map = new window.ymaps.Map(container, { center: [Number(points[0].latitude), Number(points[0].longitude)], zoom: 11, controls: ['zoomControl'] });
        state.placemarks = new window.ymaps.GeoObjectCollection();
        state.map.geoObjects.add(state.placemarks);
      }
      state.placemarks.removeAll();
      points.forEach(function (point) {
        var marker = new window.ymaps.Placemark([Number(point.latitude), Number(point.longitude)], { hintContent: point.address || point.name });
        marker.events.add('click', function () { selectPoint(point); });
        state.placemarks.add(marker);
      });
      state.map.setBounds(state.placemarks.getBounds(), { checkZoomRange: true, zoomMargin: 32 });
    });
  }

  function syncAddressFieldVisibility() {
    var city = document.querySelector('#billing_city');
    if (!city) return;
    var reveal = city.value.trim() !== '';
    document.querySelectorAll('.theobroma-delivery-address').forEach(function (row) {
      row.hidden = !reveal;
      row.setAttribute('aria-hidden', reveal ? 'false' : 'true');
    });
  }

  function syncDeliveryPlacement() {
    var fields = document.querySelector('.commerce-cart-checkout .woocommerce-billing-fields__field-wrapper');
    var methods = document.querySelector('.commerce-cart-checkout .woocommerce-shipping-totals .woocommerce-shipping-methods');
    var table = document.querySelector('.commerce-cart-checkout .woocommerce-checkout-review-order-table');
    if (!fields || !methods || !table) return;

    var host = fields.querySelector('.theobroma-delivery-methods');
    if (!host) {
      host = document.createElement('div');
      host.className = 'theobroma-delivery-methods';
      host.setAttribute('aria-label', 'Способ доставки');
      fields.appendChild(host);
    }
    host.replaceChildren(methods);
    table.hidden = true;
  }

  function confirm() {
    if (state.kind === 'pickup' && !state.selected) {
      setStatus('Сначала выберите пункт выдачи.', true);
      return;
    }
    var details = customer();
    if (!details.phone) {
      setStatus('Укажите номер телефона в оформлении заказа.', true);
      var phone = checkoutElement('#billing_phone');
      if (phone) phone.focus();
      return;
    }
    if (state.kind === 'courier' && (!details.city || !details.address)) {
      setStatus('Укажите город и адрес доставки.', true);
      return;
    }
    var button = document.querySelector('[data-delivery-confirm]');
    button.disabled = true;
    setStatus('Рассчитываем стоимость…');
    request(config.quoteUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(core.buildQuotePayload(state.provider, state.kind, state.selected, details))
    }).then(function (data) {
      if (state.kind === 'courier') {
        [['#billing_city', details.city], ['#billing_postcode', details.postcode], ['#billing_address_1', details.address], ['#billing_address_2', details.address_2]].forEach(function (pair) {
          var target = document.querySelector(pair[0]);
          if (target) target.value = pair[1];
        });
      }
      setStatus((data.quote && data.quote.label ? data.quote.label : 'Доставка выбрана') + '. Обновляем заказ…');
      $(document.body).trigger('update_checkout');
      window.setTimeout(closeDialog, 350);
    }).catch(function (error) {
      setStatus(error.message, true);
    }).finally(function () { button.disabled = false; });
  }

  document.addEventListener('click', function (event) {
    if (event.target === dialog()) { closeDialog(); return; }
    if (event.target.closest('[data-delivery-close]')) { event.preventDefault(); closeDialog(); return; }
    var clear = event.target.closest('[data-delivery-search-clear]');
    if (clear) {
      event.preventDefault();
      var search = document.querySelector('[data-delivery-search]');
      if (search) {
        search.value = '';
        search.focus();
      }
      clear.hidden = true;
      renderSuggestions([]);
      renderPoints(state.points);
      return;
    }
    var opener = event.target.closest('[data-delivery-open]');
    if (opener) { event.preventDefault(); open(opener.dataset.deliveryOpen); return; }
    var tab = event.target.closest('[data-delivery-kind]');
    if (tab) { switchKind(tab.dataset.deliveryKind); return; }
    if (event.target.closest('[data-delivery-confirm]')) confirm();
  });

  document.addEventListener('input', function (event) {
    if (event.target && event.target.matches('#billing_city')) syncAddressFieldVisibility();
  });
  document.addEventListener('change', function (event) {
    if (event.target && event.target.matches('#billing_city')) syncAddressFieldVisibility();
  });
  var checkoutEvents = $(document.body);
  if (checkoutEvents && typeof checkoutEvents.on === 'function') {
    checkoutEvents.on('updated_checkout', syncAddressFieldVisibility);
    checkoutEvents.on('updated_checkout', syncDeliveryPlacement);
  }
  syncAddressFieldVisibility();
  syncDeliveryPlacement();
  document.addEventListener('input', function (event) {
    if (event.target.matches('[data-delivery-search]')) {
      var value = event.target.value;
      var clear = document.querySelector('[data-delivery-search-clear]');
      if (clear) clear.hidden = value === '';
      renderPoints(core.filterPoints(state.points, value));
      window.clearTimeout(state.suggestTimer);
      state.suggestTimer = window.setTimeout(function () { suggestAddress(value); }, 280);
    }
  });
})(jQuery, window, document);
