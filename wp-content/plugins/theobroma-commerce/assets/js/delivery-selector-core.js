(function (window) {
  'use strict';

  function text(value) {
    return String(value || '').trim().toLowerCase();
  }

  function filterPoints(points, query) {
    var needle = text(query);
    if (!needle) return points.slice();
    return points.filter(function (point) {
      return text(point.name + ' ' + point.address + ' ' + (point.work_time || '')).indexOf(needle) !== -1;
    });
  }

  function canRenderMap(config) {
    return Boolean(config && config.mapEnabled && text(config.mapKey));
  }

  function buildQuotePayload(provider, kind, point, customer) {
    customer = customer || {};
    return {
      provider: provider,
      kind: kind,
      point_id: point && point.id ? String(point.id) : '',
      country: customer.country || 'RU',
      state: customer.state || '',
      city: customer.city || '',
      postcode: customer.postcode || '',
      address: customer.address || '',
      address_2: customer.address_2 || '',
      first_name: customer.first_name || '',
      last_name: customer.last_name || '',
      middle_name: customer.middle_name || '',
      phone: customer.phone || '',
      latitude: customer.latitude || '',
      longitude: customer.longitude || ''
    };
  }

  window.TheobromaDeliveryCore = {
    filterPoints: filterPoints,
    canRenderMap: canRenderMap,
    buildQuotePayload: buildQuotePayload
  };
})(window);
