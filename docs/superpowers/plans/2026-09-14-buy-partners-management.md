# Buy Partners Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a polished WordPress admin area for managing boutiques and partners and render published records on the existing «Где купить» page.

**Architecture:** Add focused buy-content helpers in `inc/buy-partners.php`, register two private content types under a shared «Где купить» admin menu, store structured fields in post meta, and replace the hardcoded buy template arrays with ordered published queries. Keep the existing tab controller and page layout, adding only the admin assets and card states needed for variable content.

**Tech Stack:** WordPress PHP APIs, custom post types and meta boxes, WordPress media modal, vanilla admin JavaScript/CSS, existing theme CSS, Playwright browser checks.

**Spec:** `docs/superpowers/specs/2026-09-14-buy-partners-management-design.md`

## Global Constraints

- Only published records appear on the public page.
- Boutique fields: title, photo, address, hours, directions URL, menu order.
- Partner fields: title, logo, city, store URL, menu order.
- Empty/missing images must leave a styled fallback card instead of breaking layout.
- Existing tab animation, warm Theobroma palette, and responsive layouts remain intact.
- URL fields accept only `http` and `https`; all saved and displayed values are sanitized/escaped.

---

### Task 1: Add buy-content data and admin registration

**Files:**
- Create: `wp-content/themes/theobroma/inc/buy-partners.php`
- Modify: `wp-content/themes/theobroma/functions.php:3-10`

**Interfaces:**
- Produces `theobroma_buy_register_content_types()`, `theobroma_buy_get_entries(string $post_type): array`, `theobroma_buy_meta(string $key, int $post_id): mixed`.
- Uses WordPress `register_post_type`, `register_post_meta`, `add_menu_page`, `add_meta_box`, `save_post`, and `get_posts` APIs.

- [ ] **Step 1: Add the failing structural check**

Run after implementation with the exact commands below; the check must fail before the new file is present because the functions do not exist yet:

```powershell
php -l wp-content/themes/theobroma/inc/buy-partners.php
rg -n "theobroma_buy_get_entries|theobroma_boutique|theobroma_partner" wp-content/themes/theobroma/inc/buy-partners.php
```

- [ ] **Step 2: Register the two content types and shared menu**

Implement `theobroma_buy_register_content_types()` on `init`. Use `show_ui => true`, `public => false`, `show_in_menu => 'theobroma-buy'`, `supports => ['title','page-attributes']`, and labels «Бутики» / «Партнёры». Add an `admin_menu` callback that creates the top-level «Где купить» page with a short dashboard and links to both lists.

- [ ] **Step 3: Add sanitized meta boxes and save handlers**

Use separate meta boxes for each type. Render image ID fields with a media-picker button, preview, and remove button; render text, URL, and order fields with labels and short descriptions. Save only for the matching post type, require `current_user_can('edit_post', $post_id)`, verify a per-box nonce, reject autosaves/revisions, normalize numeric attachment IDs, and accept URL schemes `http`/`https` only.

- [ ] **Step 4: Add ordered published query helpers**

Implement `theobroma_buy_get_entries()` with `post_status => 'publish'`, `posts_per_page => -1`, `orderby => ['menu_order'=>'ASC','title'=>'ASC']`, and the requested post type. Implement `theobroma_buy_meta()` as a small escaped-key wrapper around `get_post_meta`.

- [ ] **Step 5: Wire the file and lint**

Require the new file near the other theme `inc` files in `functions.php`, then run:

```powershell
php -l wp-content/themes/theobroma/inc/buy-partners.php
php -l wp-content/themes/theobroma/functions.php
git diff --check
```

- [ ] **Step 6: Commit**

```powershell
git add wp-content/themes/theobroma/inc/buy-partners.php wp-content/themes/theobroma/functions.php
git commit -m "Add admin content types for buy locations"
```

### Task 2: Build the admin editing experience and migrate existing records

**Files:**
- Create: `wp-content/themes/theobroma/assets/css/buy-admin.css`
- Create: `wp-content/themes/theobroma/assets/js/buy-admin.js`
- Modify: `wp-content/themes/theobroma/inc/buy-partners.php`
- Modify: `wp-content/themes/theobroma/functions.php:100-160`

**Interfaces:**
- Produces admin hooks that enqueue assets only for `theobroma_boutique`, `theobroma_partner`, and the `theobroma-buy` dashboard.
- Produces one-time `theobroma_migrate_buy_entries()` guarded by option `theobroma_buy_content_migrated_v1`.

- [ ] **Step 1: Add the media-picker behavior**

Implement `buy-admin.js` with `wp.media`, one picker per `.theobroma-buy-image-field`, preview replacement, attachment ID synchronization, and a remove action that clears the hidden input and preview. Use event delegation so cloned/loaded controls remain functional.

