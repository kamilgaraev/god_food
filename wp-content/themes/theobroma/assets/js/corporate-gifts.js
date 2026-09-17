(() => {
  'use strict';
  const root = document.querySelector('.corporate-redesign');
  if (!root) return;
  const ribbonToggle = root.querySelector('.cg-ribbon-toggle');
  ribbonToggle?.addEventListener('click', () => {
    const paused = ribbonToggle.closest('.cg-ribbon').classList.toggle('is-paused');
    ribbonToggle.setAttribute('aria-pressed', String(paused));
    ribbonToggle.setAttribute('aria-label', paused ? 'Продолжить бегущую строку' : 'Приостановить бегущую строку');
  });
  const dialog = root.querySelector('.cg-dialog');
  const form = root.querySelector('[data-cg-form]');
  const cards = Array.from(root.querySelectorAll('[data-cg-gift]'));
  let opener = null;
  let chosenGift = '';

  function selectRequest(value) {
    const select = form.querySelector('[name="custom[gift]"]');
    if (select && Array.from(select.options || []).some(option => option.value === value)) {
      select.value = value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
      const message = form.querySelector('[name="message"]');
      if (message && !message.value.includes(value)) {
        message.value = `${message.value}${message.value ? '\n' : ''}Интересует: ${value}`;
        message.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }
    document.getElementById('corporate-request').scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    const field = form.querySelector('input:not([type="hidden"]):not([tabindex="-1"]):not([name="theobroma_website"]),select,textarea');
    if (field) field.focus({ preventScroll: true });
  }

  root.querySelectorAll('[data-cg-open]').forEach(link => {
    link.addEventListener('click', event => {
      if (!dialog || typeof dialog.showModal !== 'function' || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      const card = cards[Number(link.dataset.cgOpen)];
      if (!card) return;
      event.preventDefault();
      opener = link;
      chosenGift = card.querySelector('h3').textContent.replace(/[«»]/g, '').trim();
      dialog.querySelector('h2').textContent = `«${chosenGift}»`;
      const source = card.querySelector('img');
      const image = dialog.querySelector('.cg-dialog-image');
      image.src = source.src;
      image.alt = source.alt;
      dialog.querySelector('.cg-dialog-description').textContent = card.querySelector('.cg-gift-copy p').textContent;
      dialog.querySelector('.cg-price').textContent = card.querySelector('.cg-price').textContent;
      dialog.showModal();
      document.documentElement.classList.add('cg-dialog-open');
    });
  });
  if (dialog) {
    dialog.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const targets = Array.from(dialog.querySelectorAll('button:not([disabled]),a[href],input:not([type="hidden"]),select,textarea,[tabindex="0"]'));
      const first = targets[0];
      const last = targets[targets.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
    dialog.querySelector('.cg-dialog-close').addEventListener('click', () => dialog.close());
    let backdropPress = false;
    dialog.addEventListener('pointerdown', event => { backdropPress = event.target === dialog; });
    dialog.addEventListener('click', event => { if (event.target === dialog && backdropPress) dialog.close(); });
    dialog.addEventListener('close', () => {
      document.documentElement.classList.remove('cg-dialog-open');
      if (opener) opener.focus({ preventScroll: true });
    });
    dialog.querySelector('[data-cg-order]').addEventListener('click', () => {
      dialog.close();
      // Run after the native close event restores the original trigger focus.
      requestAnimationFrame(() => selectRequest(chosenGift));
    });
  }
  root.querySelectorAll('[data-cg-request]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    selectRequest(link.dataset.cgRequest);
  }));
  root.querySelectorAll('[data-cg-carousel]').forEach(carousel => {
    const track = carousel.querySelector('[data-cg-track]');
    const buttons = Array.from(carousel.querySelectorAll('[data-cg-direction]'));
    function sync() {
      const max = track.scrollWidth - track.clientWidth;
      buttons.forEach(button => { button.disabled = Number(button.dataset.cgDirection) < 0 ? track.scrollLeft < 2 : track.scrollLeft >= max - 2; });
    }
    buttons.forEach(button => button.addEventListener('click', () => {
      const step = track.children[0].getBoundingClientRect().width + parseFloat(getComputedStyle(track).gap || '0');
      track.scrollBy({ left: Number(button.dataset.cgDirection) * step, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }));
    track.addEventListener('scroll', sync, { passive: true });
    new ResizeObserver(sync).observe(track);
    sync();
  });
})();
