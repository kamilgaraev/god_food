# Tablet Menu Scaling Design

## Problem

At a 744×1133 viewport the navigation drawer uses the legacy tablet rules from `style.css`. Those rules cap the drawer at `20rem` and retain the older uppercase navigation styling, while the approved mobile composition is wide relative to the viewport and uses grouped labels with title-case links.

## Approved behavior

- From 320px through 1199px, the drawer occupies `88vw` so its width scales continuously with the viewport.
- At 744px, the drawer is approximately 655px wide.
- The drawer content, close control, section labels, spacing, colors, and link typography use the approved mobile composition shown in the second reference image.
- At 1200px and above, the desktop navigation remains unchanged.
- Existing focus trapping, Escape handling, scroll locking, and ARIA behavior remain unchanged.

## Implementation

Move the drawer-only presentation rules out of the `max-width: 600px` block in `assets/css/home-redesign.css` and apply them through `max-width: 1199px`. Remove the fixed width caps from the drawer, navigation content, and close-button positioning. Do not modify the menu markup or JavaScript behavior.

## Verification

Add a browser-backed CSS regression test that loads the real theme styles and measures the rendered drawer at 390, 600, 601, 744, and 1199px. The test must verify the `88vw` width, close-button alignment, approved typography, and absence of horizontal overflow. At 1200px it must verify that the mobile drawer is not displayed.
