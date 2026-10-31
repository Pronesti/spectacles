# Hybrid catalog form assistance verification — 2026-10-25

## Implemented behavior

Backend provider contracts, opt-in configuration, authenticated reads, normalized public selection proofs, automatic Tour IDs, historical API discovery, primary-container upcoming HTML parsing, MusicBrainz Place metadata, optional attributed Commons metadata, and transactional source Venue/Event resolution are implemented. The existing local creation recovery prerequisite remains integrated; frontend/browser results are recorded below as completed.

Provider search/preview does not write catalog rows. Final Save validates proofs without upstream HTTP and uses provider-scoped references rather than names. Local shared facts are reused without overwrite; changed Event facts return an atomic conflict. Upcoming show IDs remain distinct from performance IDs. Source songs and city coordinates are excluded. If historical HTML Tour enrichment fails, structured recoverable error data retains API-confirmed Artist/Venue/date and the unsigned Tour name for explicit manual creation, without a Tour identity or usable performance binding. Existing local Events bypass unnecessary HTML enrichment.

## Automated commands and results

Executing machine runtime was checked from `~/.config/agent-skills/runtime.md`: plain `php` and `composer` from `backend/`. Versions: PHP 8.5.10, Laravel 13.38.0, Sanctum 4.3.3, PHPUnit 12.5.40.

- Baseline frontend wizard/recovery focus: 22 passed.
- Baseline backend MusicBrainz/provider-reference/collection focus: 36 passed, 1 PostgreSQL-only skip; 319 assertions.
- Backend complete SQLite suite before final source-match hardening: `php artisan test --compact`: 233 passed, 2 PostgreSQL-only skips; 3,894 assertions.
- Backend focused checks after source-match hardening: `php artisan test --compact --filter='MusicBrainz|CatalogProviderMetadata|JsonApiResources|VenueProviderLookup'`: 80 passed, 1 PostgreSQL-only skip; 1,191 assertions.
- Final backend gate after added failure/rollback/source-match regressions: `php artisan test --compact`: 244 passed, 2 PostgreSQL-only skips; 3,972 assertions.
- Cached real-source parser verification (no new provider requests): upcoming-inclusive page returned ten primary result cards with a next page; the checked empty Ken-Y page returned zero results with no next page.
- `php artisan route:list --path=api/v1/providers --except-vendor`: six authenticated provider routes listed (Place routes are under `/musicbrainz/places`).
- Final frontend gate: `npm test`: 105 passed, zero failures/skips; `npm run lint` and `npm run build` passed.
- Frontend coverage includes exact provider DTO/URL validation, source ID separation, held/cancelled reads, explicit external reads after local failures, complete group transitions, manual fallback, changed Venue binding cleanup, bounded rejected-proof guidance, v1 migration and v2 recovery.
- `vendor/bin/pint --dirty --format agent` ran; only task-related PHP files were formatted.

All product provider checks use deterministic HTTP fakes and prevent stray HTTP. Minimal HTML fixtures preserve the actual main structure/semantic links while excluding scripts, credentials, songs and raw upstream pages. Parser checks distinguish legitimate empty results from challenges/template failure, handle relative links and website `page` pagination, and reject show URLs without a performance identity. Discovery regression coverage preserves both halves of a twenty-result API page and preserves the exact cursor after a rejected Tour candidate.

## PostgreSQL concurrency

The dedicated `spectacles_testing` connection was probed using current configured host/user without guessing credentials. It is unavailable: PostgreSQL at `127.0.0.1:5432` reports that configured role `root` does not exist (SQLSTATE 08006). No role/database was created and no PostgreSQL migration was run.

The runnable process tests are retained. `CatalogProviderConcurrencyTest` requires that exact dedicated database and proc_open support, precreates shared catalog identities in committed setup, then coordinates two Event-reference inserts at a deterministic barrier. It asserts identity reuse/no orphan graph and a competing different-title conflict. Ordinary SQLite uniqueness tests do not constitute the missing two-process PostgreSQL proof.

