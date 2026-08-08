# Theobroma Homepage Visual Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Correct the homepage composition so it remains refined and commercially usable from mobile through 2295px-wide displays.

**Architecture:** Keep the existing WordPress markup, WooCommerce data layer, and interactions. Add measurable layout regression coverage, then replace viewport-driven growth with centered bounded shells and section-specific responsive geometry in the isolated homepage stylesheet.

**Tech Stack:** WordPress/PHP, WooCommerce, CSS, vanilla JavaScript, Playwright with system Chrome, Docker Compose.

## Global Constraints

- Do not change WooCommerce product selection, prices, URLs, add-to-cart behavior, or cacao filtering.
- Do not change existing story, reviews, contact form, or footer content.
- Use only current Cormorant, Montserrat, palette, logo, chocolate fragment, and product images.
- Verify at `2295×1119`, `1440×900`, `1200×1222`, `768×1024`, and `390×844`.
- No document-level horizontal overflow.

---

### Task 1: Measurable visual-layout regression

**Files:**
- Create: `scripts/verify-home-visual-layout.spec.js`
- Modify: `package.json`

**Interfaces:**
- Consumes: rendered `.home-hero`, `.home-benefit-strip`, `.home-catalog`, `.home-product-grid`, `.home-cacao`, and `.home-cacao__image-wrap` DOM boxes.
- Produces: `npm run test:home-visual`, a deterministic responsive geometry gate.

- [ ] **Step 1: Write the failing test**

Create a Playwright script that loads `BASE_URL || http://localhost:8080`, hides the cookie banner, and asserts:

```js
assert(hero.height >= 400 && hero.height <= 500, 'desktop hero must stay within 400–500px');
assert(catalog.y <= 570, 'catalog must enter the first 570px of the page');
assert(wideCard.width <= 340, 'ultrawide product cards must not exceed 340px');
assert(wideGrid.width <= 1440, 'catalog grid must be capped at 1440px');
assert(cacaoCircle.width <= 440, 'desktop cacao image circle must be capped at 440px');
assert(documentWidth === viewportWidth, 'document must not overflow horizontally');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node scripts/verify-home-visual-layout.spec.js`

Expected: FAIL on the current `100svh` hero and ultrawide cards.

- [ ] **Step 3: Add the package script**

Add `"test:home-visual": "node scripts/verify-home-visual-layout.spec.js"` to `package.json`.

- [ ] **Step 4: Commit the red test**

```powershell
git add package.json scripts/verify-home-visual-layout.spec.js
git commit -m "test: cover homepage visual geometry"
```

### Task 2: Hero and global content rhythm

**Files:**
- Modify: `wp-content/themes/theobroma/assets/css/home-redesign.css`

**Interfaces:**
- Consumes: existing homepage DOM and hero assets.
- Produces: bounded desktop hero and reusable `.home-layout-shell` geometry through selectors, without markup changes.

- [ ] **Step 1: Replace viewport-height geometry**

Set desktop hero/shell height to `clamp(420px, 33vw, 470px)`, title to `clamp(112px, 14vw, 196px)`, chocolate to `clamp(180px, 15vw, 220px)`, and desktop benefit strip to `52px`.

- [ ] **Step 2: Bound the inner hero composition**

Use `max-width: 1440px; margin-inline: auto` on the hero shell while preserving the full-width section background. Position lead and trust relative to that shell.

- [ ] **Step 3: Preserve mobile above-the-fold conversion**

At `max-width: 800px`, use a fixed responsive hero range of `620–700px`; at `390×844`, ensure both CTAs remain below the lead and above the viewport bottom.

- [ ] **Step 4: Run focused tests**

Run:

```powershell
node scripts/verify-home-visual-layout.spec.js
node scripts/verify-home-redesign.spec.js
npm run test:header
```

Expected: hero geometry and CTA checks PASS.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/themes/theobroma/assets/css/home-redesign.css
git commit -m "fix: rebalance homepage hero composition"
```

### Task 3: Catalog and cacao picker bounds

**Files:**
- Modify: `wp-content/themes/theobroma/assets/css/home-redesign.css`

**Interfaces:**
- Consumes: existing four-card grid and cacao picker DOM.
- Produces: centered bounded commercial sections at desktop and ultrawide widths.

- [ ] **Step 1: Center the catalog content**

Cap `.home-section-heading`, `.home-product-grid`, and `.home-catalog__footer` at `1440px`, center them, use `24px` desktop gaps, and cap the image slot through the bounded column width.

- [ ] **Step 2: Center and balance the picker**

Cap `.home-cacao__shell` at `1360px`, center it, use columns `minmax(360px, .9fr) minmax(620px, 1.1fr)`, cap the image circle at `420px`, and keep section padding within `84–104px`.

- [ ] **Step 3: Recheck tablet and mobile rules**

Keep the two-card tablet grid and one-card horizontal mobile rail; ensure later media rules override desktop max-width geometry correctly.

- [ ] **Step 4: Run focused tests**

Run:

```powershell
npm run test:home-visual
node scripts/verify-home-tablet.spec.js
node scripts/verify-catalog-responsive.spec.js
```

Expected: all PASS.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/themes/theobroma/assets/css/home-redesign.css
git commit -m "fix: constrain homepage commercial sections"
```

### Task 4: Design QA and full regression

**Files:**
- Create: `design-qa.md`
- Create QA artifacts under ignored `output/playwright/`.

**Interfaces:**
- Consumes: both supplied reference PNGs and fresh Docker screenshots.
- Produces: a blocking design QA report with `final result: passed`.

- [ ] **Step 1: Capture all five viewports from Docker**

Capture the homepage after hiding the cookie banner at `2295×1119`, `1440×900`, `1200×1222`, `768×1024`, and `390×844`.

- [ ] **Step 2: Build comparison boards**

Place both supplied references next to the `1200px` implementation capture and inspect hero rhythm, product density, picker balance, typography, crop, spacing, and section transitions.

- [ ] **Step 3: Write and iterate design QA**

Record every P0/P1/P2 mismatch in `design-qa.md`, fix it, recapture, and repeat until the file contains `final result: passed` with no open P0/P1/P2 items.

- [ ] **Step 4: Run full regression**

Run:

```powershell
php scripts/verify-homepage-data.php
npm run test:home-visual
npm run test:header
node scripts/verify-home-redesign.spec.js
node scripts/verify-home-tablet.spec.js
node scripts/verify-commerce-flow.spec.js
node scripts/verify-catalog-responsive.spec.js
node scripts/audit-keyboard-navigation.js
node scripts/audit-accessibility.js
npm run audit:cwv
```

Expected: all commands PASS and Core Web Vitals remain within current thresholds.

- [ ] **Step 5: Commit**

```powershell
git add design-qa.md
git commit -m "docs: record homepage design QA"
```
