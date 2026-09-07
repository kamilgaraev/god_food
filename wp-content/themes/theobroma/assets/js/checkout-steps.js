(function ($) {
  'use strict';

  const rootSelector = '.commerce-cart-checkout form.checkout';
  const contactKeys = ['first_name', 'last_name', 'phone', 'email'];
  const labels = { first_name: 'Имя', last_name: 'Фамилия', phone: 'Телефон', email: 'Электронная почта' };
  const states = new WeakMap();
  // Keep the draft only for this page lifetime; never put contact details in browser storage.
  const draft = { values: {}, step: 0 };
  const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const value = (form, key) => form.querySelector('#billing_' + key)?.value.trim() || '';

  function contactError(form, key) {
    const input = form.querySelector('#billing_' + key);
    const text = value(form, key);
    if (!text) return 'Заполните поле «' + labels[key] + '».';
    if (key === 'first_name' || key === 'last_name') {
      if (!/^[\p{L}\p{Zs}\p{Pd}]{1,50}$/u.test(text) || !/\p{L}/u.test(text)) {
        return 'Используйте буквы, пробелы и дефисы, до 50 символов.';
      }
    }
    if (key === 'phone' && !/^[78]\d{10}$/.test(text.replace(/\D/g, ''))) {
      return 'Введите номер телефона полностью: +7 и 10 цифр.';
    }
    if (key === 'email' && (input?.validity.typeMismatch || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text))) {
      return 'Проверьте адрес электронной почты, например name@mail.ru.';
    }
    return '';
  }

  function markError(form, key, message) {
    const input = form.querySelector('#billing_' + key);
    const row = input?.closest('.form-row');
    if (!row) return;
    let error = row.querySelector('.checkout-field-error');
    if (!error) {
      error = document.createElement('span');
      error.className = 'checkout-field-error';
      error.id = 'checkout-error-' + key;
      row.appendChild(error);
      const describedBy = new Set((input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
      describedBy.add(error.id);
      input.setAttribute('aria-describedby', [...describedBy].join(' '));
    }
    error.textContent = message;
    error.hidden = !message;
    input.setAttribute('aria-invalid', String(Boolean(message)));
    row.classList.toggle('checkout-field-invalid', Boolean(message));
  }

  function focusControl(element) {
    if (!element) return;
    element.focus({ preventScroll: true });
    element.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
  }

  function validateContacts(state, focus = true) {
    let firstError;
    contactKeys.forEach(key => {
      const message = contactError(state.form, key);
      markError(state.form, key, message);
      if (message && !firstError) firstError = state.form.querySelector('#billing_' + key);
    });
    if (firstError) {
      showStep(state, 0);
      if (focus) focusControl(firstError);
      state.notice.textContent = 'Заполните обязательные контакты — затем выберем доставку.';
      return false;
    }
    return true;
  }

  function shippingError(form) {
    const methods = [...form.querySelectorAll('input[name^="shipping_method"]')];
    if (!methods.length) {
      return form.querySelector('.woocommerce-shipping-totals') ? 'Для этого адреса пока нет способов доставки. Уточните город.' : '';
    }
    const groups = [...new Set(methods.map(input => input.name))];
    for (const group of groups) {
      const selected = methods.find(input => input.name === group && (input.checked || input.type === 'hidden'));
      if (!selected) return 'Выберите способ доставки.';
      const method = selected.value;
      if (/theobroma_(ozon|cdek)/.test(method) && !selected.closest('li')?.querySelector('.theobroma-delivery-open.is-confirmed')) {
        return 'Выберите пункт выдачи или адрес курьера и нажмите «Рассчитать и выбрать».';
      }
      if (method === 'official_cdek:136' && !form.querySelector('.cdek-office-code')?.value) {
        return 'Выберите пункт выдачи СДЭК.';
      }
      if (/courier|official_cdek:137/.test(method) && (!value(form, 'address_1') || !value(form, 'postcode'))) {
        return 'Укажите полный адрес и индекс в настройках курьерской доставки.';
      }
    }
    return '';
  }

  function validateDelivery(state) {
    const message = state.busy ? 'Дождитесь обновления стоимости доставки.' : shippingError(state.form);
    if (!message) return true;
    showStep(state, 1);
    state.notice.textContent = message;
    focusControl(state.delivery.querySelector('.theobroma-delivery-open') || state.deliveryTitle);
    return false;
  }

  function updateSummary(state) {
    const name = [value(state.form, 'first_name'), value(state.form, 'last_name')].filter(Boolean).join(' ');
    state.summary.querySelector('[data-summary-contact]').textContent =
      [name, value(state.form, 'phone'), value(state.form, 'email')].filter(Boolean).join(' · ');
    const selected = state.form.querySelector('input[name^="shipping_method"]:checked, input[name^="shipping_method"][type="hidden"]');
    const row = selected?.closest('li');
    state.summary.querySelector('[data-summary-delivery]').textContent =
      [row?.querySelector('label')?.textContent.trim(), row?.querySelector('.theobroma-delivery-selection-copy')?.textContent.trim() ||
        [value(state.form, 'city'), value(state.form, 'address_1')].filter(Boolean).join(', ')].filter(Boolean).join(' · ') || 'Доставка не требуется';
  }

  function showStep(state, step, animate = false) {
    const changed = state.step !== step;
    state.step = step;
    draft.step = step;
    state.form.dataset.checkoutStep = String(step);
    state.contacts.hidden = step !== 0;
    state.delivery.hidden = step !== 1;
    state.summary.hidden = step !== 2;
    const review = state.form.querySelector('#order_review');
    if (review) review.hidden = step !== 2;
    state.back.hidden = step === 0;
    state.next.hidden = step === 2;
    state.next.textContent = step === 0 ? 'К выбору доставки →' : 'К оплате →';
    state.next.disabled = step === 1 && state.busy;
    state.notice.textContent = step === 1 && state.busy ? 'Обновляем стоимость доставки…' : '';
    state.nav.querySelectorAll('button').forEach((button, index) => {
      if (index === step) button.setAttribute('aria-current', 'step');
      else button.removeAttribute('aria-current');
      button.classList.toggle('is-complete', index < step);
    });
    updateSummary(state);
    if (changed && animate) {
      const section = step === 0 ? state.contacts : step === 1 ? state.delivery : state.summary;
      if (!reducedMotion()) section.animate([{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 240, easing: 'ease-out' });
      focusControl(section.querySelector('[tabindex="-1"]'));
    }
  }

  function goTo(state, step) {
    if (step > state.step && !validateContacts(state)) return;
    if (step === 2 && !validateDelivery(state)) return;
    showStep(state, step, true);
  }

  function prepareFields(state) {
    const fields = state.form.querySelector('.woocommerce-billing-fields__field-wrapper');
    if (!fields) return;
    [...fields.children].forEach(row => {
      if (row === state.contacts || row === state.delivery) return;
      if (contactKeys.some(key => row.id === 'billing_' + key + '_field')) state.contacts.appendChild(row);
      else state.delivery.appendChild(row);
    });
    contactKeys.forEach(key => {
      const input = state.form.querySelector('#billing_' + key);
      if (!input) return;
      input.setAttribute('aria-required', 'true');
      const row = input.closest('.form-row');
      row.classList.add('validate-required');
      let label = row.querySelector('label');
      if (!label) {
        label = document.createElement('label');
        label.htmlFor = input.id;
        row.prepend(label);
      }
      label.className = 'checkout-field-label';
      if (label.dataset.checkoutLabel !== 'true') {
        label.textContent = labels[key] + ' ';
        const required = document.createElement('span');
        required.className = 'checkout-required';
        required.textContent = '*';
        required.setAttribute('aria-hidden', 'true');
        label.appendChild(required);
        label.dataset.checkoutLabel = 'true';
      }
    });
  }

  function init(form) {
    if (!form.querySelector('#billing_first_name')) return;
    const oldState = states.get(form);
    if (oldState?.nav.isConnected) return;
    form.classList.add('checkout-wizard');
    form.closest('.commerce-cart-checkout').classList.add('has-checkout-wizard');
    const nav = document.createElement('nav');
    nav.className = 'checkout-progress';
    nav.setAttribute('aria-label', 'Шаги оформления заказа');
    nav.innerHTML = '<ol>' + ['Контакты', 'Доставка', 'Оплата'].map((label, index) =>
      '<li><button type="button" data-checkout-go="' + index + '"><span aria-hidden="true">' + (index + 1) + '</span>' + label + '</button></li>').join('') + '</ol>';
    form.prepend(nav);
    const fields = form.querySelector('.woocommerce-billing-fields__field-wrapper');
    const contacts = document.createElement('section');
    contacts.className = 'checkout-step checkout-step-contacts';
    contacts.setAttribute('aria-labelledby', 'checkout-contacts-title');
    contacts.innerHTML = '<p class="checkout-step-title" id="checkout-contacts-title" tabindex="-1">Кто получит заказ?</p><p class="checkout-step-hint">Все четыре поля обязательны. Имя, фамилия и телефон нужны для выдачи заказа, а на почту пришлём подтверждение.</p>';
    const delivery = document.createElement('section');
    delivery.className = 'checkout-step checkout-step-delivery';
    delivery.setAttribute('aria-labelledby', 'checkout-delivery-title');
    delivery.innerHTML = '<p class="checkout-step-title" id="checkout-delivery-title" tabindex="-1">Куда доставить?</p><p class="checkout-step-hint">Выберите службу доставки, затем пункт выдачи или курьера. Стоимость появится после расчёта.</p>';
    fields.prepend(contacts, delivery);
    const summary = document.createElement('section');
    summary.className = 'checkout-review-summary';
    summary.innerHTML = '<p class="checkout-step-title" tabindex="-1">Проверьте заказ</p><div><span>Получатель</span><p data-summary-contact></p><button type="button" data-checkout-go="0">Изменить</button></div><div><span>Доставка</span><p data-summary-delivery></p><button type="button" data-checkout-go="1">Изменить</button></div>';
    form.querySelector('#order_review')?.before(summary);
    const actions = document.createElement('div');
    actions.className = 'checkout-step-actions';
    actions.innerHTML = '<p class="checkout-step-notice" tabindex="-1" role="status" aria-live="polite"></p><button type="button" class="checkout-step-back">← Назад</button><button type="button" class="checkout-step-next"></button>';
    form.appendChild(actions);
    const state = { form, nav, contacts, delivery, summary, actions, step: -1, busy: false,
      notice: actions.querySelector('p'), back: actions.querySelector('.checkout-step-back'),
      next: actions.querySelector('.checkout-step-next'), deliveryTitle: delivery.querySelector('.checkout-step-title') };
    states.set(form, state);
    contactKeys.forEach(key => {
      const input = form.querySelector('#billing_' + key);
      if (input && Object.hasOwn(draft.values, key)) input.value = draft.values[key];
    });
    prepareFields(state);
    const restoredStep = contactKeys.some(key => contactError(form, key)) ? 0 : Math.min(draft.step, shippingError(form) ? 1 : 2);
    showStep(state, restoredStep);
    state.back.addEventListener('click', () => goTo(state, Math.max(0, state.step - 1)));
    state.next.addEventListener('click', () => goTo(state, state.step + 1));
    $(form).off('checkout_place_order.theobromaSteps').on('checkout_place_order.theobromaSteps', () => canSubmit(state));
  }

  function canSubmit(state) {
    if (!validateContacts(state) || !validateDelivery(state)) return false;
    if (state.step !== 2) {
      goTo(state, state.step + 1);
      return false;
    }
    const missing = state.form.querySelector('input[name="theobroma_privacy_consent"]:not(:checked), input[name="terms"]:not(:checked)');
    if (missing) {
      state.notice.textContent = 'Подтвердите согласие с условиями заказа и обработкой персональных данных.';
      focusControl(missing);
      return false;
    }
    return true;
  }

  function enhanceWithin(root) {
    if (root instanceof Element && root.matches(rootSelector)) init(root);
    root.querySelectorAll?.(rootSelector).forEach(init);
  }
  enhanceWithin(document);
  new MutationObserver(mutations => mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
    if (node instanceof Element) enhanceWithin(node);
  }))).observe(document.body, { childList: true, subtree: true });

  document.addEventListener('click', event => {
    const button = event.target.closest('[data-checkout-go], .theobroma-delivery-open, .open-pvz-btn');
    const state = button && states.get(button.closest('form.checkout'));
    if (!state) return;
    if (button.hasAttribute('data-checkout-go')) {
      event.preventDefault();
      goTo(state, Number(button.dataset.checkoutGo));
    } else if (!validateContacts(state)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  document.addEventListener('input', event => {
    const state = states.get(event.target.closest('form.checkout'));
    if (!state) return;
    const key = contactKeys.find(key => event.target.id === 'billing_' + key);
    if (!key) return;
    draft.values[key] = event.target.value;
    if (event.target.closest('.form-row')?.querySelector('.checkout-field-error')) {
      markError(state.form, key, contactError(state.form, key));
    }
    if (state.step === 0 && contactKeys.every(key => !contactError(state.form, key))) state.notice.textContent = '';
    updateSummary(state);
  });
  document.addEventListener('focusout', event => {
    const state = states.get(event.target.closest('form.checkout'));
    const key = contactKeys.find(key => event.target.id === 'billing_' + key);
    if (state && key) markError(state.form, key, contactError(state.form, key));
  });
  document.addEventListener('submit', event => {
    const state = states.get(event.target);
    if (!state) return;
    if (state.step < 2) {
      event.preventDefault();
      event.stopImmediatePropagation();
      goTo(state, state.step + 1);
    } else if (!canSubmit(state)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  $(document.body).on('update_checkout.theobromaSteps', () => {
    document.querySelectorAll(rootSelector).forEach(form => {
      const state = states.get(form);
      if (!state) return;
      state.busy = true;
      if (state.step === 1) { state.next.disabled = true; state.notice.textContent = 'Обновляем стоимость доставки…'; }
    });
  }).on('updated_checkout.theobromaSteps', () => {
    // Delivery placement also runs on this event. Reconcile only after every listener finished.
    queueMicrotask(() => document.querySelectorAll(rootSelector).forEach(form => {
      init(form);
      const state = states.get(form);
      if (!state) return;
      state.busy = false;
      prepareFields(state);
      showStep(state, state.step === 2 && shippingError(form) ? 1 : state.step);
    }));
  }).on('checkout_error.theobromaSteps', () => {
    document.querySelectorAll(rootSelector).forEach(form => {
      const state = states.get(form);
      if (!state) return;
      state.busy = false;
      if (!validateContacts(state)) return;
      const errors = form.querySelector('.woocommerce-NoticeGroup-checkout, .woocommerce-error');
      const text = errors?.textContent.trim() || '';
      const contactKey = contactKeys.find(key => errors?.querySelector('[data-id="billing_' + key + '"]')) ||
        (/фамили/i.test(text) ? 'last_name' : /поле.*имя|настоящее имя/i.test(text) ? 'first_name' :
          /телефон/i.test(text) ? 'phone' : /почт|email/i.test(text) ? 'email' : '');
      if (contactKey) {
        showStep(state, 0);
        markError(form, contactKey, text);
        focusControl(form.querySelector('#billing_' + contactKey));
        return;
      }
      if (/достав|индекс|адрес|город|пункт/i.test(text) || shippingError(form)) showStep(state, 1);
      else showStep(state, 2);
      state.notice.textContent = text || 'Проверьте данные заказа и попробуйте ещё раз.';
      focusControl(state.notice);
    });
  });
})(jQuery);
