# Tablet Menu Text Size Design

## Problem

The drawer width scales from 88vw, but its links remain `1.5rem` across phone and tablet layouts. At 744px the drawer is about 655px wide while the links are only about 24.7px, so the navigation looks undersized.

## Approved behavior

- Keep phone menu links at `1.5rem` through 600px.
- Set tablet menu links to `2rem` from 601px through 1199px.
- Let the existing root `rem` scale produce approximately 32px at 601px, 33px at 744px, and 36px at 1199px.
- Preserve the existing `1.08` line-height, link casing, drawer width, labels, close control, overlay, stacking, and interaction behavior.
- Keep desktop behavior at 1200px and above unchanged.

## Verification

Extend the existing browser-backed menu regression test to assert a `1.5rem` link scale on phones and a `2rem` link scale on tablets, including the reported 744×1133 viewport.
