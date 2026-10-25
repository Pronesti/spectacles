# Frontend concept verification

Verified locally on 2026-10-22 against `https://spectacles.test` using the running Vite app and the actual Laravel health endpoint. No backend or provider data was written. Temporary QA concerts were removed through the interface; the browser collection returned to 24 sample concerts.

## Automated checks

- `npm run lint`: passed without warnings.
- `npm run build`: passed (TypeScript and Vite production build).
- `npm test`: 12 passed, including the three existing production-server checks and nine domain checks.
- `git diff --check`: passed.

The domain checks cover local calendar-day countdowns, partial dates, Gregorian date validation, multiple concerts on one day, unique venue identities, immutable setlist reordering including notes/repeated songs, composed search and filters, saved-data round trips, false preferences and zero coordinates, and rejection of malformed saved data. The production-server checks exercise compiled HTML, deep-link fallback, immutable assets, and missing-asset/API 404s.

## Browser checks

- At 1280 × 900, reviewed dashboard, concert manager, calendar, map, and setlist views against the separate generated references. Preserved the ivory/lime/lavender system, sidebar, dark photographic countdown, open statistics, flat concert lists, calendar rules, venue overlay, and ordered setlist editor.
- Created a concert with two artists, an exact date, Interested false, and Tickets bought true. The nearest-concert countdown and statistics updated. Edited its title; reloaded and verified persistence.
- Created a song with tape status, added an encore note, added/reordered another song, edited it to the same title as the first song, and verified the ordered repeated-song list survived reload.
- Changed the QA concert to month-only precision. It appeared under Date to confirm and the calendar's separate uncertain-date notice, without an exact calendar cell or countdown.
- Navigated calendar months and Today; selecting a day opened an add form with that date prefilled.
- Verified six map pins for six referenced venue identities despite 24 concerts; selected a pin, zoomed, and followed View concerts to the matching venue filter.
- Verified artist/title search, composed venue filtering, concert deletion, Undo, and final QA cleanup.
- Verified heatmap day selection, the Radiohead detail, keyboard focus moving to the next empty day, and switching to the prior year.
- Removed a saved setlist song and restored it with Undo.
- At 390 × 844, checked dashboard, calendar, venue map, setlist editor, and API details. No whole-page horizontal overflow remained. The calendar intentionally scrolls inside its 350px region over a 525px calendar grid; the heatmap also has its own scroll region.
- The real health endpoint reported connected. Retry returned to connected; Escape closed the modal. No browser console errors were recorded on the project URL.

Visual review caught and corrected an SVG pattern fill and mobile calendar overflow. The final build also caught a CSS pseudo-selector whitespace typo introduced during formatting; it was corrected before the successful final checks.

## Limits

This is a design prototype with browser-local sample data, not backend CRUD. The map is a self-contained illustration with approximate sample coordinates; no live tiles, geocoding, directions, or external map provider are used. No authentication, collaboration, provider autocomplete, production deployment, or database mutations were tested or introduced. Storage-unavailable and unreadable-save handling is implemented; browser storage-failure injection was not performed. Native date input automation required a keyboard date edit before submission.

All screenshots in this directory are review artifacts. Original generated visual references and prompts are one directory above; the photograph consumed by the app is in `src/assets/concert-stage.png`.
