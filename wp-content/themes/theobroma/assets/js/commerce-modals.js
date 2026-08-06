(() => {
    const config = window.theobromaCommerce;
    const modal = document.querySelector('#commerce-modal');
    if (!config || !modal) {
        return;
    }

    const panel = modal.querySelector('.commerce-modal-panel');
    const content = modal.querySelector('.commerce-modal-content');
    const status = modal.querySelector('.commerce-modal-status');
    const closeButton = modal.querySelector('.commerce-modal-close');
    const productCache = new Map();
    let trigger = null;
    let modalType = '';
    let returnUrl = window.location.href;
    let requestController = null;

    const setCartCount = (count) => {
        document.querySelectorAll('.cart-count').forEach((element) => {
            element.textContent = `(${Number(count) || 0})`;
        });
    };

    const setLoading = (label = 'Загрузка…') => {
        status.textContent = label;
        status.hidden = false;
        content.replaceChildren();
        panel.scrollTop = 0;
    };

    const showModal = (type, label) => {
        modalType = type;
        modal.dataset.commerceType = type;
        panel.setAttribute('aria-label', label);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('commerce-modal-open');
        document.body.classList.add('commerce-modal-open');
        window.requestAnimationFrame(() => modal.classList.add('is-open'));
        closeButton.focus({ preventScroll: true });
    };

    const hideModal = ({ restoreFocus = true } = {}) => {
        requestController?.abort();
        requestController = null;
        modal.classList.remove('is-open');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('commerce-modal-open');
        document.body.classList.remove('commerce-modal-open');
        content.replaceChildren();
        status.hidden = false;
        delete modal.dataset.commerceType;
        modalType = '';
        if (restoreFocus && trigger instanceof HTMLElement) {
            trigger.focus({ preventScroll: true });
        }
        trigger = null;
    };

    const closeModal = () => {
        if (modalType === 'product' && window.history.state?.theobromaDirectProduct) {
            window.location.assign(config.shopUrl);
            return;
        }
        if (modalType === 'product' && window.history.state?.theobromaModal === 'product') {
            window.history.back();
            return;
        }
        hideModal();
    };

    const request = async (body) => {
        requestController?.abort();
        requestController = new AbortController();
        const response = await window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({ nonce: config.nonce, ...body }),
            signal: requestController.signal,
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    };

    const activateInjectedCheckout = () => {
        if (!window.jQuery) {
            return;
        }
        const $ = window.jQuery;
        $(document.body).trigger('country_to_state_changed');
        $(document.body).trigger('update_checkout');
    };

    const bindProductGallery = () => {
        const mainImage = content.querySelector('[data-product-main-image]');
        if (!mainImage) {
            return;
        }
        content.querySelectorAll('[data-product-gallery-image]').forEach((button) => {
            button.addEventListener('click', () => {
                const source = button.dataset.productGalleryImage;
                if (!source) {
                    return;
                }
                mainImage.src = source;
                mainImage.srcset = '';
                content.querySelectorAll('[data-product-gallery-image]').forEach((item) => item.classList.toggle('is-active', item === button));
            });
        });
    };

    const mountProduct = (product) => {
        if (!product) {
            throw new Error('Product markup is missing');
        }
        const productClone = document.importNode(product, true);
        productClone.querySelectorAll('.product-detail-back,.product-detail-close').forEach((element) => element.remove());
        content.replaceChildren(productClone);
        content.className = 'commerce-modal-content commerce-modal-product';
        status.hidden = true;
        bindProductGallery();
        panel.scrollTop = 0;
    };

    const renderProduct = (html) => {
        const documentFragment = new DOMParser().parseFromString(html, 'text/html');
        mountProduct(documentFragment.querySelector('.product-detail-page'));
    };

    const openProduct = async (url, { pushHistory = true, opener = null } = {}) => {
        if (opener) {
            trigger = opener;
        }
        if (pushHistory) {
            returnUrl = window.location.href;
            window.history.pushState({ theobromaModal: 'product', returnUrl }, '', url);
        }
        showModal('product', 'Информация о товаре');
        setLoading('Загрузка товара…');
        try {
            if (!productCache.has(url)) {
                const response = await window.fetch(url, { credentials: 'same-origin', headers: { 'X-Theobroma-Modal': 'product' } });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                productCache.set(url, await response.text());
            }
            renderProduct(productCache.get(url));
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            window.location.assign(url);
        }
    };

    const renderCart = (payload) => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = payload.html;
        content.replaceChildren(...wrapper.childNodes);
        content.className = 'commerce-modal-content commerce-modal-cart';
        status.hidden = true;
        setCartCount(payload.count);
        panel.scrollTop = 0;
        activateInjectedCheckout();
    };

    const openCart = async (opener = null) => {
        if (opener) {
            trigger = opener;
        }
        showModal('cart', 'Корзина и оформление заказа');
        setLoading('Загрузка корзины…');
        try {
            const response = await request({ action: 'theobroma_cart_modal' });
            if (!response.success) {
                throw new Error(response.data?.message || 'Cart request failed');
            }
            renderCart(response.data);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            window.location.assign(config.cartUrl);
        }
    };

    const updateCart = async (cartKey, quantity, clear = false) => {
        content.classList.add('is-updating');
        try {
            const response = await request({
                action: 'theobroma_cart_update',
                cart_key: cartKey || '',
                quantity: String(quantity ?? 0),
                clear: clear ? '1' : '0',
            });
            if (!response.success) {
                throw new Error(response.data?.message || 'Cart update failed');
            }
            renderCart(response.data);
            if (window.jQuery) {
                window.jQuery(document.body).trigger('wc_fragment_refresh');
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                content.classList.remove('is-updating');
            }
        }
    };

    const addProduct = async (form) => {
        const button = form.querySelector('[name="add-to-cart"],.single_add_to_cart_button');
        const formData = new FormData(form);
        const productId = formData.get('add-to-cart') || button?.value;
        if (!productId || !config.wcAjaxUrl) {
            form.submit();
            return;
        }
        button?.classList.add('loading');
        const payload = new URLSearchParams();
        formData.forEach((value, key) => payload.append(key, String(value)));
        payload.set('product_id', String(productId));
        try {
            const response = await window.fetch(config.wcAjaxUrl.replace('%%endpoint%%', 'add_to_cart'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: payload,
            });
            const result = await response.json();
            if (result.error && result.product_url) {
                window.location.assign(result.product_url);
                return;
            }
            Object.entries(result.fragments || {}).forEach(([selector, markup]) => {
                document.querySelectorAll(selector).forEach((element) => {
                    const replacement = document.createRange().createContextualFragment(markup).firstElementChild;
                    if (replacement) {
                        element.replaceWith(replacement.cloneNode(true));
                    }
                });
            });
            if (window.jQuery) {
                window.jQuery(document.body).trigger('added_to_cart', [result.fragments, result.cart_hash, window.jQuery(button)]);
            }
            if (window.history.state?.theobromaModal === 'product') {
                window.history.replaceState(null, '', returnUrl);
            }
            await openCart(button);
        } catch (error) {
            form.submit();
        } finally {
            button?.classList.remove('loading');
        }
    };

    document.addEventListener('click', (event) => {
        const close = event.target.closest('[data-commerce-close]');
        if (close) {
            event.preventDefault();
            closeModal();
            return;
        }

        const cartLink = event.target.closest('.floating-actions a:first-child');
        if (cartLink && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
            event.preventDefault();
            openCart(cartLink);
            return;
        }

        const productLink = event.target.closest('[data-product-modal-link],ul.products li.product a.woocommerce-LoopProduct-link,.product > a,.product-related a[href*="/product/"]');
        if (productLink && productLink.href && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0) {
            event.preventDefault();
            openProduct(productLink.href, { opener: productLink });
            return;
        }

        const quantityButton = event.target.closest('[data-cart-quantity]');
        if (quantityButton) {
            const product = quantityButton.closest('[data-cart-key]');
            updateCart(product?.dataset.cartKey, Number(quantityButton.dataset.cartQuantity));
            return;
        }

        if (event.target.closest('[data-cart-clear]')) {
            updateCart('', 0, true);
            return;
        }

        const galleryButton = event.target.closest('[data-product-gallery-image]');
        if (galleryButton) {
            const mainImage = content.querySelector('[data-product-main-image]');
            if (mainImage) {
                mainImage.src = galleryButton.dataset.productGalleryImage;
                mainImage.srcset = '';
            }
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('.commerce-modal-product form.cart');
        if (!form) {
            return;
        }
        event.preventDefault();
        addProduct(form);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
        if (event.key !== 'Tab' || modal.hidden) {
            return;
        }
        const focusable = Array.from(panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter((element) => element.getClientRects().length);
        if (!focusable.length) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.addEventListener('popstate', () => {
        if (window.history.state?.theobromaModal === 'product') {
            openProduct(window.location.href, { pushHistory: false });
            return;
        }
        if (!modal.hidden) {
            hideModal({ restoreFocus: false });
        }
    });

    const directProduct = document.querySelector('.product-modal-source .product-detail-page');
    if (directProduct) {
        window.history.replaceState({
            theobromaModal: 'product',
            theobromaDirectProduct: true,
            returnUrl: config.shopUrl,
        }, '', window.location.href);
        showModal('product', 'Информация о товаре');
        mountProduct(directProduct);
    }
})();
