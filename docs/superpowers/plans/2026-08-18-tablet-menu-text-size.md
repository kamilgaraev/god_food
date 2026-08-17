# Tablet Menu Text Size Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Increase only tablet drawer links from `1.5rem` to `2rem` while preserving phone and desktop typography.

**Architecture:** Extend the existing browser-backed responsive test with breakpoint-specific rem expectations. Add one tablet-only CSS override to the existing drawer presentation block; do not change markup, JavaScript, drawer geometry, or global root scaling.

**Tech Stack:** CSS, Node.js, Playwright, `node:assert/strict`

## Global Constraints

- Phone links remain `1.5rem` through 600px.
- Tablet links are `2rem` from 601px through 1199px.
- Existing line-height, drawer geometry, labels, stacking, and interactions remain unchanged.
- Desktop behavior at 1200px and above remains unchanged.

---

### Task 1: Tablet drawer link typography

**Files:**
- Modify: `scripts/verify-mobile-menu-scaling.spec.js`
- Modify: `wp-content/themes/theobroma/assets/css/home-redesign.css`

**Interfaces:**
- Consumes: Existing `.mobile-menu a` styles and the `601px` tablet breakpoint.
- Produces: `2rem` tablet drawer links with phone links unchanged at `1.5rem`.

- [ ] **Step 1: Change the regression test first**

Add `linkScale` to the viewport fixtures: `1.5` for 320, 390, and 600px; `2` for 601, 744, and 1199px. Assert:

```js
assert.ok(
  Math.abs(metrics.linkSize - metrics.rootSize * expected.linkScale) <= 1,
  `${expected.width}px: expected ${expected.linkScale}rem drawer links`,
);
```

- [ ] **Step 2: Verify RED**

Run: `npm run test:menu-scaling`

Expected: FAIL at 744px because the current link size is `1.5rem`, not `2rem`.

- [ ] **Step 3: Add the minimal tablet override**

After the shared drawer block, add:

```css
@media (min-width: 601px) and (max-width: 1199px) {
  .mobile-menu a { font-size: 2rem; }
}
```

Keep the existing `1.5rem` declaration as the phone default.

- [ ] **Step 4: Verify GREEN and existing header behavior**

Run:

```powershell
npm run test:menu-scaling
npm run test:header
git diff --check
```

Expected: all commands exit successfully.

- [ ] **Step 5: Commit**

```powershell
git add scripts/verify-mobile-menu-scaling.spec.js wp-content/themes/theobroma/assets/css/home-redesign.css
git commit -m "Increase tablet menu text"
```
