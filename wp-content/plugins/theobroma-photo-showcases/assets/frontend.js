(() => {
  'use strict';

  document.querySelectorAll('[data-photo-showcase]').forEach((showcase) => {
    const triggers = [...showcase.querySelectorAll('[data-photo-lightbox-trigger]')];
    const lightbox = showcase.querySelector('[data-photo-lightbox]');
    if (triggers.length === 0 || !lightbox) return;

    const image = lightbox.querySelector('[data-photo-lightbox-image]');
    const caption = lightbox.querySelector('[data-photo-lightbox-caption]');
    const counter = lightbox.querySelector('[data-photo-lightbox-counter]');
    const closeButton = lightbox.querySelector('.theobroma-photo-lightbox__close');
    const previousButton = lightbox.querySelector('[data-photo-lightbox-previous]');
    const nextButton = lightbox.querySelector('[data-photo-lightbox-next]');
    document.body.appendChild(lightbox);
    let activeIndex = 0;
    let returnFocus = null;

    const render = () => {
      const trigger = triggers[activeIndex];
      image.src = trigger.dataset.photoSrc || '';
      image.alt = trigger.dataset.photoAlt || '';
      caption.textContent = trigger.dataset.photoCaption || '';
      caption.hidden = caption.textContent === '';
      counter.textContent = `${activeIndex + 1} / ${triggers.length}`;
      previousButton.hidden = triggers.length < 2;
      nextButton.hidden = triggers.length < 2;
    };

    const open = (trigger) => {
      activeIndex = triggers.indexOf(trigger);
      returnFocus = trigger;
      render();
      lightbox.hidden = false;
      lightbox.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add('theobroma-photo-lightbox-open');
      closeButton.focus({ preventScroll: true });
    };

    const close = () => {
      if (lightbox.hidden) return;
      lightbox.hidden = true;
      lightbox.setAttribute('aria-hidden', 'true');
      image.removeAttribute('src');
      document.documentElement.classList.remove('theobroma-photo-lightbox-open');
      if (returnFocus instanceof HTMLElement) returnFocus.focus({ preventScroll: true });
      returnFocus = null;
    };

    const move = (offset) => {
      activeIndex = (activeIndex + offset + triggers.length) % triggers.length;
      render();
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