- [ ] **Step 2: Style the admin cards**

Implement `buy-admin.css` with a restrained paper/gold palette, two-column field grid that collapses to one column below 782px, image preview tile, prominent save/publish area, and readable list-table thumbnail columns. Keep WordPress notices, buttons, and keyboard focus behavior intact.

- [ ] **Step 3: Add list columns and row data**

Register custom columns for both types: image/logo, title, address or city, order, and status. Use small thumbnails and fallbacks; make columns sortable only where the underlying data supports it. Add `manage_{$post_type}_posts_columns` and `manage_{$post_type}_posts_custom_column` callbacks.

- [ ] **Step 4: Migrate current hardcoded entries exactly once**

Move the current boutique and partner arrays into the migration helper, create records with stable source keys, copy existing image filenames to attachment IDs when found in the theme media path, and set the old card fields. Skip creation when the source key already exists. Run the helper from `admin_init` for users with `manage_options` so deployment does not write content on anonymous requests.

- [ ] **Step 5: Enqueue assets only on the relevant admin screens**

Add an admin enqueue callback that checks `$hook_suffix`, `get_current_screen()->post_type`, and the dashboard slug before loading the two assets. Localize the media title/button strings through `wp_localize_script`.

- [ ] **Step 6: Lint and commit**

```powershell
php -l wp-content/themes/theobroma/inc/buy-partners.php
php -l wp-content/themes/theobroma/functions.php
git diff --check
git add wp-content/themes/theobroma/inc/buy-partners.php wp-content/themes/theobroma/assets/css/buy-admin.css wp-content/themes/theobroma/assets/js/buy-admin.js wp-content/themes/theobroma/functions.php
git commit -m "Add buy content admin editor and migration"
```

### Task 3: Replace hardcoded frontend cards

**Files:**
- Modify: `wp-content/themes/theobroma/template-parts/pages/buy.php`
- Modify: `wp-content/themes/theobroma/style.css` near the existing `.buy-location` and `.buy-partner-card` rules.

**Interfaces:**
- Consumes `theobroma_buy_get_entries()` and `theobroma_buy_meta()` from Task 1.
- Preserves `.buy-tabs`, `#bulletcities1`, `#bulletcities3`, `.buy-location`, and `.buy-partner-card` selectors consumed by the existing JavaScript/CSS.

- [ ] **Step 1: Render published boutique records**

Replace the single hardcoded article with a loop. Render the stored attachment image when available, the title, address, hours, and directions link. When there are no published boutiques, render one `.buy-empty-state` message.

- [ ] **Step 2: Render published partner records**

Replace the hardcoded partner array with a loop. Render the logo when available, title, city, and an optional external link. Keep the existing grid classes and add a fallback card when no partners are published.

- [ ] **Step 3: Add variable-card layout rules**

Add `.buy-location-grid` and `.buy-empty-state`; make boutique cards a responsive grid on desktop/tablet and one column on phones. Keep image ratios, spacing, and gold action buttons aligned with the current page. Ensure long names, missing logos, and empty cities do not overflow.

- [ ] **Step 4: Check template syntax and commit**

```powershell
php -l wp-content/themes/theobroma/template-parts/pages/buy.php
git diff --check
git add wp-content/themes/theobroma/template-parts/pages/buy.php wp-content/themes/theobroma/style.css
git commit -m "Render buy page from managed entries"
```

### Task 4: Verify admin/public behavior and deploy

**Files:**
- Create: `scripts/verify-buy-content.cjs`

- [ ] **Step 1: Add browser verification**

Use Playwright to visit `/buy/` at widths 320, 768, and 1440 in Chromium and WebKit. Assert both tab panels keep their ARIA relationships, no horizontal overflow appears, cards stay within the content container, and the tab transition still changes the visible panel.

- [ ] **Step 2: Run static checks and browser verification**

```powershell
php -l wp-content/themes/theobroma/inc/buy-partners.php
php -l wp-content/themes/theobroma/template-parts/pages/buy.php
node scripts/verify-buy-content.cjs
```

- [ ] **Step 3: Check the admin route manually or with an authenticated browser session**

Confirm «Где купить», «Бутики», and «Партнёры» appear in the admin menu; create a draft, upload/replace an image, change order, publish it, and confirm the public tab changes without exposing drafts.

- [ ] **Step 4: Commit verification script and deploy**

```powershell
git add scripts/verify-buy-content.cjs
git commit -m "Verify managed buy content responsively"
git push -u origin codex/manage-buy-partners
```

Cherry-pick the feature commits onto the server deployment branch and verify `/buy/` plus the admin content types after deployment.
