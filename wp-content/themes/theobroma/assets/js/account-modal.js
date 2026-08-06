(() => {
  const modal = document.querySelector('#account-modal');
  if (!modal) return;

  const panel = modal.querySelector('.account-modal-panel');
  const emailStep = modal.querySelector('[data-account-email-step]');
  const emailInput = modal.querySelector('#account-email');
  const emailError = modal.querySelector('[data-account-error]');
  const loginForm = modal.querySelector('[data-account-login]');
  const registerForm = modal.querySelector('[data-account-register]');
  const loginEmail = modal.querySelector('#account-login-email');
  const registerEmail = modal.querySelector('#account-register-email');
  let previousFocus = null;

  const setView = (view) => {
    emailStep.hidden = view !== 'email';
    loginForm.hidden = view !== 'login';
    registerForm.hidden = view !== 'register';
    const target = view === 'email' ? emailInput : modal.querySelector(view === 'login' ? '#account-login-password' : '#account-register-password');
    window.setTimeout(() => target?.focus(), 0);
  };

  const open = () => {
    previousFocus = document.activeElement;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('account-modal-open');
    requestAnimationFrame(() => modal.classList.add('is-open'));

    const notices = document.querySelector('.woocommerce-error, .woocommerce-message');
    if (notices) {
      modal.querySelector('.account-modal-notices').replaceChildren(notices.cloneNode(true));
      setView('login');
      if (loginEmail && emailInput.value) loginEmail.value = emailInput.value;
    } else {
      setView('email');
    }
  };

  const close = () => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('account-modal-open');
    window.setTimeout(() => { modal.hidden = true; }, 200);
    previousFocus?.focus?.();
  };

  const continueWithEmail = () => {
    if (!emailInput.checkValidity()) {
      emailError.hidden = false;
      emailInput.focus();
      return;
    }
    emailError.hidden = true;
    loginEmail.value = emailInput.value;
    registerEmail.value = emailInput.value;
    setView('login');
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-account-trigger]');
    if (trigger) {
      event.preventDefault();
      open();
      return;
    }
    if (event.target.closest('[data-account-close]')) close();
    if (event.target.closest('[data-account-continue]')) continueWithEmail();
    if (event.target.closest('[data-account-show-register]')) setView('register');
    if (event.target.closest('[data-account-show-login]')) setView('login');
  });

  emailInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      continueWithEmail();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (modal.hidden) return;
    if (event.key === 'Escape') close();
    if (event.key !== 'Tab') return;
    const focusable = [...panel.querySelectorAll('button:not([hidden]), a[href], input:not([hidden])')].filter((element) => !element.closest('[hidden]'));
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  if (document.body.classList.contains('woocommerce-account')) open();
})();
