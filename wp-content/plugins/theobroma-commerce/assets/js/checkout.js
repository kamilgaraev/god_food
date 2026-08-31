(function ($, window, document) {
  'use strict';

  var config = window.theobromaDelivery || {};
  var core = window.TheobromaDeliveryCore;
  var state = { provider: '', kind: 'pickup', points: [], selected: null, map: null, placemarks: null };

  function dialog() { return document.querySelector('[data-delivery-dialog]'); }
  function field(name) { return document.querySelector('[data-delivery-field="' + name + '"]'); }
  function checkoutValue(selector) {
    var element = document.querySelector(selector);
    return element ? element.value.trim() : '';
  }
  function customer() {
    return {
      country: checkoutValue('#billing_country') || 'RU',
      state: checkoutValue('#billing_state'),
      city: (field('city') && field('city').value.trim()) || checkoutValue('#billing_city'),
      postcode: (field('postcode') && field('postcode').value.trim()) || checkoutValue('#billing_postcode'),
      address: (field('address') && field('address').value.trim()) || checkoutValue('#billing_address_1'),
      address_2: (field('address_2') && field('address_2').value.trim()) || checkoutValue('#billing_address_2'),
      first_name: checkoutValue('#billing_first_name'),
      last_name: checkoutValue('#billing_last_name'),
      middle_name: checkoutValue('#billing_middle_name'),
      phone: checkoutValue('#billing_phone')
    };
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
    options.headers = Object.assign({ 'X-Theobroma-Nonce': config.nonce || '' }, options.headers || {});
    return fetch(url, options).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok) throw new Error(data.message || 'Сервис доставки временно недоступен.');
        return data;
      });
    });
  }
  function providerTitle() { return state.provider === 'ozon' ? 'Ozon Доставка' : 'СДЭК'; }

  function open(provider) {
    state.provider = provider;
    state.kind = 'pickup';
    state.selected = null;
    var root = dialog();
    if (!root) return;
    root.querySelector('[data-delivery-provider]').textContent = providerTitle();
    var initial = customer();
    ['city', 'postcode', 'address', 'address_2'].forEach(function (name) {
      if (field(name)) field(name).value = initial[name] || '';
    });
    switchKind('pickup');
    setStatus('');
    if (typeof root.showModal === 'function') root.showModal(); else root.setAttribute('open', '');
    loadPoints();
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

  function loadPoints() {
    var city = customer().city;
    var url = config.pointsUrl + '?provider=' + encodeURIComponent(state.provider) + '&city=' + encodeURIComponent(city);
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

  function confirm() {
    if (state.kind === 'pickup' && !state.selected) {
      setStatus('Сначала выберите пункт выдачи.', true);
      return;
    }
    var details = customer();
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
      window.setTimeout(function () { if (dialog().open) dialog().close(); }, 350);
    }).catch(function (error) {
      setStatus(error.message, true);
    }).finally(function () { button.disabled = false; });
  }

  document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-delivery-open]');
    if (opener) { event.preventDefault(); open(opener.dataset.deliveryOpen); return; }
    var tab = event.target.closest('[data-delivery-kind]');
    if (tab) { switchKind(tab.dataset.deliveryKind); return; }
    if (event.target.closest('[data-delivery-confirm]')) confirm();
  });
  document.addEventListener('input', function (event) {
    if (event.target.matches('[data-delivery-search]')) renderPoints(core.filterPoints(state.points, event.target.value));
  });
})(jQuery, window, document);
