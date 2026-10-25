# Spectacles frontend concept

Generated with the built-in image tool on 2026-10-22. The six original full-screen references live in `references/`; their prompts and the separate photograph prompt are in `image-prompts.json`. These are design artifacts, separate from product source.

## Image inspection and translation

The dashboard reference prioritizes “Your life, live.”, followed by “The nights you never want to forget.” It has a narrow persistent sidebar, four open statistics separated by rules, then a wide dark photo containing the next concert title, date, venue, outlined action, and large lime countdown. Below it, upcoming concerts use a date column and a flat ruled list; the lavender setlist preview supplies a quieter second rhythm. Keep the photograph in a fixed frame and render all meaningful text in HTML. Reference numbers and dates are illustrative; production components compute them from the demo collection.

The concerts reference uses the heading “Every night has a story.”, artist/venue search, flat All/Upcoming/Past filters, and generous ruled rows. A right drawer has an explicit date-precision control and independent Interested / Tickets bought checkboxes. Preserve these distinctions. Rows use readable artist names, a small month/day block, venue, and editing controls. A custom venue may have no location; do not invent a map position.

The calendar reference uses “Make room for live music.” with a large month header, restrained previous/next/Today controls, thin seven-column rules, lavender past events, lime future events, and a dark today circle. Its side agenda and photo are subordinate. The image contains placeholder venues inconsistent with the city; use the shared sample records everywhere. Only exact dates belong in date cells; partial dates receive an explicit separate notice.

The activity reference uses a flat annual grid with seven weekday rows, 53 week columns, month labels, pale neutral empty days, and lime/olive intensity. Hover detail is a dark tooltip with a date and concert names. Translate into actual Gregorian dates, aggregate multiple concerts per day, and provide the same information on focus and click. Do not reproduce the image's accidental extra row or fabricated bar-chart totals.

The map reference uses “Your city. Your stages.” above a wide light street illustration and a narrower venue list. Pale blue river, sage parks, near-white street lines, gray neighborhood labels, dark pins, and a lime selected pin establish the visual hierarchy. A white venue detail overlay includes a collection count and View concerts action. Keep exactly one pin per venue identity, display missing coordinates separately, and label the self-contained map illustrative rather than suggesting a live geographic provider.

The setlist reference uses “The songs stay with you.”, a concert selector, a lavender image/title strip, and an ordered flat list. Song title is primary and performer secondary; note/encore rows have a pale neutral background. Reorder, edit, delete, and inline-save controls remain visible. Song numbers count songs, not note rows. Tape status is independent of row type. Repeated songs are valid.

## Extracted system

- Main background warm ivory `#f7f7f2`; ink `#1d211e`; lime `#d5f76a`; lavender `#e9e2f7`; sage rules `#dfe2d9`; muted olive text `#747966`.
- Refined grotesk/system sans. Display headings bold with tight negative tracking, approximately 44–52px at desktop; body 14–16px, secondary text 12–14px. No tiny text carries essential controls.
- Sidebar approximately 216–224px. Main gutters 36–44px. Vertical section gaps 32–40px. Statistic numbers 32–38px. Row heights 80–96px. Photo feature approximately 280–310px tall.
- Purposeful photo/feature frames use 12–16px radii; buttons 8px, no giant enclosing dashboard shell. Dark primary buttons have a small lime plus. Secondary actions are flat or outlined. Dividers and space provide hierarchy rather than nested cards.
- Four signature motifs: vertical rhythm lines, offset editorial composition, controlled image frames, flat product panels. Motion: restrained section entrance and smooth disclosure. Respect reduced-motion preferences.
- Mobile: compact brand header and five-item navigation, one-column content, horizontally scrollable heatmap/calendar within their own regions, full-width editor drawer. Preserve readable text and touch targets.

## Boundary

This is a frontend concept. Sample concert dates and artist/venue combinations are fictional. Changes are stored only in this browser, with a versioned local-storage record; the existing Laravel health endpoint remains a separate real integration. There is no authentication, provider lookup, synchronization, or backend concert write API.
