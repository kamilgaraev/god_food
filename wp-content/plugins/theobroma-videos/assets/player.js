(() => {
    const dialog = document.querySelector('[data-video-player]');
    if (!dialog) return;
    const viewport = dialog.querySelector('[data-player-viewport]');
    const template = dialog.querySelector('[data-player-slide-template]');
    const progress = dialog.querySelector('[data-video-progress]');
    const muteButton = dialog.querySelector('[data-video-mute]');
    const announcement = dialog.querySelector('[data-video-announcement]');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const soundStorageKey = 'theobroma_video_muted_v1';
    let slides = [];
    let cards = [];
    let index = -1;
    let kind = 'reels';
    let opener;
    let muted = true;
    let closing = null;
    let gesture = null;
    let holdTimer;
    let scrollFrame;
    let wheelTime = 0;
    let suppressClickUntil = 0;
    let backdropDown = false;
    let resumeAfterVisibility = false;
    let previousOverflow = {};
    const frameCallbacks = new WeakMap();
    try { muted = localStorage.getItem(soundStorageKey) !== 'false'; } catch { /* Use muted playback by default. */ }

    const currentVideo = () => slides[index]?.querySelector('video');
    const axisSize = () => kind === 'stories' ? viewport.clientWidth : viewport.clientHeight;
    const axisOffset = () => kind === 'stories' ? viewport.scrollLeft : viewport.scrollTop;
    function setMuted(value, persist = true) {
        muted = value;
        slides.forEach(slide => { slide.querySelector('video').muted = muted; });
        muteButton.setAttribute('aria-pressed', String(muted));
        muteButton.setAttribute('aria-label', muted ? 'Включить звук' : 'Выключить звук');
        if (persist) {
            try { localStorage.setItem(soundStorageKey, String(muted)); } catch { /* Keep the session preference. */ }
        }
    }
    function showLoading(slide) {
        slide.setAttribute('aria-busy', 'true');
        slide.querySelector('[data-video-loading]').hidden = false;
        slide.querySelector('[data-video-status]').textContent = '';
        slide.querySelector('[data-video-retry]').hidden = true;
    }
    function cancelFrameCallback(video) {
        const callback = frameCallbacks.get(video);
        if (callback !== undefined) {
            video.cancelVideoFrameCallback(callback);
            frameCallbacks.delete(video);
        }
    }
    function revealFrame(slide) {
        const video = slide.querySelector('video');
        const reveal = () => {
            frameCallbacks.delete(video);
            if (!video.getAttribute('src') || video.readyState < 2 || video.seeking || video.error) return;
            slide.classList.add('has-frame');
            showStatus(slide, '');
        };
        cancelFrameCallback(video);
        // Offscreen paused videos have decoded data but do not submit compositor frames.
        if (video.paused || !video.requestVideoFrameCallback) requestAnimationFrame(reveal);
        else frameCallbacks.set(video, video.requestVideoFrameCallback(reveal));
    }
    function rewindOffscreen() {
        if (!axisSize()) return;
        const visiblePosition = axisOffset() / axisSize();
        slides.forEach((slide, position) => {
            const video = slide.querySelector('video');
            if (position === index || Math.abs(position - visiblePosition) < .999 || video.currentTime <= .1 || video.seeking) return;
            slide.classList.remove('has-frame');
            video.currentTime = 0;
        });
    }
    function showStatus(slide, text, retry = false) {
        slide.setAttribute('aria-busy', 'false');
        slide.querySelector('[data-video-loading]').hidden = true;
        slide.querySelector('[data-video-status]').textContent = text;
        slide.querySelector('[data-video-retry]').hidden = !retry;
    }
    async function play(video = currentVideo()) {
        if (!video || !dialog.open || closing || document.hidden) return;
        try {
            await video.play();
        } catch (error) {
            if (video !== currentVideo() || !dialog.open || closing || error.name === 'AbortError') return;
            if (error.name === 'NotAllowedError' && !muted) {
                setMuted(true);
                announcement.textContent = 'Для автоматического воспроизведения звук выключен. Его можно включить кнопкой.';
                play(video);
                return;
            }
            showStatus(slides[index], 'Не удалось воспроизвести видео.', true);
        }
    }
    function markViewed() {
        if (kind !== 'stories' || !cards[index]?.dataset.videoKey) return;
        dialog.dispatchEvent(new CustomEvent('theobroma:story-viewed', { detail: { key: cards[index].dataset.videoKey } }));
    }
    function activate(nextIndex) {
        if (nextIndex === index || nextIndex < 0 || nextIndex >= slides.length || closing) return;
        cancelHold();
        index = nextIndex;
        slides.forEach((slide, position) => {
            const video = slide.querySelector('video');
            const active = position === index;
            slide.classList.toggle('is-active', active);
            slide.inert = !active;
            slide.setAttribute('aria-hidden', String(!active));
            if (!active) video.pause();
            if (Math.abs(position - index) <= 1) {
                if (!video.getAttribute('src')) {
                    showLoading(slide);
                    video.preload = 'auto';
                    video.src = cards[position].dataset.videoSrc;
                    video.load();
                }
            } else if (video.getAttribute('src')) {
                cancelFrameCallback(video);
                slide.classList.remove('has-frame');
                video.removeAttribute('src');
                video.load();
            }
        });
        const video = currentVideo();
        if (!slides[index].classList.contains('has-frame')) showLoading(slides[index]);
        announcement.textContent = `${kind === 'stories' ? 'Сторис' : 'Ролик'} ${index + 1} из ${slides.length}. ${cards[index].dataset.videoTitle}`;
        [...progress.children].forEach((segment, position) => {
            segment.firstElementChild.style.width = position < index ? '100%' : '0%';
        });
        play(video);
    }
    function goTo(nextIndex) {
        if (closing) return;
        if (nextIndex >= slides.length) {
            if (kind === 'stories') closePlayer();
            return;
        }
        if (nextIndex < 0) {
            currentVideo().currentTime = 0;
            play();
            return;
        }
        viewport.scrollTo({
            left: kind === 'stories' ? nextIndex * viewport.clientWidth : 0,
            top: kind === 'reels' ? nextIndex * viewport.clientHeight : 0,
            behavior: reducedMotion.matches ? 'instant' : 'smooth',
        });
    }
    function renderSlides() {
        slides = cards.map((card, position) => {
            const slide = template.content.firstElementChild.cloneNode(true);
            slide.dataset.index = position;
            const item = card.dataset;
            const video = slide.querySelector('video');
            video.muted = muted;
            video.loop = kind === 'reels';
            slide.querySelector('[data-video-heading]').textContent = item.videoTitle;
            const caption = slide.querySelector('[data-video-caption]');
            caption.textContent = item.videoDescription;
            caption.hidden = !item.videoDescription;
            const cta = slide.querySelector('[data-video-cta]');
            cta.hidden = !(item.videoButtonText && item.videoButtonUrl);
            cta.textContent = item.videoButtonText || '';
            if (!cta.hidden) cta.href = item.videoButtonUrl;
            video.addEventListener('playing', () => {
                if (video !== currentVideo() || closing) { video.pause(); return; }
                if (!slide.classList.contains('has-frame')) revealFrame(slide);
                else showStatus(slide, '');
                markViewed();
            });
            video.addEventListener('loadeddata', () => revealFrame(slide));
            video.addEventListener('seeked', () => revealFrame(slide));
            video.addEventListener('waiting', () => { if (video === currentVideo()) showLoading(slide); });
            video.addEventListener('error', () => {
                if (video.getAttribute('src')) showStatus(slide, 'Не удалось загрузить видео.', true);
            });
            video.addEventListener('timeupdate', () => {
                if (kind !== 'stories' || video !== currentVideo() || !Number.isFinite(video.duration) || video.duration <= 0) return;
                const fill = progress.children[index]?.firstElementChild;
                if (fill) fill.style.width = `${video.currentTime / video.duration * 100}%`;
            });
            video.addEventListener('ended', () => {
                if (kind === 'stories' && video === currentVideo() && !closing) goTo(index + 1);
            });
            slide.inert = true;
            return slide;
        });
        viewport.replaceChildren(...slides);
        progress.hidden = kind !== 'stories';
        progress.replaceChildren(...(kind === 'stories' ? cards.map(() => {
            const segment = document.createElement('span');
            segment.append(document.createElement('i'));
            return segment;
        }) : []));
    }
    function openPlayer({ collection, card }) {
        if (dialog.open || closing) return;
        cards = [...collection.querySelectorAll('[data-video-open]')];
        kind = collection.dataset.videoCollection;
        opener = card;
        index = -1;
        dialog.dataset.kind = kind;
        previousOverflow = { html: document.documentElement.style.overflow, body: document.body.style.overflow };
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        renderSlides();
        setMuted(muted, false);
        dialog.showModal();
        const startIndex = cards.indexOf(card);
        viewport.scrollTo({ left: kind === 'stories' ? startIndex * viewport.clientWidth : 0, top: kind === 'reels' ? startIndex * viewport.clientHeight : 0, behavior: 'instant' });
        activate(startIndex);
    }
    function closePlayer() {
        if (closing) return closing;
        if (!dialog.open) return Promise.resolve();
        cancelHold();
        slides.forEach(slide => slide.querySelector('video').pause());
        dialog.classList.add('is-closing');
        closing = new Promise(resolve => {
            dialog.addEventListener('close', resolve, { once: true });
            setTimeout(() => dialog.close(), reducedMotion.matches ? 0 : 180);
        });
        return closing;
    }
    function cancelHold(resume = false) {
        clearTimeout(holdTimer);
        dialog.classList.remove('is-holding');
        viewport.classList.remove('is-dragging');
        if (resume && gesture?.holding && gesture.wasPlaying && gesture.index === index) play();
        gesture = null;
    }
    function togglePause() {
        const video = currentVideo();
        if (!video) return;
        if (video.paused) play();
        else video.pause();
        announcement.textContent = video.paused ? 'Пауза' : 'Воспроизведение';
    }

    dialog.addEventListener('theobroma:open', event => openPlayer(event.detail));
    dialog.querySelector('[data-video-close]').addEventListener('click', closePlayer);
    muteButton.addEventListener('click', () => setMuted(!muted));
    dialog.addEventListener('cancel', event => { event.preventDefault(); closePlayer(); });
    dialog.addEventListener('pointerdown', event => { backdropDown = event.target === dialog; });
    dialog.addEventListener('click', event => { if (event.target === dialog && backdropDown) closePlayer(); });
    dialog.addEventListener('close', () => {
        cancelHold();
        cancelAnimationFrame(scrollFrame);
        slides.forEach(slide => {
            const video = slide.querySelector('video');
            video.pause();
            cancelFrameCallback(video);
            video.removeAttribute('src');
            video.load();
        });
        viewport.replaceChildren();
        progress.replaceChildren();
        slides = [];
        cards = [];
        index = -1;
        closing = null;
        resumeAfterVisibility = false;
        dialog.classList.remove('is-closing');
        document.documentElement.style.overflow = previousOverflow.html;
        document.body.style.overflow = previousOverflow.body;
        opener?.focus({ preventScroll: true });
    });
    viewport.addEventListener('scroll', () => {
        cancelAnimationFrame(scrollFrame);
        scrollFrame = requestAnimationFrame(() => {
            if (!dialog.open || closing || !axisSize() || (gesture?.type === 'mouse' && gesture.moved)) return;
            activate(Math.round(axisOffset() / axisSize()));
            rewindOffscreen();
        });
    }, { passive: true });
    new ResizeObserver(() => {
        if (!dialog.open || index < 0 || closing) return;
        viewport.scrollTo({ left: kind === 'stories' ? index * viewport.clientWidth : 0, top: kind === 'reels' ? index * viewport.clientHeight : 0, behavior: 'instant' });
    }).observe(viewport);
    viewport.addEventListener('wheel', event => {
        if (kind !== 'stories' || Math.abs(event.deltaX) >= Math.abs(event.deltaY)) return;
        event.preventDefault();
        if (Date.now() - wheelTime > 350 && Math.abs(event.deltaY) > 5) {
            wheelTime = Date.now();
            goTo(index + Math.sign(event.deltaY));
        }
    }, { passive: false });
    viewport.addEventListener('pointerdown', event => {
        if (event.button !== 0 || event.target.closest('[data-video-ui], a')) return;
        gesture = { id: event.pointerId, type: event.pointerType, x: event.clientX, y: event.clientY, offset: axisOffset(), index, started: Date.now(), holding: false, moved: false, wasPlaying: !currentVideo().paused };
        if (event.pointerType === 'mouse') viewport.setPointerCapture(event.pointerId);
        holdTimer = setTimeout(() => {
            if (!gesture || gesture.moved) return;
            gesture.holding = true;
            currentVideo()?.pause();
            dialog.classList.add('is-holding');
        }, 220);
    });
    viewport.addEventListener('pointermove', event => {
        if (!gesture || gesture.id !== event.pointerId) return;
        const dx = event.clientX - gesture.x;
        const dy = event.clientY - gesture.y;
        if (Math.hypot(dx, dy) <= 10) return;
        clearTimeout(holdTimer);
        if (gesture.holding) {
            dialog.classList.remove('is-holding');
            if (gesture.wasPlaying) play();
            gesture.holding = false;
        }
        gesture.moved = true;
        if (gesture.type === 'mouse') {
            viewport.classList.add('is-dragging');
            event.preventDefault();
            if (kind === 'stories') viewport.scrollLeft = gesture.offset - dx;
            else viewport.scrollTop = gesture.offset - dy;
        }
    });
    viewport.addEventListener('pointerup', event => {
        if (!gesture || gesture.id !== event.pointerId) return;
        const finished = gesture;
        if (finished.holding || finished.moved) suppressClickUntil = Date.now() + 400;
        if (finished.moved && finished.type === 'mouse') {
            viewport.classList.remove('is-dragging');
            const distance = kind === 'stories' ? event.clientX - finished.x : event.clientY - finished.y;
            const speed = Math.abs(distance) / Math.max(1, Date.now() - finished.started);
            const skip = Math.abs(distance) > Math.min(80, axisSize() * .2) || (Math.abs(distance) > 20 && speed > .3);
            goTo(finished.index + (skip ? (distance < 0 ? 1 : -1) : 0));
        }
        cancelHold(true);
    });
    viewport.addEventListener('pointercancel', () => { suppressClickUntil = Date.now() + 400; cancelHold(true); });
    viewport.addEventListener('contextmenu', event => event.preventDefault());
    viewport.addEventListener('click', event => {
        const cta = event.target.closest('[data-video-cta]');
        if (cta) {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            closePlayer().then(() => { window.location.href = cta.href; });
            return;
        }
        if (event.target.closest('[data-video-retry]')) { currentVideo().load(); play(); return; }
        if (event.target.closest('[data-video-ui]') || Date.now() < suppressClickUntil) return;
        if (kind === 'reels') togglePause();
        else if (event.detail === 0 && event.target.closest('[data-story-direction]')) {
            goTo(index + Number(event.target.closest('[data-story-direction]').dataset.storyDirection));
        } else {
            const fraction = (event.clientX - viewport.getBoundingClientRect().left) / viewport.clientWidth;
            if (fraction < .33) goTo(index - 1);
            if (fraction > .67) goTo(index + 1);
        }
    });
    dialog.addEventListener('keydown', event => {
        if (event.target.closest('a')) return;
        const next = kind === 'stories' ? 'ArrowRight' : 'ArrowDown';
        const previous = kind === 'stories' ? 'ArrowLeft' : 'ArrowUp';
        if (event.key === next || event.key === previous) {
            event.preventDefault();
            goTo(index + (event.key === next ? 1 : -1));
        }
        if (event.code === 'Space' && !event.target.closest('button')) { event.preventDefault(); togglePause(); }
        if (event.key.toLowerCase() === 'm') setMuted(!muted);
    });
    document.addEventListener('visibilitychange', () => {
        if (!dialog.open || closing) return;
        if (document.hidden) {
            resumeAfterVisibility = !currentVideo()?.paused || Boolean(gesture?.holding && gesture.wasPlaying);
            cancelHold();
            currentVideo()?.pause();
        } else if (resumeAfterVisibility) {
            resumeAfterVisibility = false;
            play();
        }
    });
    window.addEventListener('blur', () => { if (gesture) cancelHold(true); });
})();
