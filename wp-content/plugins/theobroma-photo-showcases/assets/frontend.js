(() => {
  'use strict';

  document.querySelectorAll('[data-photo-showcase]').forEach((showcase) => {
    const triggers = [...showcase.querySelectorAll('[data-photo-lightbox-trigger]')];
    const lightbox = showcase.querySelector('[data-photo-lightbox]');
    if (triggers.length === 0 || !lightbox) return;

    const image = lightbox.querySelector('[data-photo-lightbox-image]');
    const caption = lightbox.querySelector('[data-photo-lightbox-caption]');
    const panel = lightbox.querySelector('.theobroma-photo-lightbox__panel');
    const closeButton = lightbox.querySelector('.theobroma-photo-lightbox__close');
    const previousButton = lightbox.querySelector('[data-photo-lightbox-previous]');
    const nextButton = lightbox.querySelector('[data-photo-lightbox-next]');
    document.body.appendChild(lightbox);
    let activeIndex = 0;
    let returnFocus = null;

    const alignNavigation = () => {
      if (lightbox.hidden || !image.complete || !panel) return;
      const imageBox = image.getBoundingClientRect();
      const panelBox = panel.getBoundingClientRect();
      if (imageBox.width === 0) return;
      panel.style.setProperty('--photo-nav-previous-x', `${imageBox.left - panelBox.left}px`);
      panel.style.setProperty('--photo-nav-next-x', `${imageBox.right - panelBox.left}px`);
    };

    const render = () => {
      const trigger = triggers[activeIndex];
      image.src = trigger.dataset.photoSrc || '';
      image.alt = trigger.dataset.photoAlt || '';
      caption.textContent = trigger.dataset.photoCaption || '';
      caption.hidden = caption.textContent === '';
      previousButton.hidden = triggers.length < 2;
      nextButton.hidden = triggers.length < 2;
      window.requestAnimationFrame(alignNavigation);
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
    image.addEventListener('load', alignNavigation);
    window.addEventListener('resize', alignNavigation, { passive: true });
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
