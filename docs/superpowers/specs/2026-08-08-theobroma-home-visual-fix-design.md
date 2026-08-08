# Theobroma homepage visual correction

## Direction

The page uses restrained editorial luxury: the porous chocolate fragment is treated as a precious object, typography remains the primary visual gesture, and commercial content is compact enough to remain legible on large monitors. The two supplied references define rhythm and composition; the DOCX defines content and behavior. This is not a pixel clone.

## Global geometry

- Full-bleed section backgrounds remain, but meaningful content is centered in a `1440px` maximum-width shell.
- At widths above `1600px`, typography, cards, gaps, and product imagery stop growing.
- Desktop side padding is `48–64px`; tablet is `24px`; mobile is `18px`.
- The fixed header is `72px` on desktop and `64–68px` on mobile.

## Hero

- Desktop hero content height is `440–480px`, independent of viewport height.
- The benefit strip starts no later than `560px` from the top of the page on desktop.
- The title uses Cormorant at `clamp(112px, 14vw, 196px)` and is capped on ultrawide screens.
- The chocolate fragment is `180–220px` wide on desktop, overlaps the central letters without hiding the word, and uses a restrained shadow.
- Eyebrow, title, lead/CTA, and trust block form four deliberate horizontal bands with no dead vertical field.
- Both CTA remain visible without scrolling at `1440×900` and `390×844`.

## Catalog

- Four WooCommerce cards in a centered four-column grid on desktop.
- Grid width never exceeds `1440px`; card image height is capped so ultrawide screens do not create poster-sized products.
- At `1200px`, each card is approximately `250–275px` wide; at `2295px`, no card exceeds `340px`.
- Product data and interactions remain unchanged.

## Cacao picker

- Centered shell with a maximum width of `1360px`.
- Desktop section height stays within roughly `600–700px`.
- Selector and product presentation are balanced columns; the image circle is capped at `400–440px`.
- The product photo fills the visual slot without becoming a giant pack shot.
- Mobile keeps the horizontal percentage rail and places the image above the centered copy.

## Remaining sections

- Existing story, composition, reviews, form, and footer stay structurally unchanged.
- Only spacing is normalized to the new centered rhythm; original content and functionality are preserved.

## Quality gates

- Runtime screenshots at `2295×1119`, `1440×900`, `1200×1222`, `768×1024`, and `390×844`.
- Side-by-side comparison boards against both supplied references.
- No P0/P1/P2 findings in `design-qa.md`.
- No document-level horizontal overflow.
- Existing commerce, keyboard, accessibility, and Core Web Vitals suites remain green.
