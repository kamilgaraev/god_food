# Header Alignment and Mobile Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep the current Theobroma visual language while precisely aligning the desktop header and making the mobile header and first screen compact, balanced, and complete.

**Architecture:** Preserve the existing `header.php` DOM and reuse the existing user/cart assets. Put geometry rules in the scoped homepage stylesheet, with Playwright assertions serving as the public layout contract. Convert only the mobile hero layout from absolute offsets to a small CSS grid.

**Tech Stack:** WordPress PHP templates, CSS, Node.js, Playwright, existing WooCommerce/header JavaScript.

## Global Constraints

- Work only on `codex/header-alignment-mobile-polish`, never directly on `main`.
- Do not add libraries, fonts, colors, icons, or external assets.
- Keep all navigation labels, destinations, menu behavior, and WooCommerce cart behavior unchanged.
- Preserve 78 px desktop and 68 px mobile header heights.
- No horizontal overflow from 320 through 2295 px.

---

### Task 1: Lock the new header geometry contract

**Files:**
- Modify: `scripts/verify-responsive-header.spec.js`
- Modify: `scripts/verify-home-visual-layout.spec.js`

**Interfaces:**
- Consumes: existing `.nav`, `.brand`, `.nav-links-study`, `.nav-links-transactional`, `.header-account`, `.header-cart`, `.menu-toggle`, `.home-hero`, `.home-hero__actions` DOM.
- Produces: executable geometry requirements for the CSS implementation.

- [ ] **Step 1: Write failing header assertions**

Extend desktop viewports to `[1101, 1200, 1440, 2295]`; measure centers for the two navigation groups, logo, account, cart, and header. Assert logo deviation ≤ 1 px, all interactive controls intersect the header vertical center, and the two groups do not overlap the logo. On `[320, 390, 440, 768]`, assert the account control is visible, account/cart/burger share a center within 2 px, cart is 38 px high, and the document does not overflow.

- [ ] **Step 2: Write failing mobile hero assertions**

In `verify-home-visual-layout.spec.js`, replace the old 540–600 px mobile hero contract with 460–500 px. Assert both hero CTAs are within the viewport at 390 × 844 and 440 × 956, trust is below the title, lead is below trust, and the benefit strip begins within 2 px of the hero bottom.

- [ ] **Step 3: Run tests and verify the expected failure**

Run:

```powershell
npm run test:header
npm run test:home-visual
```

Expected: header test fails because `.header-account` is hidden on mobile; visual test fails because the hero is 540–580 px.

- [ ] **Step 4: Commit the red tests**

```powershell
git add scripts/verify-responsive-header.spec.js scripts/verify-home-visual-layout.spec.js
git commit -m "test: define polished header geometry"
```

### Task 2: Align the desktop and mobile header

**Files:**
- Modify: `wp-content/themes/theobroma/assets/css/home-redesign.css`

**Interfaces:**
- Consumes: the existing header DOM and current `--home-*` tokens.
- Produces: centered three-zone desktop layout and four-control mobile layout with visible account control.

- [ ] **Step 1: Implement the desktop layout**

Keep `grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr)` and make the nav content use one bounded inline shell through matching side padding. Remove selector-specific transform/order compensation, use one explicit gap scale, and give links a common 42 px alignment box. Add `font-variant-numeric: tabular-nums` to `.cart-count`; define explicit color/background/transform transitions and `:active { transform: scale(.96); }` for icon controls.

- [ ] **Step 2: Implement the mobile action cluster**

At `max-width: 800px`, hide only `.nav-links-study` and `.header-where`; keep `.header-account` visible. Use columns `auto 1fr auto`, place the brand first, the transactional cluster second, and burger third. Set account to 38 × 38 px, cart to 58–62 × 38 px, align both icons optically, and use smaller responsive gaps below 374 px.

- [ ] **Step 3: Run the header test**

Run `npm run test:header`.

Expected: PASS with visible mobile account, centered cart, no overlap, and no horizontal overflow.

- [ ] **Step 4: Commit the header implementation**

```powershell
git add wp-content/themes/theobroma/assets/css/home-redesign.css
git commit -m "fix: align responsive header controls"
```

### Task 3: Tighten the mobile first screen

**Files:**
- Modify: `wp-content/themes/theobroma/assets/css/home-redesign.css`

**Interfaces:**
- Consumes: existing hero DOM and content.
- Produces: mobile grid order `eyebrow → title → trust → flexible space → lead` with a 460–500 px hero.

- [ ] **Step 1: Replace mobile absolute offsets with grid placement**

At `max-width: 800px`, set `.home-hero__shell` to a five-row grid and target `height: clamp(460px, 58svh, 500px)`. Reset `.home-hero__trust` and `.home-hero__lead` to static positioning, assign their grid rows, and keep trust right-aligned and lead bottom-aligned. Preserve the current two-column CTA layout and exact copy.

- [ ] **Step 2: Add the narrow-width adjustment**

At `max-width: 420px`, reduce title margin and control gaps without changing font families or copy. Ensure both actions retain at least 44 px height.

- [ ] **Step 3: Run visual and responsive regression tests**

Run:

```powershell
npm run test:home-visual
node scripts/verify-home-redesign.spec.js
node scripts/verify-home-tablet.spec.js
```

Expected: PASS; both CTAs remain above the fold and all existing homepage contracts remain intact.

- [ ] **Step 4: Commit the mobile hero implementation**

```powershell
git add wp-content/themes/theobroma/assets/css/home-redesign.css
git commit -m "fix: balance mobile homepage hero"
```

### Task 4: Visual QA, regression, and publication

**Files:**
- Modify: `design-qa.md`
- Create: `output/playwright/header-polish/*` (untracked QA artifacts)

**Interfaces:**
- Consumes: completed CSS and geometry tests.
- Produces: evidence-backed release commit ready for `main`.

- [ ] **Step 1: Capture visual evidence**

Capture header/hero at 1440 × 900, 1101 × 900, 768 × 1024, 440 × 956, 390 × 844, and 320 × 720. Inspect vertical axes, logo center, action spacing, icon visibility, hero rhythm, CTA visibility, and overflow.

- [ ] **Step 2: Run the final verification set**

```powershell
npm run test:header
npm run test:home-visual
node scripts/verify-home-redesign.spec.js
node scripts/verify-home-tablet.spec.js
node scripts/verify-commerce-flow.spec.js
node scripts/audit-keyboard-navigation.js
php -l wp-content/themes/theobroma/header.php
git diff --check
```

Expected: every command exits 0.

- [ ] **Step 3: Update and commit QA evidence**

Record tested viewports, measured alignment, screenshot paths, and zero open P0–P2 findings in `design-qa.md`, then commit:

```powershell
git add design-qa.md
git commit -m "docs: record header polish QA"
```

- [ ] **Step 4: Publish after verifying fast-forward safety**

Fetch `origin/main`, confirm it is an ancestor of `HEAD`, and push `HEAD:main` without force. If the remote moved, stop and integrate it before retrying.
