# Catalog frontend verification — 2026-10-23

Implementation in the existing `feat/live-event-data-layer` checkout; no commit, push, backend change, dependency, or lockfile change. Existing configuration, planning, design, and QA files were preserved.

## Environment and isolation

Node 24.22.1; installed Vite 8.3.5; PHP 8.5.10 with the machine-configured `php` prefix. Native Codex in-app browser, 1280 × 720 screenshot viewport. Frontend `http://localhost:5179`; backend `http://localhost:8097`. A new temporary SQLite database was migrated and seeded with the existing LiveEventSeeder. Existing application databases and ordinary browser origins were untouched.

Temporary database: `/var/folders/m8/sdwc4pyj44g6rzmvg50yxqzw0000gn/T//spectacles-catalog-qa.7Zlmmg/database.sqlite` (disposable). Only fixture account data was entered. No credentials or cookie values are retained here.

The plan's `artisan serve` command initially returned responses without the temporary CORS origin: Laravel's reload mode did not retain the exported environment. Restarting this temporary server with `--no-reload` preserved its environment and allowed ordinary registration/login and cookie reads. No project configuration was changed to work around this.

## Automated evidence

Tests were first run before adding source and failed on missing catalog modules/components. After integration, lint, TypeScript + Vite production build, and all 41 existing/new frontend tests passed. An additional pagination filter/origin regression was then added; final run passed all **42 tests**, lint with zero warnings, and the TypeScript/Vite build.

Commands: `npm run lint`, `npm run build`, `npm test`, `git diff --check`. Protocol tests cover cookie GETs, all six paths, encoded filters, no bearer/body/CSRF mutation, safe 401/404/422/429/5xx/HTML/network errors, body timeout, caller cancellation, obsolete success/401 suppression, explicit DTOs and untrusted links. Draft/storage tests cover legacy workspaces, duplicate comma-containing Unicode names, UUID/local identity separation, zero coordinates, null Venue/timezone, empty lineup, unknown dates, midnight seconds, Venue UUID reuse without mutating locals, and independent Song/performer reference detachment. Markup tests are not browser interaction proof.

## Browser observations

| Case | Actual observation | Coverage |
| --- | --- | --- |
| Guest/manual | Catalog controls disabled with sign-in/manual explanation. Saved `Manual Ñ QA` with one `Björk, guest` chip and unknown date; count increased from 24 to 25. | Passed core manual path; new manual Venue/Tour and guest note/reorder not exercised. |
| All six reads | Registered disposable fixture account through UI. Event search showed Sample Concert; selection loaded separate fresh detail preview. Artist, Venue, Tour and Song searches showed seeded records. Artist search was also used in performer slot. | Passed live UI/API path; full browser Network header capture unavailable. Header/body properties proved by fake-fetch tests; server output confirmed paths. |
| Explicit confirmation | Before adoption, typed title `Keep until confirmed` and personal note. Preview showed facts without changing title. `Use these details` replaced title/lineup/Venue/date/Tour; personal note remained. | Passed adoption; dismiss-preview scenario not exercised. |
| Local save/reopen | Saved selected Artist/Venue/Tour draft; count became 26, Venue count 7. Reopened Sample Concert with year-only 2026, blank time/timezone, Sample Artist/Tour and original personal note. Song/performer selection saved and reopened. | Passed UI reopen; UUID storage/reload checks covered automatically, not inspected through browser storage. |
| Venue reuse | Selected the adopted Sample Venue again before Save; only one new local Venue appeared. | Two separate concert reuse/equal-name manual case covered by pure tests, not browser. |
| Partial facts | Confirmed seed's year-only date and unknown time/timezone remained so when reopened. | Passed seed UI; null Venue/empty lineup/zero/seconds use automated fixtures only. |
| Independent choices | Song selection copied Sample Song while `My independent performer` remained unchanged. Then explicitly selected Sample Artist in performer picker and saved. Tour lookup displayed roster; selection retained lineup. | Passed. |
| Rapid/cancelled reads | Debounced searches and result selection exercised. | Delayed/throttled UI races not exercised; latest-read cancellation and obsolete 401 tests passed. |
| Pagination | Single real page displayed disabled Previous/Next. | Multi-page UI unverified; client page/filter/origin regression covered. |
| Offline/errors | Real session expiry exercised. | Browser offline/retry and detail 404 unverified; safe failure cases tested at client boundary. |
| Setlist expiry | Deleted sessions only in disposable database while editor had unsaved `Dirty setlist survives`; next search returned unauthorized. Account became Demo; both lookups disabled; editor remained with dirty title; Save entry succeeded. | Passed real 401/draft/save. |
| Concert expiry | Signed back in through normal UI, opened a new dirty concert with title and note, expired only disposable sessions, searched Event. Lookups disabled while drawer/title/note remained. Cancel returned to unchanged 26-concert collection. | Passed real 401/draft/cancel. |
| Account races | Lookups are disabled during account mutations; hook refuses expiry during an in-flight mutation. Existing account tests passed. | Delayed browser logout/profile race unverified. |
| Layout/keyboard | Desktop screenshot visually inspected; preserved ivory/lime/lavender styling and readable inline controls. Native focus returned to Song search after selection. | 390px mobile, full Tab/Space/Enter/Escape sequence and overflow measurements unverified. |
| Existing collection | Navigated dashboard, concerts and setlists after saves; same 26 concerts survived expiry and ordinary re-login. | Calendar/map, second account, browser reload, Undo/reorder manual acceptance not exercised in this run; existing pure tests remain passing. |

Screenshots: `catalog-expired-setlist.png`, `catalog-expired-concert.png`. They contain only disposable fixture content. These show the preserved drafts with disabled guest catalog controls.

## Remaining limits

Browser automation exposes native accessibility interaction but no network emulation or viewport resizing capability in this session. No browser Network header trace was captured. The unverified matrix items above are acceptance gaps, not claimed passes. Automated checks do not replace their browser proof. Temporary servers were stopped after verification; the temporary database can be discarded.
