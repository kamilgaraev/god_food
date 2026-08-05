(() => {
    const updateHeader = () => {
        document.body.classList.toggle('nav-sticky', window.scrollY > 40);
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    const menuToggle = document.querySelector('.menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    const menuClose = document.querySelector('.mobile-menu-close');

    const setMenuOpen = (open) => {
        if (!menuToggle || !mobileMenu) {
            return;
        }
        document.body.classList.toggle('mobile-menu-open', open);
        menuToggle.setAttribute('aria-expanded', String(open));
        mobileMenu.setAttribute('aria-hidden', String(!open));
        (open ? menuClose : menuToggle)?.focus({ preventScroll: true });
    };

    menuToggle?.addEventListener('click', () => setMenuOpen(true));
    menuClose?.addEventListener('click', () => setMenuOpen(false));
    mobileMenu?.addEventListener('click', (event) => {
        if (event.target === mobileMenu || event.target.closest('a')) {
            setMenuOpen(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('mobile-menu-open')) {
            setMenuOpen(false);
        }
    });

    const cookieNotice = document.querySelector('.cookie-notice');
    const cookieButton = cookieNotice?.querySelector('button');
    const cookieKey = 'theobroma_cookie_notice_accepted';
    if (cookieNotice && window.localStorage.getItem(cookieKey) !== '1') {
        cookieNotice.hidden = false;
    }
    cookieButton?.addEventListener('click', () => {
        window.localStorage.setItem(cookieKey, '1');
        cookieNotice.hidden = true;
    });

    const revealTargets = document.querySelectorAll([
        '#catalog .section-heading h2',
        '#catalog .product',
        '.about-award',
        '.story',
        '.value',
        '.reviews .section-heading h2',
        '.review',
        '.contact-card h2',
        '.recipe-card',
        '.market-product',
        '.media-card'
    ].join(','));

    if (revealTargets.length && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        revealTargets.forEach((element, index) => {
            element.classList.add('reveal-item');
            element.style.setProperty('--reveal-delay', `${Math.min(index % 4, 3) * 70}ms`);
        });
        document.documentElement.classList.add('reveal-ready');

        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

        revealTargets.forEach((element) => revealObserver.observe(element));
    }

    const reviewGrid = document.querySelector('.review-grid');
    const reviewButtons = document.querySelectorAll('[data-review-direction]');
    let reviewOffset = 0;

    reviewButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!reviewGrid) {
                return;
            }

            const direction = Number(button.dataset.reviewDirection);
            const maxOffset = Math.max(0, reviewGrid.scrollWidth - reviewGrid.clientWidth);
            reviewOffset = Math.min(maxOffset, Math.max(0, reviewOffset + direction * 300));
            reviewGrid.style.transform = `translateX(${-reviewOffset}px)`;
        });
    });

    const parallaxChocolate = document.querySelector('.about-award');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (parallaxChocolate && !reduceMotion.matches) {
        let frame = 0;
        let targetX = 0;
        let targetY = 0;
        let currentX = 0;
        let currentY = 0;

        const renderParallax = () => {
            currentX += (targetX - currentX) * 0.12;
            currentY += (targetY - currentY) * 0.12;
            parallaxChocolate.style.transform = `translate3d(${currentX.toFixed(3)}px, ${currentY.toFixed(3)}px, 0)`;

            if (Math.abs(targetX - currentX) > 0.01 || Math.abs(targetY - currentY) > 0.01) {
                frame = window.requestAnimationFrame(renderParallax);
            } else {
                frame = 0;
            }
        };

        window.addEventListener('pointermove', (event) => {
            targetX = Math.max(-22, Math.min(22, ((event.clientX / window.innerWidth) - 0.5) * 44));
            targetY = Math.max(-22, Math.min(22, ((event.clientY / window.innerHeight) - 0.5) * 44));
            if (!frame) {
                frame = window.requestAnimationFrame(renderParallax);
            }
        }, { passive: true });

        document.documentElement.addEventListener('mouseleave', () => {
            targetX = 0;
            targetY = 0;
            if (!frame) {
                frame = window.requestAnimationFrame(renderParallax);
            }
        });
    }
})();
