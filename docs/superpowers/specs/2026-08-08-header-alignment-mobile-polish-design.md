# Header alignment and mobile polish

Date: 2026-08-08

## Goal

Preserve the current light Theobroma header and homepage aesthetic while removing alignment drift on desktop and the empty, visually weak mobile first screen. No new visual concept, fonts, colors, libraries, or external assets are introduced.

## Desktop header

- Keep the existing 78 px fixed header, light translucent surface, logo, navigation labels, account control, and cart.
- Use one stable three-zone layout: left navigation, optically centered logo, right actions.
- The logo center must stay within 1 px of the viewport center at 1101, 1200, 1440, and 2295 px.
- Left and right groups share the same vertical center; individual links and controls must not use compensating transforms.
- Navigation gaps are responsive but consistent within each group. The left group remains left-aligned, while “Где купить”, account, and cart remain right-aligned.
- Account and cart retain at least 40 × 40 px hit areas. Cart icon and count share one baseline and use tabular numerals.
- Hover, focus-visible, and pressed states stay restrained and use only the existing brand palette.

## Mobile header

- Keep the 68 px header and existing logo/burger composition.
- Show the existing account icon in the top header next to the cart instead of hiding it.
- Group account and cart into one aligned action cluster; align logo, controls, and burger to the same optical center.
- The cart must have a stable 38 px height, centered icon, and centered count at 320–800 px without overlap.
- At narrow widths, reduce gaps and logo width before hiding any primary control.
- The mobile drawer keeps its existing information architecture and keyboard behavior.

## Mobile hero rhythm

- Replace the large empty middle area with a deliberate vertical sequence: eyebrow, title, trust metrics, flexible breathing room, product copy, CTAs.
- The mobile hero shell becomes a grid rather than relying on absolute top/bottom offsets.
- Target hero height is 420–450 px, depending on viewport height, with both CTAs visible without scrolling at 390 × 844 and 440 × 956. This tighter range was selected after visual QA showed that 460–500 px still left an overly large gap between trust and product copy.
- Preserve all current copy, colors, typography, buttons, and trust values. No decorative image is reintroduced.
- The benefit strip follows immediately after the hero without a visually dead gap.

## Accessibility and motion

- All controls remain keyboard reachable with visible focus.
- Account, cart, and menu retain their accessible names.
- Hover/press transitions name explicit properties and respect `prefers-reduced-motion`.
- No horizontal overflow is allowed at 320, 390, 440, 768, 1101, 1200, 1440, or 2295 px.

## Verification

- Add failing geometry assertions before CSS changes for desktop logo centering, common control axis, visible mobile account, cart alignment, compact hero height, CTA visibility, and overflow.
- Run the responsive header, homepage visual, homepage contract, tablet, keyboard navigation, and PHP syntax checks.
- Capture and inspect desktop and mobile header/hero screenshots after the automated checks pass.

## Out of scope

- Navigation labels or destinations.
- New icons or imagery.
- Catalog, cacao selector, lower homepage sections, footer, account modal, cart modal, checkout, or WooCommerce data behavior.
