# Home Eyebrow Gold Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Match the homepage eyebrow text color to the primary button gold.

**Architecture:** Reuse the existing `--home-gold` design token in `.home-eyebrow`. Protect the relationship with a computed-style browser test rather than duplicating a hex value in the test.

**Tech Stack:** WordPress theme CSS, Node.js, Playwright.

## Global Constraints

- Work only on `codex/home-eyebrow-gold`.
- Do not change typography, spacing, markup, or responsive layout.
- Push the verified commit to `main` without force-pushing.

---

### Task 1: Match eyebrow and button colors

**Files:**
- Create: `scripts/verify-home-eyebrow-color.spec.js`
- Modify: `package.json`
- Modify: `wp-content/themes/theobroma/assets/css/home-redesign.css`

**Interfaces:**
- Consumes: `.home-eyebrow`, `.home-button--primary`, and `--home-gold`
- Produces: identical computed colors for the eyebrow text and primary button background

- [x] **Step 1: Add a browser regression test**

At 1440px and 390px viewports, read `getComputedStyle(document.querySelector('.home-eyebrow')).color` and `getComputedStyle(document.querySelector('.home-button--primary')).backgroundColor`, then fail when they differ.

- [x] **Step 2: Verify RED**

Run `npm run test:home-eyebrow-color` and confirm it fails because the eyebrow uses the peach token while the button uses the gold token.

- [x] **Step 3: Implement the minimal change**

Change only `.home-eyebrow` from `color: var(--home-peach)` to `color: var(--home-gold)`.

- [x] **Step 4: Verify GREEN and layout**

Run `npm run test:home-eyebrow-color`, `npm run test:home-visual`, and `git diff --check`; all must exit with code 0.

- [x] **Step 5: Commit and publish**

Commit only the spec, plan, test, package script, and CSS change. Rebase on the latest `origin/main` if necessary and push `HEAD:main` without force.