## Authenticated browser observations

The fake-backed real backend fixture was migrated only on a new ignored SQLite file under `qa-output/catalog-providers-20261025-130512/`. It serves real authentication, sessions, Attendance transactions and collection responses at `http://localhost:18081`; provider requests are faked and unmatched calls blocked. Frontend origin is `http://localhost:15174` with `VITE_API_BASE_URL=http://localhost:18081`. Runtime secrets live only in the task-owned ignored `runtime.env` (mode 0600), never tracked or printed.

Launch from `backend/`:

```sh
set -a
source ../qa-output/catalog-providers-20261025-130512/runtime.env
set +a
php artisan migrate --no-interaction
php -S localhost:18081 tests/Fixtures/catalog-provider-http.php
```

Frontend launch from `frontend/`:

```sh
VITE_API_BASE_URL=http://localhost:18081 npm run dev -- --host localhost --port 15174
```

Health smoke check returned `{"status":"ok","service":"spectacles-api"}`. Two disposable synthetic QA accounts exercised the actual browser, Sanctum sessions and Attendance endpoint. No normal app database was used.

| Case | Actual observation |
| --- | --- |
| Historical | Selected MusicBrainz Artist, automatic setlist.fm Tour, then historical performance. Reload before Apply restored an explicit Resume/Dismiss preview and the Unicode personal note. Apply filled the group and cleared unavailable timezone; final Save succeeded. |
| Upcoming | Applied and saved the October 30, 2026 performance. Database audit confirmed `b4cc196`, distinct from show path `2b893c8a`; time/timezone stayed null. |
| Local authority/detach | Selecting the already saved historical performance displayed saved relationships; Artist/Tour/Venue/date and shared Event fields became read-only. Explicit detachment unlocked them. The detached manual Event saved with no performance reference. |
| Venue | Reviewed address, alias, precise coordinates and image attribution; confirmed a MusicBrainz Place association. Past history returned the historical performance, upcoming history returned the future performance independently of the main picker. |
| Account isolation | A retained an Event-step draft and note; B opened a blank Artist step with an empty collection and wrote a distinct query. Returning to A restored its step, note and details. Held late-response suppression is separately automated, not a held browser network test. |
| Expiry | Advancing the fixture clock 25 hours produced explicit Performance/Tour reselection guidance and retained the draft. Reselecting the source at the new clock allowed Save. |
| Failed save | A simulated failure retained fields and blocked another write until collection reconciliation; reopening recovered the same draft. The original fault-router rejection used an unbound helper and returned 500. The fixture was corrected and a separate isolated HTTP check proved its intended 503 JSON response, credentialed CORS and no database writes. |
| Uncertain response | The real detached manual save committed, then the fixture replaced its response with 503. The draft stayed open and Save disabled until Reload collection. The collection then contained exactly three Attendances; no mutation replay occurred. The retained draft was explicitly discarded after inspecting the committed record. |
| Clear lifecycle | Confirmed source saves opened a fresh Artist step on the next creation. Explicit discard also opened a fresh draft without restoration notice. |

The final read-only SQLite audit found three Events/Attendances, two source Event references, one Artist, two Tours, one enriched Venue and zero Songs/setlist entries. Each source Event has one Artist and one Tour. The two source Tour IDs are `2bde703a` and `bdac5de`. The Venue retains Place and setlist.fm references. The third Event, “Manual reconciliation check,” has no source Event reference. These are fixture identities, not live-source coverage claims.

Screenshots are ignored local QA artifacts: `historical-saved.png`, `upcoming-saved.png` and `reconciled-saves.png` in the task-owned QA directory. The final screenshot shows all three saved rows at the available 662 px browser width. Both task-owned servers were stopped after verification; unrelated local sessions/data were retained.

## Local recovery matrix

