(function () {
  'use strict';

  const selector = 'input[type="tel"],input[name*="phone" i]:not([type="hidden"])';
  const incompleteMessage = 'Введите номер телефона полностью';

  function nationalDigits(value) {
    const source = String(value || '');
    let digits = source.replace(/\D/g, '');

    if (source.trim().startsWith('+7')) {
      digits = digits.replace(/^7/, '');
    } else if (digits.length > 10 && /^[78]/.test(digits)) {
      digits = digits.slice(1);
    }

    return digits.slice(0, 10);
  }

  function formatPhone(digits) {
    const value = String(digits || '').replace(/\D/g, '').slice(0, 10);
    if (value.length === 0) return '';

    let formatted = '+7';

    if (value.length > 0) formatted += ` (${value.slice(0, 3)}`;
    if (value.length >= 3) formatted += ')';
    if (value.length > 3) formatted += ` ${value.slice(3, 6)}`;
    if (value.length > 6) formatted += `-${value.slice(6, 8)}`;
    if (value.length > 8) formatted += `-${value.slice(8, 10)}`;

    return formatted;
  }

  function nationalDigitCountBefore(value, caret) {
    const prefix = String(value || '').slice(0, Math.max(0, caret));
    const count = (prefix.match(/\d/g) || []).length;
    return Math.max(0, count - (prefix.includes('7') && String(value || '').startsWith('+7') ? 1 : 0));
  }

  function caretAfterDigits(value, count) {
    if (count <= 0) return 2;

    let seen = 0;
    for (let index = 2; index < value.length; index += 1) {
      if (/\d/.test(value[index])) seen += 1;
      if (seen === count) return index + 1;
    }

    return value.length;
  }

  function applyValue(input, digits, caretDigits) {
    input.value = formatPhone(digits);
    input.setCustomValidity(digits.length === 0 || digits.length === 10 ? '' : incompleteMessage);

    if (typeof caretDigits === 'number' && document.activeElement === input) {
      const caret = caretAfterDigits(input.value, caretDigits);
      input.setSelectionRange(caret, caret);
    }
  }

  function handleDeletion(event) {
    if (event.inputType !== 'deleteContentBackward' && event.inputType !== 'deleteContentForward') return;

    const input = event.currentTarget;
    const digits = nationalDigits(input.value);
    const selectionStart = input.selectionStart ?? input.value.length;
    const selectionEnd = input.selectionEnd ?? selectionStart;
    const startDigit = nationalDigitCountBefore(input.value, selectionStart);
    const endDigit = nationalDigitCountBefore(input.value, selectionEnd);
    let nextDigits = digits;
    let nextCaret = startDigit;

    if (selectionStart !== selectionEnd && startDigit !== endDigit) {
      nextDigits = digits.slice(0, startDigit) + digits.slice(endDigit);
    } else if (event.inputType === 'deleteContentBackward' && startDigit > 0) {
      nextDigits = digits.slice(0, startDigit - 1) + digits.slice(startDigit);
      nextCaret = startDigit - 1;
    } else if (event.inputType === 'deleteContentForward' && startDigit < digits.length) {
      nextDigits = digits.slice(0, startDigit) + digits.slice(startDigit + 1);
    }

    event.preventDefault();
    applyValue(input, nextDigits, nextCaret);
  }

  function enhance(input) {
    if (!(input instanceof HTMLInputElement) || input.dataset.phoneFormatReady === 'true') return;

    input.dataset.phoneFormatReady = 'true';
    input.type = 'tel';
    input.placeholder = 'Номер телефона';
    input.inputMode = 'tel';
    input.autocomplete = 'tel';
    input.maxLength = 18;

    const field = input.closest('.phone-field');
    if (field) field.querySelectorAll('.phone-flag,.phone-triangle,.phone-code').forEach((element) => element.remove());

    input.addEventListener('beforeinput', handleDeletion);
    input.addEventListener('input', () => {
      const caretDigits = nationalDigitCountBefore(input.value, input.selectionStart ?? input.value.length);
      applyValue(input, nationalDigits(input.value), caretDigits);
    });
    input.addEventListener('blur', () => applyValue(input, nationalDigits(input.value)));

    applyValue(input, nationalDigits(input.value));
  }

  function enhanceWithin(root) {
    if (root instanceof HTMLInputElement && root.matches(selector)) enhance(root);
    if (root.querySelectorAll) root.querySelectorAll(selector).forEach(enhance);
  }

  enhanceWithin(document);

  new MutationObserver((mutations) => {
    mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
      if (node instanceof Element) enhanceWithin(node);
    }));
  }).observe(document.documentElement, { childList: true, subtree: true });

  document.addEventListener('reset', (event) => {
    window.setTimeout(() => {
      enhanceWithin(event.target);
      event.target.querySelectorAll(selector).forEach((input) => applyValue(input, nationalDigits(input.value)));
    }, 0);
  });
})();
