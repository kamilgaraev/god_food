# Home Chocolate Texture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a refined chocolate-material texture to the large homepage “ШОКОЛАД” heading.

**Architecture:** Keep the existing semantic `h1` and layout unchanged. Add one optimized theme image and compose it with CSS gradient lighting inside the text, retaining the current solid color as a compatibility fallback.

**Tech Stack:** WordPress theme, CSS, WebP image asset, Playwright screenshot checks.

## Global Constraints

- Work on `codex/home-chocolate-texture`, not directly on `main`.
- Do not include the existing user modification in `scripts/verify-catalog-spacing-product-copy-scale.spec.js`.
- Do not add JavaScript or external dependencies.
- Preserve heading semantics and layout at desktop, tablet, and mobile widths.

---

### Task 1: Chocolate heading material

**Files:**
- Create: `wp-content/themes/theobroma/assets/images/chocolate-texture.webp`
- Modify: `wp-content/themes/theobroma/assets/css/home-redesign.css:114`
- Test: existing local homepage screenshot flow

**Interfaces:**
- Consumes: `.home-hero h1` rendered by `wp-content/themes/theobroma/index.php`
- Produces: a CSS-only textured text treatment with a solid-color fallback

- [ ] **Step 1: Establish the baseline**

Capture the current homepage at desktop and mobile widths so heading placement and readability can be compared after the change.

- [ ] **Step 2: Optimize the supplied texture**

Convert the supplied PNG to a visually lossless, resized WebP suitable for a text fill. Keep enough source detail for high-density desktop displays while avoiding the original PNG payload.

- [ ] **Step 3: Add the progressive CSS enhancement**

Keep `color: #8e857d` as the base declaration. Under `@supports ((-webkit-background-clip: text) or (background-clip: text))`, apply the texture plus warm lighting gradients, blend the layers, clip them to the glyphs, make the text fill transparent, and add restrained edge definition.

- [ ] **Step 4: Tune mobile texture scale**

Within the existing `max-width: 600px` media query, use a larger texture scale so the porous detail remains legible and does not become noisy in smaller glyphs.

- [ ] **Step 5: Verify**

Run the relevant static checks and capture desktop/mobile screenshots. Confirm that the heading retains the same box geometry, remains readable, and uses the optimized asset without console or network errors.

- [ ] **Step 6: Commit and publish**

Commit only the texture asset, CSS, design spec, and plan. Merge the feature branch into `main` without overwriting unrelated working-tree changes, then push `main` to `origin`.