Automated recovery checks cover the unchanged owner-specific key, strict v1 migration without an eager storage write, v2 round trips of source groups and unfinished Venue fields/metadata/linked references, corrupt/unknown/oversized data preservation, storage quota/read/removal failure, successful-save/discard clearing and stale-callback non-resurrection. Existing partial Artist/Tour/nested setlist editor SSR checks remain green. No auth state, full result pages, raw HTML or collection mirror is retained.

Browser proof covers reload of an unconfirmed preview with explicit Resume, retained personal notes and step, failed-save recovery, A→B→A isolation, expired-proof rejection/reselection, confirmed save clearing, uncertain-response reconciliation and explicit discard. The exhaustive browser-only matrix for partial inputs at every step, legacy-v1 save, corrupt/full storage, held network/account transitions, IME/keyboard and fixed 390/1440 px layouts was not run; automated coverage of those contracts is not a substitute for that remaining manual matrix.

## Provider configuration and permission dependency

setlist.fm and Venue-image sources remain disabled by default; zero quotas mean unavailable. No provider account, issued key, production setting or deployment was changed. Provider permission for HTML retrieval and retention of reviewed prefills/references/recovery, API entitlement and configured quota values remain external operator dependencies. User confirmation is not a substitute for operator permission.

## Remaining failures or environment limits

- PostgreSQL process proof is blocked by the configured role's absence, as above.
- The additional exhaustive browser matrix listed above remains unperformed. Main source save/reuse/recovery paths were exercised on the actual wizard.
- Live source activation/permissions and deployment are outside this implementation's authorization.


## UI consolidation follow-up — 2026-10-25

The four review findings are fixed in the current checkout: one shared query each for Tour/Event/Venue, inherited single-Artist context, conditional multi-Artist source scope, selected summaries before discovery and removal of the duplicate Venue dropdown. Independent source feedback/cancellation and the existing Apply/detach/local-authority rules remain. Legacy differing recovery query text is retained behind an explicit recovered-search choice.

Final frontend gates for this follow-up: 108 tests passed; lint and TypeScript/Vite build passed. The parent executed eleven browser cases and all passed on the final source, including real UI selection of two identified Artists, source action from an open local popup, Places metadata adoption, complete group Apply and note retention, saved-Event detachment, Back/reload recovery, source failures/manual continuation and 390/1440 px layouts. No final database Save was clicked in this focused run and the backend was unchanged. Backend results above are prior implementation evidence, not a new backend run.

Two follow-up defects found during testing were fixed and replayed: empty selection markup inflating Tour spacing, and source buttons moving on popup blur before click dispatch. Places now explicitly renders loading/empty/error states. A failed HMR websocket required full reload for later source verification.

Detailed ledger/screenshots: `qa-output/catalog-providers-ui-fix-20261025-141054/report.md` (ignored local evidence). All 11 scoped cases passed; browser conflicting-legacy-snapshot restoration, full accessibility/IME, other engines and live-source checks remain outside this follow-up. Only owned QA state and servers were cleaned up. Changes remain uncommitted and providers disabled by default.


## HTML-first revision (2026-10-25)

This revision supersedes the historical API architecture and API entitlement requirements above. All setlist.fm reads now use public website HTML; no API key or `api.setlist.fm` request is required. MusicBrainz remains API based and resolves an Artist only through an explicit `setlistfm` URL relationship. Canonical website Artist ID and exact MBID are independently checked on the Artist page; missing/conflicting evidence remains unavailable rather than matching a name.

Tour IDs come directly from that verified Artist page. Past/upcoming summaries, exact Tour associations, detail previews and Venue metadata use bounded HTML parsing, canonical URL checks, normalized caches and the shared website quota/pacing gate. A chained read may wait at most two seconds for spacing; upstream errors are never automatically retried. Verified summary values can support explicit manual entry after detail failure, without a performance or Tour proof. Existing local Event adoption remains authoritative.

