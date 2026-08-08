# Theobroma Homepage Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Rebuild the production WordPress homepage around the approved editorial product layout while keeping WooCommerce as the source of truth.

**Architecture:** WordPress renders all product and cacao-percentage data on the server. A small deferred homepage script progressively enhances the cacao selector and add-to-cart button states. The existing WooCommerce catalogue, cart, account, reviews, contact form, and footer remain authoritative.

**Tech Stack:** PHP 8+, WordPress, WooCommerce, semantic HTML, CSS, vanilla JavaScript, Playwright.

## Global Constraints

- Work on `codex/theobroma-home-redesign` from current `origin/main`; do not touch the user's dirty checkout.
- Reuse Cormorant, Montserrat, the existing palette, logo, chocolate fragment, and WooCommerce images.
- Do not add third-party libraries, fonts, external images, migrations, or new database schema.
- Supported cacao values are exactly 59, 65, 68, 70, and 80; default is 70.
- Public filter URL is `/catalog/?cacao_percentage=<value>`.
- Preserve internal pages, checkout, modals, contact form, reviews, and footer behavior.

## Tasks

1. Add focused PHP tests for percentage validation, grouping, representative selection, and filter URLs; then implement the homepage data module and catalogue query integration.
2. Rebuild the shared header and homepage PHP template with server-rendered fallbacks and real WooCommerce product data.
3. Add `homepage.js` for the accessible cacao tabs and cart-button state, then replace homepage/header CSS with responsive `.home-*` styling.
4. Update and add Playwright coverage for header, homepage, percentage filtering, keyboard access, responsive overflow, and reduced motion.
5. Run PHP syntax checks, relevant browser suites, accessibility/commerce checks, Core Web Vitals, and visual review at 1440x900, 768x1024, and 390x844.

