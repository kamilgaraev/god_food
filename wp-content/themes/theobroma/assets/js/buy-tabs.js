(() => {
  const tabList = document.querySelector('.buy-tabs[role="tablist"]');
  if (!tabList) return;

  const tabs = [...tabList.querySelectorAll('[role="tab"]')];
  const panels = tabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls')));

  let panelAnimation;
  let transitionId = 0;

  const activate = (tab, { focus = false, updateHash = true } = {}) => {
    const index = tabs.indexOf(tab);
    if (index < 0 || !panels[index]) return;

    const current = panels.find((panel) => panel && !panel.hidden);
    const target = panels[index];
    const id = ++transitionId;
    panelAnimation?.cancel();
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    tabs.forEach((candidate, candidateIndex) => {
      const selected = candidateIndex === index;
      candidate.setAttribute('aria-selected', String(selected));
      candidate.tabIndex = selected ? 0 : -1;
    });

    if (focus) tab.focus();
    if (updateHash && history.replaceState) {
      history.replaceState(null, '', `#${target.id}`);
    }

    const reveal = () => {
      if (id !== transitionId) return;
      panels.forEach((panel) => { if (panel) panel.hidden = panel !== target; });
      if (!reducedMotion && current !== target) {
        panelAnimation = target.animate(
          [{ opacity: 0, transform: 'translateY(24px)' }, { opacity: 1, transform: 'translateY(0)' }],
          { duration: 340, easing: 'cubic-bezier(.22,.61,.36,1)' }
        );
      }
    };

    if (!reducedMotion && current && current !== target) {
      panelAnimation = current.animate(
        [{ opacity: 1, transform: 'translateY(0)' }, { opacity: 0, transform: 'translateY(-12px)' }],
        { duration: 160, easing: 'ease-in', fill: 'forwards' }
      );
      const leaving = panelAnimation;
      leaving.finished.then(() => {
        if (id !== transitionId) return;
        current.hidden = true;
        leaving.cancel();
        reveal();
      }).catch(() => {});
    } else {
      reveal();
    }
  };

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activate(tab));
    tab.addEventListener('keydown', (event) => {
      let nextIndex = null;
      if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'Home') nextIndex = 0;
      if (event.key === 'End') nextIndex = tabs.length - 1;
      if (nextIndex === null) return;
      event.preventDefault();
      activate(tabs[nextIndex], { focus: true });
    });
  });

  const hashPanelIndex = panels.findIndex((panel) => panel && `#${panel.id}` === window.location.hash);
  activate(tabs[hashPanelIndex >= 0 ? hashPanelIndex : 0], { updateHash: hashPanelIndex >= 0 });
})();