Captured public HTML fragments from Radiohead's Artist page, Joker Out's 2025-08-07 performance and Allstate Arena's Venue page exercise actual selectors and IDs. The fixtures omit unrelated page infrastructure/scripts and retain source metadata; transformed synthetic discovery fixtures stay offline. Discovery→preview→Save regression proof verifies no API key, no setlist.fm API request and no upstream HTTP during Save.

Live application-service read: Radiohead MBID resolved through MusicBrainz to its canonical Artist page, and Tour lookup returned `European Tour 2025`. Live past search and the ID-only Venue route returned recoverable 503; bounded `/search` samples returned 202 with empty bodies. Exact canonical Artist and Venue pages were readable during source inspection. No access challenge was bypassed and no zero-result success was reported for these failures. Browser/account/draft state was not modified and no domain data was created by live reads.

Only ignored local development configuration was activated: `SETLISTFM_ENABLED`, `SETLISTFM_HTML_INTERVAL_MS`, `SETLISTFM_HTML_DAILY_LIMIT`, `SETLISTFM_CACHE_SECONDS`. Identification reuses existing MusicBrainz configuration; no contact value or credential is included in tracked files. Production defaults remain disabled/zero quota. No commits, push, PR, merge or deployment occurred.

Final automated gates for the HTML-first revision: backend 257 tests (255 passed, two PostgreSQL-only skips), 4,017 assertions; frontend 108 tests passed; frontend lint and production build passed; Pint and `git diff --check` passed. Live evidence above remains separate from offline fixture-backed proof.


## Canonical navigation correction (2026-10-25)

This follow-up fixes the two live route failures recorded above. Artist-scoped performance discovery now uses the verified canonical Artist listing and the form navigation exposed by that page, rather than `/search`. It reuses the normalized first-page data already fetched for Artist identity/Tours. Source-supported Tour/country/Venue/year filters are checked against selected form values; text and exact-date filtering run locally over bounded candidates. Website result pages always remain partial. Upcoming source widgets are kept separate from historical pagination; `/upcoming/...` links lacking explicit performance identity are excluded rather than guessed. Venue-only Tour/country combinations require an identified Artist (422), rather than silently ignoring those filters.

Venue lookup/history now receives an exact `venueUrl`, validated against the fixed HTTPS host, allowed resource path and requested Venue ID, with no query/credentials/fragment. A normalized optional `venueUrl` is retained for explicit MusicBrainz Venue relationships, source suggestions, signed proofs, creation bindings/editors and history-filter recovery. Confirmed Save stores it on the matching `venue_provider_ids.source_url`; existing Venue facts remain authoritative. A saved ID without a source URL produces actionable guidance to reselect a source Venue, never a guessed website slug. A migration adds only the nullable source URL column; rollback/reapply and confirmed-save metadata coverage pass.

Live application-service reads (ordinary configured provider HTTP, no fixture transport):

- Radiohead past search: 10 rows, first performance `34cc517`.
- Radiohead search→preview: verified `Radiohead`, `Royal Arena`.
- Radiohead exact Tour filter `2bdd20ea` (`European Tour 2025`): 10 rows.
- Exact Allstate Arena Venue lookup: `Allstate Arena`, `Rosemont`, `US`, canonical ID `73d6ca11`.
- Allstate Arena Venue-only history: 10 past rows and 10 upcoming rows; first upcoming performance `b4cc196`.
- The first Venue-only historical preview (`2b7c7c66`, Magnus Ferrell) parsed its performance page, but the exact linked Artist page `https://www.setlist.fm/setlists/magnus-ferrell-33e6f0d1.html` returned upstream 202. It correctly remains a recoverable 503 rather than issuing an unverified Artist/performance proof. A bounded diagnostic observed that status; no access restriction was bypassed or automatic retry added.

Only the new `add_source_url_to_venue_provider_ids` migration was applied locally; no domain rows were created/changed by live verification. The pre-existing `2026_10_25_000001_extend_catalog_provider_metadata` migration remains pending in this local database and was not applied as part of this correction. No further provider settings were changed. Current browser tabs, account and draft were untouched. These are application-service HTTP checks, not full browser UI acceptance. No stage, commit, push, PR, merge or deployment occurred.

