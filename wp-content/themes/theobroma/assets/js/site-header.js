(() => {
    const siteHeader = document.querySelector('.site-header');
    let stickyStart = siteHeader?.offsetHeight ?? 40;

    const updateHeader = () => {
        document.body.classList.toggle('nav-sticky', window.scrollY >= stickyStart);
    };

    const measureStickyStart = () => {
        stickyStart = siteHeader?.offsetHeight ?? 40;
        updateHeader();
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
    window.addEventListener('resize', measureStickyStart, { passive: true });

    const menuToggle = document.querySelector('.menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    const menuClose = document.querySelector('.mobile-menu-close');
    const menuBackground = document.querySelectorAll('.site-header, main, .site-footer, .cookie-notice');

    const setMenuOpen = (open) => {
        if (!menuToggle || !mobileMenu) {
            return;
        }
        document.documentElement.classList.toggle('mobile-menu-open', open);
        document.body.classList.toggle('mobile-menu-open', open);
        menuToggle.setAttribute('aria-expanded', String(open));
        mobileMenu.setAttribute('aria-hidden', String(!open));
        menuBackground.forEach((element) => { element.inert = open; });
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
        if (!document.body.classList.contains('mobile-menu-open')) {
            return;
        }
        if (event.key === 'Escape') {
            setMenuOpen(false);
            return;
        }
        if (event.key === 'Tab' && mobileMenu) {
            const focusable = Array.from(mobileMenu.querySelectorAll('a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])'))
                .filter((element) => element.getClientRects().length > 0);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
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
        window.dispatchEvent(new CustomEvent('theobroma:cookie-consent'));
    });

    const sourceTextReveals = document.querySelectorAll('.source-text-reveal');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (sourceTextReveals.length && !reduceMotion.matches) {
        document.documentElement.classList.add('source-motion-ready');
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.01 });

        sourceTextReveals.forEach((element) => revealObserver.observe(element));
    }

    const deferredDecor = document.querySelectorAll('.home-decor i:nth-child(n+2)');
    const observeDeferredDecor = () => {
        if (deferredDecor.length && 'IntersectionObserver' in window) {
            const decorObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-loaded');
                        observer.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '200px 0px', threshold: 0.01 });
            deferredDecor.forEach((element) => decorObserver.observe(element));
        } else {
            deferredDecor.forEach((element) => element.classList.add('is-loaded'));
        }
    };
    if (document.readyState === 'complete') {
        observeDeferredDecor();
    } else {
        window.addEventListener('load', observeDeferredDecor, { once: true });
    }

    const reviewGrid = document.querySelector('.review-grid');
    const reviewButtons = document.querySelectorAll('[data-review-direction]');

    const initializeReviewLoop = (grid) => {
        const reviews = [...grid.querySelectorAll('.review')];
        if (reviews.length < 2) {
            return;
        }

        const cloneReviews = () => reviews.map((review) => {
            const clone = review.cloneNode(true);
            clone.setAttribute('aria-hidden', 'true');
            clone.setAttribute('inert', '');
            return clone;
        });
        const before = cloneReviews();
        const after = cloneReviews();
        const beforeFragment = document.createDocumentFragment();
        const afterFragment = document.createDocumentFragment();
        before.forEach((review) => beforeFragment.append(review));
        after.forEach((review) => afterFragment.append(review));
        grid.prepend(beforeFragment);
        grid.append(afterFragment);

        const cycleWidth = () => after[0].offsetLeft - reviews[0].offsetLeft;
        grid.scrollLeft = cycleWidth();

        const normalizePosition = () => {
            const width = cycleWidth();
            if (grid.scrollLeft < width * 0.5) {
                grid.scrollLeft += width;
            } else if (grid.scrollLeft >= width * 1.5) {
                grid.scrollLeft -= width;
            }
        };
        grid.addEventListener('scroll', normalizePosition, { passive: true });
    };

    if (reviewGrid) {
        initializeReviewLoop(reviewGrid);
    }

    reviewButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!reviewGrid) {
                return;
            }

            const direction = Number(button.dataset.reviewDirection);
            const firstReview = reviewGrid.querySelector('.review');
            const columnGap = Number.parseFloat(getComputedStyle(reviewGrid).columnGap) || 0;
            const step = (firstReview?.getBoundingClientRect().width || reviewGrid.clientWidth) + columnGap;
            reviewGrid.scrollBy({
                left: direction * step,
                behavior: reduceMotion.matches ? 'auto' : 'smooth',
            });
        });
    });

})();
