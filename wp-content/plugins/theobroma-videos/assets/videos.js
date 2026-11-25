(() => {
    const dialog = document.querySelector('[data-video-player]');
    if (!dialog) return;
    const storageKey = 'theobroma_viewed_stories_v1';
    let viewedStories = new Set();
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
        if (Array.isArray(saved)) viewedStories = new Set(saved.filter(key => typeof key === 'string').slice(-200));
    } catch {
        // Viewed state also works for this page when storage is unavailable.
    }
    function updateViewedStories() {
        document.querySelectorAll('[data-video-collection="stories"] [data-video-open]').forEach(card => {
            const viewed = viewedStories.has(card.dataset.videoKey);
            card.classList.toggle('is-viewed', viewed);
            card.setAttribute('aria-label', `${viewed ? 'Просмотрено. ' : ''}Смотреть ${card.dataset.videoTitle || 'сторис'}`);
        });
    }
    updateViewedStories();
    dialog.addEventListener('theobroma:story-viewed', event => {
        viewedStories.add(event.detail.key);
        try {
            localStorage.setItem(storageKey, JSON.stringify([...viewedStories].slice(-200)));
        } catch {
            // Keep the in-page state if storage is blocked or full.
        }
        updateViewedStories();
    });
    document.querySelectorAll('[data-video-collection]').forEach(collection => {
        const track = collection.querySelector('.theobroma-videos__track');
        const arrows = collection.querySelector('.theobroma-videos__arrows');
        const previous = arrows.querySelector('[data-video-scroll="-1"]');
        const next = arrows.querySelector('[data-video-scroll="1"]');
        function updateArrows() {
            const maximum = track.scrollWidth - track.clientWidth;
            arrows.hidden = maximum <= 2;
            previous.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft >= maximum - 2;
        }
        new ResizeObserver(updateArrows).observe(track);
        track.addEventListener('scroll', updateArrows, { passive: true });
        document.fonts.ready.then(updateArrows);
        updateArrows();
        collection.addEventListener('click', event => {
            const card = event.target.closest('[data-video-open]');
            if (card) dialog.dispatchEvent(new CustomEvent('theobroma:open', { detail: { collection, card } }));
            const scroll = event.target.closest('[data-video-scroll]');
            if (scroll) {
                track.scrollBy({ left: track.clientWidth * .8 * Number(scroll.dataset.videoScroll), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            }
        });
    });
})();
