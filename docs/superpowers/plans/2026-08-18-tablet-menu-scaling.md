# Tablet Menu Scaling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the navigation drawer scale to 88% of the viewport and retain the approved mobile visual composition through the tablet breakpoint.

**Architecture:** Keep behavior and markup unchanged. Consolidate drawer presentation into one responsive CSS block covering 320–1199px, and protect the cascade with a Playwright regression test that renders the real styles in isolation.

**Tech Stack:** CSS, Node.js, Playwright, `node:assert/strict`

## Global Constraints

- Drawer width is exactly `88vw` from 320px through 1199px.
- The 744px drawer is approximately 655px wide.
- Desktop navigation at 1200px and above is unchanged.
- Existing accessibility and interaction behavior is unchanged.
- Do not refactor unrelated theme styles.

---

### Task 1: Responsive drawer presentation

**Files:**
- Create: `scripts/verify-mobile-menu-scaling.spec.js`
- Modify: `wp-content/themes/theobroma/assets/css/home-redesign.css:288-314`
- Modify: `package.json`

**Interfaces:**
- Consumes: `.mobile-menu`, `.mobile-menu::before`, `.mobile-menu nav`, `.mobile-menu-label`, `.mobile-menu a`, and `.mobile-menu-close` from the existing header markup.
- Produces: A continuous 88vw drawer layout for viewports below 1200px and a reusable `npm run test:menu-scaling` regression command.

- [ ] **Step 1: Write the failing browser-backed CSS test**

Create a test that loads `style.css` and `home-redesign.css`, renders the existing menu classes, and for `[390, 600, 601, 744, 1199]` asserts:

```js
assert.ok(Math.abs(metrics.panelWidth - width * 0.88) <= 1);
assert.ok(Math.abs(metrics.closeRight - metrics.panelWidth) <= metrics.rootSize);
assert.equal(metrics.labelTransform, 'uppercase');
assert.equal(metrics.linkTransform, 'none');
assert.equal(metrics.scrollWidth, metrics.viewportWidth);
```

At 1200px, assert that `.mobile-menu` has `display: none`.

- [ ] **Step 2: Run the test and verify RED**

Run: `node scripts/verify-mobile-menu-scaling.spec.js`

Expected: FAIL at 744px because the rendered panel is approximately `20rem`, not `88vw`.

- [ ] **Step 3: Implement the minimal CSS correction**

Add a drawer-only `@media (max-width: 1199px)` block before the existing phone layout block:

```css
@media (max-width: 1199px) {
  .mobile-menu { z-index: 200; }
  .mobile-menu::before { width: 88vw; background: var(--home-paper); }
  .mobile-menu nav { top: 4.25rem; left: 1.75rem; width: calc(88vw - 3.5rem); }
  .mobile-menu-label { margin: 1.625rem 0 0.875rem; color: var(--home-peach); font: 500 0.5625rem/1.2 'Montserrat', Arial, sans-serif; letter-spacing: .2em; text-transform: uppercase; }
  .mobile-menu li + li { margin-top: 0.75rem; }
  .mobile-menu a { max-width: 100%; color: var(--home-ink); font-size: 1.5rem; line-height: 1.08; text-transform: none; }
  .mobile-menu-close { top: 0.875rem; left: calc(88vw - 3rem); }
}
```

Remove the duplicate capped drawer rules from the `max-width: 600px` block.

- [ ] **Step 4: Run focused and existing header checks**

Run:

```powershell
node scripts/verify-mobile-menu-scaling.spec.js
npm run test:header
```

Expected: both commands PASS.

- [ ] **Step 5: Commit the implementation**

```powershell
git add docs/superpowers/specs/2026-08-18-tablet-menu-scaling-design.md docs/superpowers/plans/2026-08-18-tablet-menu-scaling.md scripts/verify-mobile-menu-scaling.spec.js wp-content/themes/theobroma/assets/css/home-redesign.css package.json
git commit -m "Fix tablet menu scaling"
```