Final correction gates: backend **265 tests (263 passed, two dedicated-PostgreSQL skips), 4,106 assertions**; frontend **111 tests passed**; frontend lint/build, Pint and `git diff --check` passed. Live Radiohead cursor pagination also returned a second page of 10 performances (first `1b40c9c8`) with zero overlap with page one.


## Metadata migration and native app Save/reload acceptance (2026-10-25)

Applied `2026_10_25_000001_extend_catalog_provider_metadata` only to the confirmed local SQLite database at `backend/database/database.sqlite` (`APP_ENV=local`, application URL `https://api.spectacles.test`). Its Tour reference rename/unique index and Venue address/aliases/image additions are independent of the already-applied source URL migration. Migration status now shows every migration as run. Schema checks confirmed the old Tour column absent, `external_provider_id` present, all three Venue metadata fields present, and `venue_provider_ids.source_url` retained. Migration alone left the existing 3 users and 14 attendances unchanged.

Actual browser acceptance used `https://spectacles.test/` in a new Safari Private Browsing window with a dedicated local QA account named **HTML migration QA 2026-10-25**. No existing account was signed out or switched, no original creation storage was cleared, no password was saved to the user's password store, and the original regular Safari window/tab was preserved. No fixture HTTP or alternate application origin was used.

| Case | Result | Native UI evidence |
| --- | --- | --- |
| Identified Artist | Pass | Selected the MusicBrainz Radiohead group result, distinct from similarly named results, then confirmed its draft identity. |
| Artist-scoped Tour | Pass | Radiohead scope returned `European Tour 2025`; explicitly reviewed and confirmed the Tour. |
| Performance search and preview | Pass | Search returned 10 Radiohead performances; selected December 16, 2025 at Royal Arena. Preview showed Radiohead, European Tour 2025, Royal Arena and source link ending `34cc517.html`. |
| Separate Allstate Arena metadata | Pass | Live Place detail showed Rosemont, full Mannheim Road address, Rosemont Horizon alias, and precise Venue coordinates. This Venue was not saved into the unrelated Radiohead record. |
| Exact canonical Venue history | Pass after scoped fix | Allstate's actual MusicBrainz record has two setlist.fm Venue references. The UI now retains each explicit URL, lets the user choose identity `73d6ca11`, and showed 10 Allstate Arena past performances from that canonical source. A transient provider-unavailable response recovered on explicit retry after a normal provider read returned 200; no automatic retry or access bypass was added. |
| Draft continuity | Pass | Reloaded the app during QA; the isolated draft restored its Artist/Tour/Event step and Allstate metadata. |
| Coherent performance apply | Pass | Applied Radiohead/Royal Arena/European Tour 2025/2025-12-16 together, replacing the temporary Allstate selection. |
| Single Save | Pass | Clicked Save concert exactly once. App displayed “Concert saved in the database.” and collection count 1. |
| Reload and persistence | Pass | Full app reload, Concerts list and View showed the exact QA label, Radiohead, Royal Arena/Copenhagen, date and QA note. Edit view showed European Tour 2025; cancelled without saving again. |
| Successful-save recovery clearing | Pass | Add concert reopened an empty Artist step without restored-draft notice or previous selections. Discarded only that newly opened empty QA draft. |
| Cleanup/isolation | Pass | Closed only the owned Private Browsing window; the original regular Spectacles window and its 11 tabs remained. No test server/harness was started. |

The native Allstate case exposed and fixed a real multi-reference omission: a single optional `venueUrl` was insufficient when MusicBrainz explicitly linked multiple website Venue IDs. Bounded `venueSources` metadata now retains each ID/URL pair through normalization, signed proofs, editor/binding recovery and confirmed Save. History chooses the exact URL for the selected identity; save persistence writes each URL only to its matching reference. Unknown or mismatched URLs remain rejected. Focused regressions cover multi-reference proof preservation, URL/ref mismatch rejection, identity-specific history choice, creation/editor recovery and matching-row persistence. No new schema was needed for this correction.

