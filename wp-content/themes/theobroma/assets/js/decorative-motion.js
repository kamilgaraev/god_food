(() => {
    const elements = Array.from(document.querySelectorAll('img[src*="cooperation-chocolate.webp"], [data-pointer-parallax]'));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const precisePointer = window.matchMedia('(hover: hover) and (pointer: fine)');

    if (!elements.length) {
        return;
    }

    elements.forEach((element) => element.setAttribute('data-pointer-parallax', ''));

    if (reduceMotion.matches || !precisePointer.matches) {
        return;
    }

    const current = { x: 0, y: 0, rotateX: 0, rotateY: 0 };
    const target = { x: 0, y: 0, rotateX: 0, rotateY: 0 };
    let animationFrame = 0;

    const applyTransform = () => {
        elements.forEach((element) => {
            element.style.setProperty('--pointer-parallax-x', `${current.x.toFixed(3)}px`);
            element.style.setProperty('--pointer-parallax-y', `${current.y.toFixed(3)}px`);
            element.style.setProperty('--pointer-parallax-rotate-x', `${current.rotateX.toFixed(3)}deg`);
            element.style.setProperty('--pointer-parallax-rotate-y', `${current.rotateY.toFixed(3)}deg`);
        });
    };

    const animate = () => {
        const smoothing = 0.14;
        current.x += (target.x - current.x) * smoothing;
        current.y += (target.y - current.y) * smoothing;
        current.rotateX += (target.rotateX - current.rotateX) * smoothing;
        current.rotateY += (target.rotateY - current.rotateY) * smoothing;
        applyTransform();

        const unsettled = Object.keys(current).some((key) => Math.abs(target[key] - current[key]) > 0.01);
        if (unsettled) {
            animationFrame = window.requestAnimationFrame(animate);
            return;
        }

        Object.assign(current, target);
        applyTransform();
        animationFrame = 0;
    };

    const requestUpdate = () => {
        if (!animationFrame) {
            animationFrame = window.requestAnimationFrame(animate);
        }
    };

    const reset = () => {
        Object.assign(target, { x: 0, y: 0, rotateX: 0, rotateY: 0 });
        requestUpdate();
    };

    elements.forEach((element) => element.classList.add('is-pointer-parallax-ready'));

    window.addEventListener('pointermove', (event) => {
        const horizontal = Math.max(-1, Math.min(1, (event.clientX / window.innerWidth) * 2 - 1));
        const vertical = Math.max(-1, Math.min(1, (event.clientY / window.innerHeight) * 2 - 1));
        Object.assign(target, {
            x: horizontal * 12,
            y: vertical * 8,
            rotateX: vertical * -4,
            rotateY: horizontal * 4,
        });
        requestUpdate();
    }, { passive: true });

    window.addEventListener('pointerout', (event) => {
        if (!event.relatedTarget) {
            reset();
        }
    }, { passive: true });
    window.addEventListener('blur', reset);
})();
