(() => {
  'use strict';
  const root = document.querySelector('.corporate-redesign');
  if (!root) return;
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
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  root.querySelectorAll('.cg-faq details').forEach(details => {
    const summary = details.querySelector('summary');
    let animation = null;
    let expanded = details.open;
    const finish = () => {
      details.open = expanded;
      details.style.removeProperty('height');
      details.style.removeProperty('overflow');
      delete details.dataset.expanded;
      animation = null;
    };
    summary.addEventListener('click', event => {
      if (typeof details.animate !== 'function') return;
      event.preventDefault();
      const startHeight = details.getBoundingClientRect().height;
      expanded = !expanded;
      if (animation) {
        animation.onfinish = null;
        animation.cancel();
      }
      if (reducedMotion.matches) { finish(); return; }
      details.open = true;
      details.dataset.expanded = String(expanded);
      details.style.removeProperty('height');
      const endHeight = expanded ? details.getBoundingClientRect().height : summary.getBoundingClientRect().height;
      details.style.overflow = 'hidden';
      animation = details.animate(
        [{ height: `${startHeight}px` }, { height: `${endHeight}px` }],
        { duration: 280, easing: 'cubic-bezier(.2,.65,.3,1)' }
      );
      animation.onfinish = finish;
    });
    reducedMotion.addEventListener('change', () => {
      if (!reducedMotion.matches || !animation) return;
      animation.cancel();
      finish();
    });
  });
  if (!reducedMotion.matches && 'IntersectionObserver' in window && typeof Element.prototype.animate === 'function') {
    const activeAnimations = new Set();
    const reveal = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        reveal.unobserve(entry.target);
        if (reducedMotion.matches || entry.target.contains(document.activeElement)) return;
        const siblings = Array.from(entry.target.parentElement.children);
        const stagger = entry.target.matches('.cg-gift,.cg-detail-grid article') ? (siblings.indexOf(entry.target) % 3) * 70 : 0;
        const animation = entry.target.animate([
          { opacity:0, transform:'translateY(18px)' },
          { opacity:1, transform:'translateY(0)' },
        ], { duration:500, delay:stagger, easing:'cubic-bezier(.2,.65,.3,1)', fill:'backwards' });
        activeAnimations.add(animation);
        animation.finished.then(() => activeAnimations.delete(animation), () => activeAnimations.delete(animation));
      });
    }, { threshold:0, rootMargin:'0px 0px -32px 0px' });
    root.querySelectorAll('.cg-gift,.cg-details-intro,.cg-detail-grid article,.cg-gallery h2,.cg-gallery-track,.cg-season,.cg-faq details,.cg-reviews h2,.cg-review-track,.cg-request-layout > div').forEach(element => reveal.observe(element));
    reducedMotion.addEventListener('change', event => {
      if (!event.matches) return;
      reveal.disconnect();
      activeAnimations.forEach(animation => animation.cancel());
      activeAnimations.clear();
    });
  }
})();