Independent read-only database proof after the UI Save confirmed:

- Exactly **1** attendance for the dedicated QA owner; all other owners still have **14** attendances; total **15**.
- Retained label: **QA HTML migration - Radiohead - 2026-10-25**.
- Attendance public ID: `12d14c7e-a6b9-4ac5-bb65-370da2df8378`.
- Date: `2025-12-16`; Event reference `setlist_fm/setlist/34cc517`.
- Radiohead references: MusicBrainz `a74b1b7f-71a5-4011-9441-d0b5e4122711` and setlist.fm Artist `bd6bd12`.
- European Tour 2025 reference: setlist.fm `2bdd20ea`.
- Venue: Royal Arena, Copenhagen, reference `73d512e9`; stored `source_url` is `https://www.setlist.fm/venue/royal-arena-copenhagen-denmark-73d512e9.html`.
- Venue metadata columns persisted honestly as address `null`, aliases `[]`, image `null`; nonempty address/alias persistence is separately covered by integration regression tests. The temporary Allstate Place was never saved as this Event's Venue.
- The full owned QA note survived the reload; no duplicate Save or deletion of an existing record occurred.

Final gates for this correction: **267 backend tests (265 passed, two dedicated-PostgreSQL skips), 4,118 assertions**; **113 frontend tests passed**; lint, TypeScript/Vite build, Pint and `git diff --check` passed. Native UI proof above is distinct from the fake-backed automated tests and the database read proof. Previously observed source restrictions on other Artists were not bypassed; the positive path used accessible Radiohead. The minimal labeled QA record and its isolated account remain for inspection. Source changes remain uncommitted; no stage, push, PR, merge, production migration or deployment occurred.

## Simplified single-entity creation UI (2026-10-25)

New creation now requires one Artist, zero/one Tour, one Venue and one Event/performance. Each entity uses one query and one result list with source attribution; normal creation removes provider search buttons, separate source lists and Venue-history forms. Successful zero-total local lookup permits automatic online fallback; empty browse requests five locals and one/two-character queries make no reads. Confirmed selections collapse to Change summaries; optional metadata stays in disclosures. Existing edit/catalog schemas and Setlist performer selection remain broader. Legacy multi-choice drafts stay visible until explicitly resolved, and restored pending Venue/history reviews remain resumable.

Prompt-improver read-only research grounded this change; UI/UX Pro Max guidance informed keyboard, controlled-input, disclosure and form decisions. Two generated design references (desktop Event and mobile Tour) were analyzed before implementation. Existing ivory/ink/lime tokens and type family are retained without new dependencies.

Native IAB acceptance used the actual components on isolated localhost:5187 with synthetic public provider responses and a fixture-only Save callback. Passed: empty browse of five locals; two-character threshold; automatic Artist fallback in the same list; keyboard selection; single-Artist summary; zero-Tour continuation; scoped Tour lookup/review; unfinished Tour and Venue reviews after full reload; Venue summary; failed performance lookup/manual fallback; performance preview and explicit Apply preserving notes; final one-Artist/one-Tour/one-Venue payload with proof; successful fixture Save clearing only fixture recovery; legacy multiple-Artist preservation/progression block/explicit resolution; manual Venue and zero-Tour/unknown-date Event continuation; sourced Venue detachment confirmation/cancellation. Desktop 680px drawer and a 390px iframe viewport were visually inspected. These are UI/fixture checks, not a new live backend Save/reload acceptance claim.

The existing live app's Artist/Tour and entered Event/Venue queries were retained; no real concert was saved or deleted. A real `movistar` Venue lookup returned provider-unavailable on initial lookup and an explicit retry; the unified field retained manual entry. This provider limitation is separate from the passing deterministic UI checks. No live-success claim is made for that read. Only owned QA tabs, fixture recovery and temporary fixture/server files were cleaned up.

