(function ($, window, document) {
  'use strict';

  var config = window.theobromaDelivery || {};
  var core = window.TheobromaDeliveryCore;
  var state = { provider: '', kind: 'pickup', points: [], selected: null, map: null, placemarks: null, suggestTimer: null, suggestView: null };

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
    ['country', 'city', 'postcode', 'address', 'address_2'].forEach(function (name) {
      if (field(name)) details[name] = field(name).value.trim();
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
  var courierSuggestions = [];
  var courierSearchTimer;
  var citySearchTimer;
  document.addEventListener('input', function (event) {
    if (!event.target.matches('[data-delivery-field="city"]')) return;
    state.selected = null;
    window.clearTimeout(citySearchTimer);
    citySearchTimer = window.setTimeout(function () { loadPointsForCheckoutAddress(customer().city); }, 450);
  });
  function suggestCourierAddress(value) {
    if (!config.suggestionsUrl || value.trim().length < 3) return;
    var query = (field('country').selectedOptions[0].textContent + ', ') + (field('city').value || '') + ', ' + value;
    request(config.suggestionsUrl + '?country=' + encodeURIComponent(customer().country) + '&query=' + encodeURIComponent(query)).then(function (data) {
      if (field('address').value !== value) return;
      courierSuggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
      var list = document.querySelector('#theobroma-courier-suggestions');
      if (!list) return;
      list.replaceChildren();
      courierSuggestions.forEach(function (item) {
        var option = document.createElement('option');
        option.value = item.label || item.address || '';
        list.appendChild(option);
      });
    }).catch(function () { /* Manual address entry stays available. */ });
  }
  function applyCourierAddress() {
    var match = courierSuggestions.find(function (item) { return (item.label || item.address) === field('address').value; });
    if (!match) return;
    if (match.city) field('city').value = match.city;
    if (match.postcode) field('postcode').value = match.postcode;
    if (match.address) field('address').value = match.address;
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
    ['country', 'city', 'postcode', 'address', 'address_2'].forEach(function (name) {
      if (field(name)) field(name).value = initial[name] || '';
    });
    field('country').disabled = false;
    if (!field('country').value && field('country').options.length) field('country').selectedIndex = 0;
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
    initNativeSuggestions();
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
    var url = config.pointsUrl + '?provider=' + encodeURIComponent(state.provider) + '&city=' + encodeURIComponent(city) + '&country=' + encodeURIComponent(customer().country) + viewportQuery(viewport);
    if (!city) {
      state.points = [];
      renderPoints([]);
      setStatus('Укажите город доставки.', true);
      return;
    }
    setStatus('Загружаем пункты выдачи…');
    var context = state.provider + ':' + customer().country + ':' + city;
    request(url).then(function (data) {
      if (context !== state.provider + ':' + customer().country + ':' + customer().city) return;
      state.points = Array.isArray(data.points) ? data.points : [];
      renderPoints(state.points);
      setStatus(state.points.length ? 'Выберите удобный пункт выдачи.' : 'Пункты выдачи не найдены.', !state.points.length);
      renderMap();
    }).catch(function (error) {
      if (context !== state.provider + ':' + customer().country + ':' + customer().city) return;
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
    var country = field('country').selectedOptions[0].textContent;
    var city = customer().city;
    var context = state.provider + ':' + customer().country + ':' + city;
    function current() { return context === state.provider + ':' + customer().country + ':' + customer().city; }
    function lookup(query) { return request(config.suggestionsUrl + '?country=' + encodeURIComponent(customer().country) + '&query=' + encodeURIComponent(query)); }
    lookup(country + ', ' + value).then(function (data) {
      if (!current()) return null;
      var items = Array.isArray(data.suggestions) ? data.suggestions : [];
      if (items.some(function (item) { return item.viewport; }) || !city || value.trim() === city.trim()) return data;
      return lookup(country + ', ' + city).then(function (fallback) {
        if (current()) {
          var search = document.querySelector('[data-delivery-search]');
          if (search) search.value = '';
        }
        return fallback;
      });
    }).then(function (data) {
      if (!current() || !data) return;
      var suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
      var first = suggestions.find(function (item) { return item.viewport; });
      renderSuggestions([]);
      if (first) { state.addressLocation = first; renderMap(); loadPoints(first.viewport); }
      else setStatus('Не удалось найти город. Уточните название.', true);
    }).catch(function () {
      if (current()) setStatus('Поиск адреса временно недоступен. Попробуйте ещё раз.', true);
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
        applyAddressSuggestion(item);
      });
      list.appendChild(button);
    });
  }

  function suggestAddress(value) {
    if (config.suggestEnabled || !config.suggestionsUrl || value.trim().length < 3) {
      renderSuggestions([]);
      return;
    }
    var city = customer().city;
    var query = city && value.toLocaleLowerCase('ru-RU').indexOf(city.toLocaleLowerCase('ru-RU')) === -1
      ? city + ', ' + value
      : value;
    query = (field('country').selectedOptions[0].textContent + ', ') + query;
    request(config.suggestionsUrl + '?country=' + encodeURIComponent(customer().country) + '&query=' + encodeURIComponent(query)).then(function (data) {
      renderSuggestions(Array.isArray(data.suggestions) ? data.suggestions : []);
      if (data.configured === false) {
        setStatus('Подсказки адреса доступны после настройки HTTP Геокодера. Пункты можно выбрать из списка.', false);
      }
    }).catch(function () { renderSuggestions([]); });
  }

  function suggestCity(value) {
    if (config.mapProvider !== 'osm' || !config.suggestionsUrl) return;
    var input = field('city');
    var list = document.getElementById('theobroma-city-options');
    if (!list) {
      list = document.createElement('div');
      list.id = 'theobroma-city-options';
      list.className = 'theobroma-delivery-suggestions';
      list.setAttribute('role', 'listbox');
      list.setAttribute('aria-label', 'Подсказки городов');
      list.style.top = '100%';
      input.parentNode.style.position = 'relative';
      input.setAttribute('aria-controls', list.id);
      input.setAttribute('aria-autocomplete', 'list');
      input.parentNode.appendChild(list);
      input.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown' && !list.hidden && list.firstChild) { event.preventDefault(); list.firstChild.focus(); }
        if (event.key === 'Escape') { list.hidden = true; event.stopPropagation(); }
      });
    }
    list.innerHTML = '';
    list.hidden = true;
    state.citySuggestions = [];
    if (value.trim().length < 2) return;
    var country = customer().country;
    request(config.suggestionsUrl + '?type=city&country=' + encodeURIComponent(country) + '&query=' + encodeURIComponent(value)).then(function (data) {
      if (input.value !== value || customer().country !== country) return;
      state.citySuggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
      list.hidden = !state.citySuggestions.length;
      state.citySuggestions.forEach(function (item) {
        var option = document.createElement('button');
        option.type = 'button';
        option.setAttribute('role', 'option');
        option.textContent = item.label;
        option.addEventListener('click', function () {
          window.clearTimeout(state.cityTimer);
          input.value = item.city;
          list.hidden = true;
          state.addressLocation = item;
          state.selected = null;
          state.points = [];
          ['postcode', 'address', 'address_2'].forEach(function (name) { field(name).value = ''; });
          var search = document.querySelector('[data-delivery-search]');
          if (search) search.value = '';
          setCheckoutValue('#billing_city', item.city);
          setCheckoutValue('#billing_country', country);
          renderSuggestions([]);
          renderPoints([]);
          renderMap();
          loadPoints(item.viewport);
        });
        list.appendChild(option);
      });
    }).catch(function () { /* Manual city entry remains available. */ });
  }

  function setCheckoutValue(selector, value) {
    var target = checkoutElement(selector);
    if (target && value) target.value = value;
  }

  function applyAddressSuggestion(item) {
    if (!item) return;
    var search = document.querySelector('[data-delivery-search]');
    var clear = document.querySelector('[data-delivery-search-clear]');
    if (search) search.value = item.label || '';
    if (clear) clear.hidden = false;
    [['city', '#billing_city'], ['postcode', '#billing_postcode'], ['address', '#billing_address_1']].forEach(function (pair) {
      var value = String(item[pair[0]] || '').trim();
      if (!value) return;
      if (field(pair[0])) field(pair[0]).value = value;
      setCheckoutValue(pair[1], value);
    });
    setCheckoutValue('#billing_country', customer().country);
    syncAddressFieldVisibility();
    renderSuggestions([]);
    state.selected = null;
    state.addressLocation = item;
    renderMap();
    loadPoints(item.viewport || null);
  }

  function resolveAddressSuggestion(value) {
    if (!config.suggestionsUrl || String(value || '').trim().length < 3) return;
    request(config.suggestionsUrl + '?country=' + encodeURIComponent(customer().country) + '&query=' + encodeURIComponent(value)).then(function (data) {
      var item = Array.isArray(data.suggestions) ? data.suggestions[0] : null;
      if (item) applyAddressSuggestion(item);
    }).catch(function () {
      setStatus('Не удалось уточнить адрес. Проверьте его и попробуйте ещё раз.', true);
    });
  }

  function initNativeSuggestions() {
    if (!config.suggestEnabled || state.suggestView || !window.ymaps) return;
    window.ymaps.ready(function () {
      var search = document.querySelector('[data-delivery-search]');
      if (!search || !window.ymaps.SuggestView || state.suggestView) return;
      state.suggestView = new window.ymaps.SuggestView(search, { results: 5, zIndex: 100000 });
      state.suggestView.events.add('select', function (event) {
        var item = event.get('item') || {};
        resolveAddressSuggestion(item.value || item.displayName || '');
      });
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
    renderPoints(config.mapProvider === 'osm' ? state.points : core.filterPoints(state.points, checkoutValue('[data-delivery-search]')));
    setStatus('Выбран: ' + (point.address || point.name || point.id));
    if (state.map && point.latitude && point.longitude) {
      if (config.mapProvider === 'osm') state.map.setView([Number(point.latitude), Number(point.longitude)], 15);
      else state.map.setCenter([Number(point.latitude), Number(point.longitude)], 15, { duration: 250 });
    }
  }

  function renderOsmMap(container) {
    if (!container || !window.L) { if (container) container.hidden = true; return; }
    var points = state.points.filter(function (point) { return point.latitude && point.longitude; });
    var address = state.addressLocation;
    container.hidden = !points.length && !address;
    if (container.hidden) { if (state.placemarks) state.placemarks.clearLayers(); return; }
    if (!state.map) {
      state.map = window.L.map(container, { scrollWheelZoom: false });
      window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
      }).addTo(state.map);
      state.placemarks = window.L.featureGroup().addTo(state.map);
    }
    state.placemarks.clearLayers();
    points.forEach(function (point) {
      var label = document.createElement('span');
      label.textContent = point.address || point.name || '';
      window.L.circleMarker([Number(point.latitude), Number(point.longitude)], {
        radius: 9, color: '#fff', weight: 2, fillColor: '#714727', fillOpacity: 1
      }).bindTooltip(label).on('click', function () { selectPoint(point); }).addTo(state.placemarks);
    });
    state.map.invalidateSize();
    if (address) {
      var addressLabel = document.createElement('span');
      addressLabel.textContent = address.label || 'Найденный адрес';
      window.L.circleMarker([Number(address.latitude), Number(address.longitude)], {
        radius: 7, color: '#714727', weight: 3, fillColor: '#fff', fillOpacity: 1
      }).bindTooltip(addressLabel).addTo(state.placemarks);
      state.map.setView([Number(address.latitude), Number(address.longitude)], address.house ? 15 : 12);
    } else state.map.fitBounds(state.placemarks.getBounds(), { padding: [24, 24], maxZoom: 14 });
  }

  function renderMap() {
    if (config.mapProvider === 'osm') { renderOsmMap(document.querySelector('[data-delivery-map]')); return; }
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
    // Address is entered once, inside the courier dialog.
    var chosen = checkoutElement('input[name^="shipping_method"]:checked');
    var reveal = Boolean(config.officialCdek && chosen && chosen.value === 'official_cdek:137');
    document.querySelectorAll('.theobroma-delivery-address').forEach(function (row) {
      row.hidden = !reveal;
      row.setAttribute('aria-hidden', reveal ? 'false' : 'true');
    });
  }

  function syncOfficialCdek(fields, methods) {
    if (!config.officialCdek) return;
    var root = fields.querySelector('[data-official-cdek-dialog]');
    if (!root) {
      root = document.createElement('dialog');
      root.dataset.officialCdekDialog = '';
      root.className = 'theobroma-official-cdek';
      root.setAttribute('aria-label', 'Доставка СДЭК');
      root.innerHTML = '<div class="theobroma-official-content"><header><h3>Доставка СДЭК</h3><button type="button" data-official-close aria-label="Закрыть">×</button></header><div data-official-address></div><ul class="woocommerce-shipping-methods" data-official-rates></ul><p data-official-message role="status"></p></div><footer><button type="button" data-official-apply>Готово</button></footer>';
      fields.appendChild(root);
      root.addEventListener('click', function (event) {
        if (event.target === root || event.target.closest('[data-official-close]')) root.close();
        if (event.target.closest('[data-official-apply]')) {
          var chosen = root.querySelector('input[name^="shipping_method"]:checked');
          var office = root.querySelector('.cdek-office-code');
          if (!chosen || (chosen.value === 'official_cdek:136' && (!office || !office.value))) {
            root.querySelector('[data-official-message]').textContent = 'Выберите тариф и пункт выдачи для доставки в ПВЗ.';
            return;
          }
          root.close();
        }
      });
      // The official map is a body overlay and must not sit behind a native dialog.
      root.addEventListener('click', function (event) {
        if (event.target.closest('.open-pvz-btn')) root.close();
      }, true);
    }
    var address = root.querySelector('[data-official-address]');
    fields.querySelectorAll('#billing_country_field, #billing_city_field, .theobroma-delivery-address').forEach(function (row) {
      if (row.parentNode !== address) address.appendChild(row);
    });
    var rates = root.querySelector('[data-official-rates]');
    rates.replaceChildren();
    methods.querySelectorAll('input[name^="shipping_method"]').forEach(function (input) {
      if (input.value.indexOf('official_cdek:') === 0) rates.appendChild(input.closest('li'));
    });
    var chosen = rates.querySelector('input:checked');
    var row = document.createElement('li');
    row.className = 'theobroma-official-summary';
    var label = document.createElement('label');
    var selectedLabel = chosen && chosen.closest('li').querySelector('label');
    var price = selectedLabel && selectedLabel.querySelector('.amount');
    label.textContent = 'СДЭК' + (chosen ? (chosen.value === 'official_cdek:137' ? ' · Курьер' : ' · Пункт выдачи') : '') + (price ? ': ' + price.textContent.trim() : '');
    var button = document.createElement('button');
    button.type = 'button';
    button.textContent = chosen ? 'Изменить доставку' : 'Выбрать доставку';
    button.addEventListener('click', function () {
      root.querySelector('[data-official-message]').textContent = rates.children.length ? '' : 'Укажите город для расчёта доставки.';
      root.showModal();
    });
    button.className = 'theobroma-delivery-open' + (chosen ? ' is-confirmed' : '');
    var selector = document.createElement('input');
    selector.type = 'radio';
    selector.checked = Boolean(chosen);
    selector.id = 'theobroma-official-cdek-choice';
    selector.setAttribute('aria-label', 'СДЭК');
    label.htmlFor = selector.id;
    selector.addEventListener('click', function (event) {
      event.preventDefault();
      button.click();
    });
    row.append(selector, label, button);
    methods.prepend(row);
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
    syncOfficialCdek(fields, methods);
    var heading = document.querySelector('#commerce-checkout-title') || document.querySelector('.commerce-cart-checkout .woocommerce-billing-fields > h3');
    if (heading) heading.textContent = 'Получатель';
    if (!fields.querySelector('.theobroma-delivery-heading')) {
      var deliveryHeading = document.createElement('h3');
      deliveryHeading.className = 'theobroma-delivery-heading';
      deliveryHeading.textContent = 'Доставка';
      var cityRow = fields.querySelector(config.officialCdek ? '#billing_country_field' : '#billing_city_field');
      fields.insertBefore(deliveryHeading, cityRow && cityRow.parentNode === fields ? cityRow : host);
    }
    ['first_name', 'last_name', 'phone', 'email'].forEach(function (key) {
      var input = fields.querySelector('#billing_' + key);
      if (input) input.setAttribute('aria-label', {first_name: 'Имя', last_name: 'Фамилия', phone: 'Телефон', email: 'Электронная почта'}[key]);
    });
    var payment = document.querySelector('.commerce-cart-checkout #payment');
    if (payment && !payment.querySelector('.theobroma-payment-heading')) {
      var paymentHeading = document.createElement('h3');
      paymentHeading.className = 'theobroma-payment-heading';
      paymentHeading.textContent = 'Оплата';
      payment.prepend(paymentHeading);
    }
    table.hidden = true;
  }

  function syncCheckoutDestination(details) {
    details.state = '';
    [['country', 'country'], ['city', 'city'], ['postcode', 'postcode'], ['address', 'address_1'], ['address_2', 'address_2']].forEach(function (pair) {
      var input = checkoutElement('#billing_' + pair[1]);
      if (input) input.value = details[pair[0]] || '';
    });
    var region = checkoutElement('#billing_state');
    if (region) region.value = '';
    return new Promise(function (resolve, reject) {
      var body = $(document.body);
      var timer = window.setTimeout(function () {
        body.off('updated_checkout.theobromaDestination');
        reject(new Error('Не удалось обновить адрес. Попробуйте ещё раз.'));
      }, 20000);
      body.one('updated_checkout.theobromaDestination', function () { window.clearTimeout(timer); resolve(); });
      body.trigger('update_checkout');
    });
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
    syncCheckoutDestination(details).then(function () { return request(config.quoteUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(core.buildQuotePayload(state.provider, state.kind, state.selected, details))
    }); }).then(function (data) {
      setStatus((data.quote && data.quote.label ? data.quote.label : 'Доставка выбрана') + '. Обновляем заказ…');
      return new Promise(function (resolve, reject) {
        var body = $(document.body);
        var attempts = 0;
        var timer = window.setTimeout(function () { finish(new Error('Не удалось выбрать доставку. Повторите расчёт.')); }, 25000);
        function finish(error) {
          window.clearTimeout(timer);
          body.off('updated_checkout.theobromaChoose');
          if (error) reject(error); else { closeDialog(); resolve(); }
        }
        body.on('updated_checkout.theobromaChoose', function () {
          var opener = document.querySelector('.commerce-cart-checkout [data-delivery-open="' + data.provider + '"].is-confirmed');
          var row = opener && opener.closest('li');
          var radio = row && row.querySelector('input[name^="shipping_method"]');
          if (!radio) { finish(new Error('Расчёт доставки устарел. Выберите доставку ещё раз.')); return; }
          if (radio.checked || radio.type === 'hidden') { finish(); return; }
          if (++attempts > 1) { finish(new Error('Не удалось переключить способ доставки.')); return; }
          radio.checked = true;
          body.trigger('update_checkout');
        });
        body.trigger('update_checkout');
      });
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
    if (event.target && event.target.matches('[data-delivery-field="city"]')) {
      window.clearTimeout(state.cityTimer);
      var typedCity = event.target.value;
      state.cityTimer = window.setTimeout(function () { suggestCity(typedCity); }, 280);
    }
    if (event.target && event.target.matches('#billing_city')) syncAddressFieldVisibility();
    if (event.target && event.target.matches('[data-delivery-field="address"]')) {
      applyCourierAddress();
      window.clearTimeout(courierSearchTimer);
      courierSearchTimer = window.setTimeout(function () { suggestCourierAddress(field('address').value); }, 280);
    }
  });
  document.addEventListener('change', function (event) {
    if (event.target.matches('[data-delivery-field="country"], [data-delivery-field="city"]')) {
      window.clearTimeout(state.cityTimer);
      var selectedCity = event.target === field('city') && (state.citySuggestions || []).find(function (item) { return item.label === event.target.value; });
      if (selectedCity) field('city').value = selectedCity.city;
      state.addressLocation = selectedCity || null;
      state.selected = null;
      state.points = [];
      renderPoints([]);
      ['postcode', 'address', 'address_2'].forEach(function (name) { field(name).value = ''; });
      var search = document.querySelector('[data-delivery-search]');
      if (search) search.value = '';
      if (event.target === field('country')) {
        field('city').value = '';
        field('city').focus();
        setStatus('Укажите город доставки.');
        state.citySuggestions = [];
        var cityList = document.getElementById('theobroma-city-options');
        if (cityList) cityList.innerHTML = '';
        renderMap();
      } else if (selectedCity) { renderMap(); loadPoints(selectedCity.viewport); }
      else { loadPointsForCheckoutAddress(customer().city); }
    }
    if (event.target && event.target.matches('#billing_city')) syncAddressFieldVisibility();
    if (event.target && event.target.matches('[data-delivery-field="address"]')) {
      applyCourierAddress();
      window.clearTimeout(courierSearchTimer);
      courierSearchTimer = window.setTimeout(function () { suggestCourierAddress(field('address').value); }, 280);
    }
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
