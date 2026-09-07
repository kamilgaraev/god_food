(() => {
  'use strict';

  document.querySelectorAll('[data-photo-showcase]').forEach((showcase) => {
    const triggers = [...showcase.querySelectorAll('[data-photo-lightbox-trigger]')];
    const lightbox = showcase.querySelector('[data-photo-lightbox]');
    if (triggers.length === 0 || !lightbox) return;

    const image = lightbox.querySelector('[data-photo-lightbox-image]');
    const caption = lightbox.querySelector('[data-photo-lightbox-caption]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let generation = 0;
    let closing = false;
    let overlayMotion;
    let imageMotion;
    const counter = document.createElement('span');
    counter.className = 'theobroma-photo-lightbox__counter';
    counter.setAttribute('aria-live', 'polite');
    lightbox.querySelector('.theobroma-photo-lightbox__panel').appendChild(counter);
    const closeButton = lightbox.querySelector('.theobroma-photo-lightbox__close');
    const previousButton = lightbox.querySelector('[data-photo-lightbox-previous]');
    const nextButton = lightbox.querySelector('[data-photo-lightbox-next]');
    document.body.appendChild(lightbox);
    let activeIndex = 0;
    let returnFocus = null;

    const render = async (direction = 0) => {
      const request = ++generation;
      const index = activeIndex;
      const trigger = triggers[index];
      const source = trigger.dataset.photoSrc || '';
      const loaded = new Image();
      loaded.src = source;
      try { await loaded.decode(); } catch (_) { return; }
      if (request !== generation || closing || lightbox.hidden) return;
      imageMotion?.cancel();
      if (image.getAttribute('src') && !reducedMotion.matches) {
        imageMotion = image.animate(
          [{ opacity: 1, transform: 'translateX(0)' }, { opacity: 0, transform: `translateX(${-direction * 14}px)` }],
          { duration: 120, easing: 'ease-in', fill: 'forwards' }
        );
        await imageMotion.finished.catch(() => {});
        if (request !== generation || closing || lightbox.hidden) return;
      }
      image.src = source;
      image.alt = trigger.dataset.photoAlt || '';
      caption.textContent = trigger.dataset.photoCaption || '';
      caption.hidden = caption.textContent === '';
      counter.textContent = `${index + 1} / ${triggers.length}`;
      previousButton.hidden = triggers.length < 2;
      nextButton.hidden = triggers.length < 2;
      imageMotion?.cancel();
      if (!reducedMotion.matches) {
        imageMotion = image.animate(
          [{ opacity: 0, transform: `translateX(${direction * 14}px)` }, { opacity: 1, transform: 'translateX(0)' }],
          { duration: 220, easing: 'ease-out' }
        );
      }
    };

    const open = (trigger) => {
      activeIndex = triggers.indexOf(trigger);
      returnFocus = trigger;
      closing = false;
      overlayMotion?.cancel();
      lightbox.hidden = false;
      render();
      if (!reducedMotion.matches) overlayMotion = lightbox.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 220, easing: 'ease-out' });
      lightbox.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add('theobroma-photo-lightbox-open');
      closeButton.focus({ preventScroll: true });
    };

    const close = async () => {
      if (lightbox.hidden || closing) return;
      closing = true;
      ++generation;
      imageMotion?.cancel();
      overlayMotion?.cancel();
      if (!reducedMotion.matches) {
        overlayMotion = lightbox.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 160, easing: 'ease-in', fill: 'forwards' });
        await overlayMotion.finished.catch(() => {});
      }
      if (!closing) return;
      lightbox.hidden = true;
      overlayMotion?.cancel();
      lightbox.setAttribute('aria-hidden', 'true');
      image.removeAttribute('src');
      counter.textContent = '';
      document.documentElement.classList.remove('theobroma-photo-lightbox-open');
      if (returnFocus instanceof HTMLElement) returnFocus.focus({ preventScroll: true });
      returnFocus = null;
    };

    const move = (offset) => {
      if (closing || lightbox.hidden) return;
      activeIndex = (activeIndex + offset + triggers.length) % triggers.length;
      render(offset);
    };

    triggers.forEach((trigger) => trigger.addEventListener('click', () => open(trigger)));
    lightbox.addEventListener('click', (event) => {
      if (event.target.closest('[data-photo-lightbox-close]')) close();
      else if (event.target.closest('[data-photo-lightbox-previous]')) move(-1);
      else if (event.target.closest('[data-photo-lightbox-next]')) move(1);
    });

    document.addEventListener('keydown', (event) => {
      if (lightbox.hidden) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        close();
      } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        move(-1);
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        move(1);
      } else if (event.key === 'Tab') {
        const controls = [...lightbox.querySelectorAll('button:not([hidden])')].filter((control) => control.tabIndex >= 0);
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });
  });
})();