Screenshots with isolated fixture data: `qa-output/simplified-creation-20261025/event-desktop.png`, `event-mobile.png`, `unified-event-search.png`. Final frontend gates for this revision: **122 tests passed**, zero failures; lint, TypeScript/Vite production build and `git diff --check` passed. No backend changes were made for this UI revision; prior migration/backend test proof above remains historical evidence. No commit, push or deployment.

## Tour accent-insensitive search — 2026-10-25

Provider Tour filtering now compares lowercase ASCII-transliterated strings while returning the original provider name and exact identity. Local Tour search uses the same normalization in a dedicated `tours.search_name` comparison column, maintained on Tour saves and backfilled in chunks of 500 by `2026_10_26_022251_add_search_name_to_tours_table.php`. Display names are unchanged. SQL Artist scoping, literal wildcard escaping, ordering, totals and pagination remain in the existing query; no database extension, global collation change or per-request PHP catalog scan is required. Deployments must run the additive migration before the changed local search code serves traffic.

Actual app acceptance passed in a new Safari **Private Browsing** window at `https://spectacles.test/`, using the real local backend/database and existing development sign-in. Selecting Maná from Your catalog, then entering the exact Tour query `mexico lindo y querido`, visibly returned **México lindo y querido — Your catalog**. Sanitized screenshot: `qa-output/tour-accent-20261025/local-tour-search.png`. The existing normal-browser/IAB account draft was not opened, cleared, overwritten or discarded. This QA private draft was not saved as a concert.

No local Maná or matching Tour existed before this acceptance. With the user's authorization for minimal local QA data, one Artist, Artist MusicBrainz identity, Tour, Tour/Artist link and Tour provider identity were retained using existing factories in one transaction. Read-only database inspection confirmed:

- Artist UUID `01a17605-91db-72c9-af47-cde559ce0a52`: Maná, MusicBrainz Artist `55441123-89a2-49ab-ae3f-df7a3e86835a`.
- Tour UUID `01a17605-91e4-7009-bae5-79add806a257`: original name `México lindo y querido`; comparison value `mexico lindo y querido`; exact provider `setlist_fm` / `tour` / `23de703f`; linked to that Artist.

An initial QA insertion attempt included an unsupported Tour `source_url` column and was fully rolled back before the successful transaction; it retained no duplicate or partial records. No existing rows were deleted. Only the new search migration was applied locally; no production migration/deployment occurred.

Independent automated API regressions prove unaccented, mixed-case accented and decomposed-accent queries, exact Artist scoping against another Artist's same-name Tour, unchanged public UUID/display, updated-name maintenance, backfill of existing rows, and correct page totals across two pages. HTTP is blocked in those tests. A frontend creation-search regression proves an accented local result for this exact unaccented query suppresses both online providers; browse-five/minimum-three policy and nonzero-total behavior remain covered. The native Safari screenshot proves the actual local UI result; it does not claim a captured browser network trace. Direct address-bar API navigation lacked Sanctum's stateful JSON request context and did not supply usable API proof; the independent database read and automated endpoint tests supply the identity/scope evidence.

Actual application provider-service lookup also returned original `México lindo y querido`, exact Tour `23de703f`, for the Maná MBID and exact unaccented query with `Http::preventStrayRequests()` and no fakes: this is **cached service proof**, not a fresh upstream request (`partial=true`).

Verification: focused local endpoint suite passed 46 tests / 2,054 assertions before the added backfill case; final affected backend gate passed **93 tests / 2,427 assertions**. Final full backend suite: **271 tests, 269 passed, two dedicated-PostgreSQL skips, 4,174 assertions**. Final frontend suite: **123 passed** (focused creation-search: nine passed). Pint on changed PHP files and `git diff --check` passed. No PostgreSQL run or fresh upstream fetch is claimed. Changes remain uncommitted; no stage, commit, push, PR, merge or deployment.
