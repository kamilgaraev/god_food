(() => {
    const dialog = document.querySelector('[data-video-player]');
    if (!dialog) return;
    const video = dialog.querySelector('[data-video-element]');
    const heading = dialog.querySelector('[data-video-heading]');
    const caption = dialog.querySelector('[data-video-caption]');
    const status = dialog.querySelector('[data-video-status]');
    const counter = dialog.querySelector('[data-video-counter]');
    const cta = dialog.querySelector('[data-video-cta]');
    const pause = dialog.querySelector('[data-video-pause]');
    const mute = dialog.querySelector('[data-video-mute]');
    const progress = dialog.querySelector('[data-video-progress]');
    const retry = dialog.querySelector('[data-video-retry]');
    const stage = dialog.querySelector('.theobroma-player__stage');
    let cards = [];
    let index = 0;
    let opener;
    let playbackId = 0;
    let previousOverflow = '';
    let touchStart = null;
    let suppressClickUntil = 0;

    function updatePause() {
        const paused = video.paused;
        pause.textContent = paused ? 'Смотреть' : 'Пауза';
        pause.setAttribute('aria-label', paused ? 'Воспроизвести видео' : 'Приостановить видео');
    }
    function updateMute() {
        mute.textContent = video.muted ? 'Без звука' : 'Со звуком';
        mute.setAttribute('aria-label', video.muted ? 'Включить звук' : 'Выключить звук');
        mute.setAttribute('aria-pressed', String(video.muted));
    }
    function play() {
        const currentId = playbackId;
        video.play().catch((error) => {
            if (!dialog.open || currentId !== playbackId || error.name === 'AbortError') return;
            status.textContent = error.name === 'NotAllowedError' ? 'Нажмите «Смотреть», чтобы запустить видео.' : 'Не удалось воспроизвести видео.';
            retry.hidden = error.name === 'NotAllowedError';
            updatePause();
        });
    }
    function showItem(nextIndex) {
        playbackId += 1;
        video.pause();
        index = (nextIndex + cards.length) % cards.length;
        const item = cards[index].dataset;
        heading.textContent = item.videoTitle || 'Видео Theobroma';
        caption.textContent = item.videoDescription || '';
        caption.hidden = !item.videoDescription;
        counter.textContent = `${index + 1} / ${cards.length}`;
        cta.hidden = !(item.videoButtonText && item.videoButtonUrl);
        cta.textContent = item.videoButtonText || '';
        if (cta.hidden) cta.removeAttribute('href');
        else cta.href = item.videoButtonUrl;
        status.textContent = 'Загружаем видео…';
        retry.hidden = true;
        progress.replaceChildren(...cards.map((card, position) => {
            const segment = document.createElement('span');
            const fill = document.createElement('i');
            fill.style.width = position < index ? '100%' : '0%';
            segment.append(fill);
            return segment;
        }));
        dialog.querySelectorAll('[data-video-direction]').forEach((button) => { button.hidden = cards.length < 2; });
        video.poster = item.videoPoster || '';
        video.src = item.videoSrc;
        video.load();
        play();
    }
    function togglePause() {
        if (video.paused) play();
        else video.pause();
    }
    function open(collection, card) {
        cards = Array.from(collection.querySelectorAll('[data-video-open]'));
        opener = card;
        previousOverflow = document.documentElement.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        dialog.dataset.kind = collection.dataset.videoCollection;
        video.muted = true;
        updateMute();
        dialog.showModal();
        showItem(cards.indexOf(card));
    }
    document.querySelectorAll('[data-video-collection]').forEach((collection) => {
        collection.addEventListener('click', (event) => {
            const card = event.target.closest('[data-video-open]');
            if (card) open(collection, card);
            const scroll = event.target.closest('[data-video-scroll]');
            if (scroll) {
                const track = collection.querySelector('.theobroma-videos__track');
                track.scrollBy({ left: track.clientWidth * 0.8 * Number(scroll.dataset.videoScroll), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            }
        });
    });
    dialog.querySelector('[data-video-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => {
        playbackId += 1;
        video.pause();
        video.removeAttribute('src');
        video.removeAttribute('poster');
        video.load();
        document.documentElement.style.overflow = previousOverflow;
        opener?.focus({ preventScroll: true });
    });
    dialog.querySelectorAll('[data-video-direction]').forEach((button) => {
        button.addEventListener('click', () => showItem(index + Number(button.dataset.videoDirection)));
    });
    dialog.addEventListener('keydown', (event) => {
        if (event.target.closest('a')) return;
        if (['ArrowDown', 'ArrowRight'].includes(event.key)) { event.preventDefault(); showItem(index + 1); }
        if (['ArrowUp', 'ArrowLeft'].includes(event.key)) { event.preventDefault(); showItem(index - 1); }
        if (event.code === 'Space' && !event.target.closest('button')) { event.preventDefault(); togglePause(); }
    });
    cta.addEventListener('click', () => dialog.close());
    pause.addEventListener('click', togglePause);
    video.addEventListener('click', () => { if (Date.now() > suppressClickUntil) togglePause(); });
    mute.addEventListener('click', () => { video.muted = !video.muted; updateMute(); });
    retry.addEventListener('click', () => showItem(index));
    video.addEventListener('play', updatePause);
    video.addEventListener('pause', updatePause);
    video.addEventListener('playing', () => { status.textContent = ''; retry.hidden = true; });
    video.addEventListener('waiting', () => { if (dialog.open) status.textContent = 'Загружаем видео…'; });
    video.addEventListener('error', () => {
        if (!dialog.open || !video.getAttribute('src')) return;
        status.textContent = 'Не удалось загрузить видео. Попробуйте ещё раз.';
        retry.hidden = false;
    });
    video.addEventListener('timeupdate', () => {
        const fill = progress.children[index]?.firstElementChild;
        if (fill && Number.isFinite(video.duration) && video.duration > 0) fill.style.width = `${video.currentTime / video.duration * 100}%`;
    });
    video.addEventListener('ended', () => {
        if (index < cards.length - 1) showItem(index + 1);
        else { status.textContent = 'Вы посмотрели все видео.'; updatePause(); }
    });
    stage.addEventListener('touchstart', (event) => {
        if (event.target.closest('button')) return;
        const touch = event.changedTouches[0];
        touchStart = { x: touch.clientX, y: touch.clientY };
    }, { passive: true });
    stage.addEventListener('touchend', (event) => {
        if (!touchStart) return;
        const touch = event.changedTouches[0];
        const dy = touch.clientY - touchStart.y;
        const dx = touch.clientX - touchStart.x;
        touchStart = null;
        if (Math.abs(dy) > 50 && Math.abs(dy) > Math.abs(dx)) {
            suppressClickUntil = Date.now() + 400;
            showItem(index + (dy < 0 ? 1 : -1));
        }
    }, { passive: true });
    stage.addEventListener('touchcancel', () => { touchStart = null; }, { passive: true });
    document.addEventListener('visibilitychange', () => { if (document.hidden && dialog.open) video.pause(); });
})();
