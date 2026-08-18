(() => {
    const config = window.theobromaCommerce;
    const modal = document.querySelector('#commerce-modal');
    if (!config || !modal) {
        return;
    }

    const panel = modal.querySelector('.commerce-modal-panel');
    const content = modal.querySelector('.commerce-modal-content');
    const checkoutFormAnchor = content.querySelector('.theobroma-checkout-anchor');
    const status = modal.querySelector('.commerce-modal-status');
    const closeButton = modal.querySelector('.commerce-modal-close');
    const productCache = new Map();
    let trigger = null;
    let modalType = '';
    let returnUrl = window.location.href;
    let requestController = null;
    let imageLightbox = null;
    let imageLightboxTrigger = null;
    const wishlistStorageKey = 'theobroma_wishlist_product_ids';
    const storedWishlistIds = (() => {
        try {
            const value = JSON.parse(window.localStorage.getItem(wishlistStorageKey) || '[]');
            return Array.isArray(value) ? value.map(Number).filter((id) => Number.isInteger(id) && id > 0) : [];
        } catch (error) {
            return [];
        }
    })();
    let wishlistIds = new Set([...(config.wishlistIds || []).map(Number), ...storedWishlistIds]);
    let wishlistSyncPromise = Promise.resolve();

    const setCartCount = (count) => {
        document.querySelectorAll('.cart-count').forEach((element) => {
            const normalizedCount = Number(count) || 0;
            element.textContent = String(normalizedCount);
            element.closest('[data-commerce-cart-open]')?.setAttribute('aria-label', `Корзина, товаров: ${normalizedCount}`);
        });
    };

    const syncCartAccessibleName = () => {
        document.querySelectorAll('[data-commerce-cart-open]').forEach((link) => {
            const count = Number(link.querySelector('.cart-count')?.textContent) || 0;
            link.setAttribute('aria-label', `Корзина, товаров: ${count}`);
        });
    };
    const cartCountObserver = new MutationObserver(syncCartAccessibleName);
    document.querySelectorAll('[data-commerce-cart-open]').forEach((link) => {
        cartCountObserver.observe(link, { childList: true, subtree: true, characterData: true });
    });

    if (window.jQuery) {
        window.jQuery(document.body).on('added_to_cart.theobromaCartCount', (_event, _fragments, _cartHash, button) => {
            setCartCount(document.querySelector('.cart-count')?.textContent || 0);
            window.requestAnimationFrame(syncCartAccessibleName);
            const opener = button?.jquery ? button.get(0) : button;
            if (opener?.matches('.home-product-card__button')) {
                openCart(opener);
            }
        });
    }

    const focusFirstModalControl = () => {
        const focusable = Array.from(panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
            .find((element) => element.getClientRects().length);
        focusable?.focus({ preventScroll: true });
    };

    const setLoading = (label = 'Загрузка…') => {
        status.textContent = label;
        status.hidden = false;
        content.replaceChildren();
        panel.scrollTop = 0;
        focusFirstModalControl();
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

    const syncWishlistUi = (root = document) => {
        document.querySelectorAll('.wishlist-count').forEach((element) => {
            element.textContent = `(${wishlistIds.size})`;
        });
        root.querySelectorAll('[data-wishlist-toggle]').forEach((button) => {
            const active = wishlistIds.has(Number(button.dataset.productId));
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
            button.setAttribute('aria-label', active ? 'Удалить из избранного' : 'Добавить в избранное');
        });
    };

    const ensureImageLightbox = () => {
        if (imageLightbox) {
            return imageLightbox;
        }
        imageLightbox = document.createElement('div');
        imageLightbox.className = 'product-image-lightbox';
        imageLightbox.dataset.productLightbox = '';
        imageLightbox.hidden = true;
        imageLightbox.setAttribute('aria-hidden', 'true');
        imageLightbox.setAttribute('role', 'dialog');
        imageLightbox.setAttribute('aria-modal', 'true');
        imageLightbox.setAttribute('aria-label', 'Увеличенное изображение товара');
        imageLightbox.innerHTML = `
            <button class="product-image-lightbox-backdrop" type="button" data-product-lightbox-close tabindex="-1" aria-label="Закрыть увеличенное изображение"></button>
            <figure><img data-product-lightbox-image src="" alt=""></figure>
            <button class="product-image-lightbox-close" type="button" data-product-lightbox-close aria-label="Закрыть увеличенное изображение"></button>
        `;
        panel.appendChild(imageLightbox);
        return imageLightbox;
    };

    const openImageLightbox = (button) => {
        const sourceImage = button.querySelector('[data-product-main-image]');
        if (!sourceImage) {
            return;
        }
        const lightbox = ensureImageLightbox();
        const image = lightbox.querySelector('[data-product-lightbox-image]');
        image.src = sourceImage.currentSrc || sourceImage.src;
        image.alt = sourceImage.alt;
        imageLightboxTrigger = button;
        lightbox.hidden = false;
        lightbox.setAttribute('aria-hidden', 'false');
        lightbox.querySelector('.product-image-lightbox-close').focus({ preventScroll: true });
    };

    const closeImageLightbox = ({ restoreFocus = true } = {}) => {
        if (!imageLightbox || imageLightbox.hidden) {
            return false;
        }
        imageLightbox.hidden = true;
        imageLightbox.setAttribute('aria-hidden', 'true');
        imageLightbox.querySelector('[data-product-lightbox-image]').removeAttribute('src');
        if (restoreFocus && imageLightboxTrigger instanceof HTMLElement) {
            imageLightboxTrigger.focus({ preventScroll: true });
        }
        imageLightboxTrigger = null;
        return true;
    };

    const hideModal = ({ restoreFocus = true } = {}) => {
        requestController?.abort();
        requestController = null;
        closeImageLightbox({ restoreFocus: false });
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

    const backgroundRequest = async (body) => {
        const response = await window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({ nonce: config.nonce, ...body }),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    };

    const persistWishlist = () => {
        const ids = [...wishlistIds];
        window.localStorage.setItem(wishlistStorageKey, JSON.stringify(ids));
        syncWishlistUi();
        if (config.wishlistLoggedIn) {
            wishlistSyncPromise = backgroundRequest({ action: 'theobroma_wishlist_save', ids: JSON.stringify(ids) })
                .then((response) => {
                    if (!response.success) throw new Error(response.data?.message || 'Wishlist save failed');
                    wishlistIds = new Set((response.data.ids || []).map(Number));
                    window.localStorage.setItem(wishlistStorageKey, JSON.stringify([...wishlistIds]));
                    syncWishlistUi();
                })
                .catch(() => {});
        }
        return wishlistSyncPromise;
    };

    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[character]);

    const formatWishlistPrice = (value) => String(value).replace(/\s*р\.?$/iu, ' р.');

    const renderWishlist = (items) => {
        const cards = items.map((item) => `
            <article class="commerce-wishlist-product" data-wishlist-product="${Number(item.id)}">
                <a class="commerce-wishlist-thumb" href="${escapeHtml(item.url)}" data-product-modal-link><img src="${escapeHtml(item.image)}" alt=""></a>
                <h3><a href="${escapeHtml(item.url)}" data-product-modal-link>${escapeHtml(item.title)}</a></h3>
                <strong>${escapeHtml(formatWishlistPrice(item.price))}</strong>
                <button type="button" data-wishlist-remove="${Number(item.id)}" aria-label="Удалить из избранного"></button>
            </article>
        `).join('');
        content.innerHTML = items.length ? `
            <section class="commerce-wishlist">
                <header><h2>Избранное:</h2><button type="button" data-wishlist-clear>Очистить</button></header>
                <div class="commerce-wishlist-products">${cards}</div>
            </section>
        ` : `
            <section class="commerce-wishlist commerce-wishlist--empty">
                <div class="commerce-wishlist-empty">
                    <button type="button" class="commerce-cart-empty-close" data-commerce-close aria-label="Закрыть"></button>
                    <p>Пожалуйста, добавьте товары в избранное</p>
                </div>
            </section>
        `;
        content.className = 'commerce-modal-content commerce-modal-wishlist';
        status.hidden = true;
        panel.scrollTop = 0;
        syncWishlistUi(content);
    };

    const openWishlist = async (opener = null) => {
        if (opener) trigger = opener;
        showModal('wishlist', 'Избранные товары');
        setLoading('Загрузка избранного…');
        try {
            await wishlistSyncPromise;
            const response = await request({ action: 'theobroma_wishlist_items', ids: JSON.stringify([...wishlistIds]) });
            if (!response.success) throw new Error(response.data?.message || 'Wishlist request failed');
            wishlistIds = new Set((response.data.ids || []).map(Number));
            window.localStorage.setItem(wishlistStorageKey, JSON.stringify([...wishlistIds]));
            renderWishlist(response.data.items || []);
            syncWishlistUi();
            focusFirstModalControl();
        } catch (error) {
            if (error.name !== 'AbortError') {
                renderWishlist([]);
                focusFirstModalControl();
            }
        }
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
        const defaultImage = {
            src: mainImage.currentSrc || mainImage.src,
            srcset: mainImage.srcset,
            sizes: mainImage.sizes,
        };
        const showImage = ({ src = '', srcset = '', sizes = '' } = {}) => {
            if (!src) {
                return;
            }
            mainImage.src = src;
            mainImage.srcset = srcset;
            mainImage.sizes = sizes;
            content.querySelectorAll('[data-product-gallery-image]').forEach((item) => item.classList.remove('is-active'));
        };
        content.querySelectorAll('[data-product-gallery-image]').forEach((button) => {
            button.addEventListener('click', () => {
                const source = button.dataset.productGalleryImage;
                if (!source) {
                    return;
                }
                showImage({ src: source });
                content.querySelectorAll('[data-product-gallery-image]').forEach((item) => item.classList.toggle('is-active', item === button));
            });
        });
        const variationForm = content.querySelector('form.variations_form');
        if (variationForm && window.jQuery) {
            window.jQuery(variationForm)
                .off('.theobromaGallery')
                .on('found_variation.theobromaGallery', (_event, variation) => {
                    const image = variation?.image || {};
                    showImage({
                        src: image.full_src || image.src || '',
                        srcset: image.srcset || '',
                        sizes: image.sizes || '',
                    });
                })
                .on('reset_data.theobromaGallery hide_variation.theobromaGallery', () => {
                    showImage(defaultImage);
                    content.querySelector('[data-product-gallery-image]')?.classList.add('is-active');
                });
        }
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
        syncWishlistUi(content);
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
            focusFirstModalControl();
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
        const injectedCheckout = wrapper.querySelector('form.checkout');
        if (injectedCheckout && checkoutFormAnchor) {
            [...checkoutFormAnchor.attributes].forEach(({ name }) => checkoutFormAnchor.removeAttribute(name));
            [...injectedCheckout.attributes].forEach(({ name, value }) => checkoutFormAnchor.setAttribute(name, value));
            checkoutFormAnchor.classList.add('theobroma-checkout-anchor');
            checkoutFormAnchor.setAttribute('novalidate', 'novalidate');
            checkoutFormAnchor.replaceChildren(...injectedCheckout.childNodes);
            injectedCheckout.replaceWith(checkoutFormAnchor);
        }
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
            focusFirstModalControl();
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
            if (Number(response.data.count) === 0) focusFirstModalControl();
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
        const variationId = Number(formData.get('variation_id') || 0);
        payload.set('product_id', String(variationId > 0 ? variationId : productId));
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
        if (event.target.closest('[data-product-lightbox-close]')) {
            event.preventDefault();
            closeImageLightbox();
            return;
        }

        const imageZoomButton = event.target.closest('[data-product-image-zoom]');
        if (imageZoomButton) {
            event.preventDefault();
            openImageLightbox(imageZoomButton);
            return;
        }

        const close = event.target.closest('[data-commerce-close]');
        if (close) {
            event.preventDefault();
            closeModal();
            return;
        }

        const cartLink = event.target.closest('[data-commerce-cart-open],.floating-actions a:first-child');
        if (cartLink && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
            event.preventDefault();
            openCart(cartLink);
            return;
        }

        const wishlistLink = event.target.closest('[data-wishlist-open]');
        if (wishlistLink && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
            event.preventDefault();
            openWishlist(wishlistLink);
            return;
        }

        const wishlistToggle = event.target.closest('[data-wishlist-toggle]');
        if (wishlistToggle) {
            event.preventDefault();
            const productId = Number(wishlistToggle.dataset.productId);
            if (wishlistIds.has(productId)) wishlistIds.delete(productId);
            else if (productId > 0) wishlistIds.add(productId);
            persistWishlist();
            return;
        }

        const wishlistRemove = event.target.closest('[data-wishlist-remove]');
        if (wishlistRemove) {
            wishlistIds.delete(Number(wishlistRemove.dataset.wishlistRemove));
            persistWishlist().then(() => openWishlist(trigger));
            return;
        }

        if (event.target.closest('[data-wishlist-clear]')) {
            wishlistIds.clear();
            persistWishlist().then(() => {
                renderWishlist([]);
                focusFirstModalControl();
            });
            return;
        }

        const productLink = event.target.closest('[data-product-modal-link],ul.products li.product a.woocommerce-LoopProduct-link,.product > a:not(.add_to_cart_button),.product-related a[href*="/product/"]');
        if (productLink && !productLink.matches('.add_to_cart_button') && productLink.href && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0) {
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
        if (event.key === 'Escape' && closeImageLightbox()) {
            event.preventDefault();
            return;
        }

        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
        if (event.key === 'Tab' && imageLightbox && !imageLightbox.hidden) {
            event.preventDefault();
            imageLightbox.querySelector('.product-image-lightbox-close').focus({ preventScroll: true });
            return;
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
            const opener = trigger;
            hideModal({ restoreFocus: false });
            window.requestAnimationFrame(() => {
                if (opener instanceof HTMLElement && opener.isConnected) {
                    opener.focus({ preventScroll: true });
                }
            });
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
        focusFirstModalControl();
        directProduct.closest('.product-modal-source')?.remove();
    }
    syncWishlistUi();
    window.localStorage.setItem(wishlistStorageKey, JSON.stringify([...wishlistIds]));
    if (config.wishlistLoggedIn && storedWishlistIds.some((id) => !(config.wishlistIds || []).map(Number).includes(id))) {
        persistWishlist();
    }
})();
