# Concert wizard / MusicBrainz verification — 2026-10-25

Implemented locally on `feat/live-event-data-layer`, based on `38099a5`. No commit, staging, push, PR, merge, deployment, account change, or real provider request was performed. Existing planning documents remain untracked and preserved.

Sources: [implementation plan](../superpowers/plans/2026-10-25-concert-wizard-musicbrainz.md), [approved design](../superpowers/specs/2026-10-25-concert-wizard-musicbrainz-design.md).

## Implemented behavior

Creation now uses Artist → Tour → Event → Setlist, one in-memory draft, and a single final transactional Attendance save. Existing concert editing and immediate saved-setlist editing remain available. Local/manual catalogs work independently of provider availability. Pending Artist/Song metadata travels only in a top-level request sidecar and is stripped from saved collection state.

Authenticated Artist/Work reads normalize and cache public suggestions, share an atomic outbound pacing gate, attach current local matches, and issue expiring encrypted selection proofs. No browser request goes directly to MusicBrainz. Final saves validate references/proofs before writes, resolve identities inside the existing transaction with nested savepoints, and reuse existing provider identities without changing shared facts. Notes, text-only unresolved Songs, repeated Songs, and independent performers remain supported.

## Automated evidence

- PHP 8.5 / Laravel 13.38.0; runtime prefixes read from the machine configuration.
- `cd backend && php artisan test --compact`: **191 tests, 190 passed, 3,526 assertions, one skipped**. The skip is the dedicated PostgreSQL two-process race check.
- `cd backend && vendor/bin/pint --dirty --format agent`: passed after formatting; full backend suite rerun afterward.
- `cd frontend && npm test`: **61 tests passed**. Includes strict provider transport/decoding/deadline/cancellation, keyboard transitions, draft retention/pruning/identity clearing, optional/repeated entries, sidecar transmission, SSR single-form/accessibility contracts, and preserved existing API/UI/domain checks.
- `cd frontend && npm run lint`: passed with no warnings.
- `cd frontend && npm run build`: TypeScript and Vite production build passed.
- `git diff --check`: passed.

New backend lookup/save tests fake HTTP and prevent stray requests. They cover authentication, strict query/body rejection, normalized optional metadata, literal query escaping, writer/performer separation, current local matches on cache hits, empty caching, shared gate/lock behavior, bounded Retry-After, safe malformed/upstream failures, invalid/wrong-kind/expired proofs, mapping validation, no-write rejection, identity reuse across accounts, manual versus unresolved Songs, repeated Songs, existing Event reuse, and full rollback after duplicate resolved lineup identities. These are concrete offline checks, not live provider quality measurements.

## Browser evidence and limits

Used a temporary Vite fixture that mounted the real production wizard with deterministic in-memory catalog/provider clients and a nonpersistent final-save callback. It did not access the app database or an account. The fixture files, server, browser tab, and viewport override were removed afterward; no production debug bypass remains.

Observed with actual browser interactions:

- Manual Artist confirmation; Next disabled while an inline editor was open; Back retained the confirmed Artist.
- Optional Tour navigation; Event fields expose type, Venue, precision, time/timezone, flags, and notes.
- Empty lineup with a supplied title, unknown date/timezone, and an empty optional Tour path reached Setlist.
- Event Next stopped on Setlist without saving. Browser testing caught React reusing the Next button as Save during the same click; the Next handler now prevents its default before changing steps, and the interaction was retested successfully.
- Text-only song plus encore note staged in memory; final Save returned exactly two rows through the fixture callback. No intermediate save occurred.
- Same-name provider Artist candidates displayed separate context, including missing country; ArrowDown/Enter selected the explicitly chosen second candidate and opened confirmation fields.
- Editing a provider-prefilled Artist label visibly changed its source to Manual entry.
- A reviewed Work selection retained an independently entered performer; Use text only cleared the pending Song selection while retaining the row text/performer.
- A 390 × 844 viewport showed usable Artist creation fields, step indicator, and footer controls without clipping. This is a narrow-screen spot check, not the whole responsive matrix.

The complete browser acceptance matrix has **not** been run. Remaining interactive checks include real authenticated save/reload, Event preview/adopt/detach interactions, session expiration/same-account recovery/different-account isolation, stale asynchronous searches during session changes, full failure/reconciliation behavior, IME composition, and all responsive long-label cases. Pure, transport, SSR, and backend tests cover portions of those behaviors but do not establish their complete interactive acceptance.

## PostgreSQL and live provider limits

The PostgreSQL server answered on `127.0.0.1:5432`, but the documented current-user connection failed because that role does not exist. Active backend configuration selects SQLite and supplies no PostgreSQL connection user. No roles/accounts/databases were created, no alternative accounts were guessed, and no PostgreSQL migrations were run. `MusicBrainzConcurrencyTest` and its worker fixture implement the two-process Artist/Work barrier/savepoint test, guarded to run only on the dedicated `spectacles_testing` database; this integration proof remains unrun.

At initial verification, no MusicBrainz enablement/User-Agent contact was configured in the active backend environment. No real identifying contact was invented and no live prefix searches were attempted. Manual entry remained available with the default disabled integration.

### Local activation follow-up — 2026-10-25

The user supplied an operator contact. Added `MUSICBRAINZ_CONTACT` to the example configuration and stored the supplied value only in the ignored backend `.env`, with `MUSICBRAINZ_ENABLED=true`. A blank full User-Agent override now derives `Spectacles/1.0 (contact)` from that setting; an explicit full override remains supported. Cleared the Laravel configuration cache and verified that the effective integration is enabled with the expected identifying User-Agent.

Retried the user's existing `Rolling` search in the authenticated wizard at `https://spectacles.test/`. Live MusicBrainz candidates appeared, including The Rolling Stones, Rolling Contact, and Rolling Blackouts Coastal Fever. No candidate was selected or saved. This establishes the live Artist prefix path; Work lookup and the remaining authenticated acceptance matrix are still unverified.

After the configuration change, `php artisan test --compact tests/Feature/MusicBrainzLookupTest.php` passed: **8 tests, 54 assertions**. Pint and `git diff --check` passed. The contact value is omitted from tracked documentation and screenshot evidence.

## Scope review

No migrations, dependency changes, durable domain DTO changes, provider synchronization, browser draft storage, public setlist catalog, or additional providers were introduced. The existing release branch was reused. Tests and documentation are local changes only.


### Tour autocomplete extension — 2026-10-25

Extended the existing read/selection/save path with authenticated `/api/v1/musicbrainz/tours`, using the upstream `series` resource with escaped name prefixes plus `AND type:tour` and returned-type validation. Series uses the same provider cache, pacing gate, timeout, identity proof and throttle. Existing linked Tours reuse their saved name/UUID without mutation. New confirmed Tour identities resolve atomically through optional paired `pendingCatalog.tours` / `tourChoices`; legacy clients and manual Tour saves retain their previous contract. Final durable Tour choices contain only name and catalog UUID.

Verification performed for this extension:

- `cd backend && php artisan test --compact`: **199 tests, 198 passed, 3,639 assertions, one skipped** (the guarded PostgreSQL race test).
- `cd backend && vendor/bin/pint --dirty --format agent`: passed after formatting test changes. Explicit Pint on all five changed provider/resolver/rules source files passed too.
- After formatting, focused lookup/save/race/provider-reference/collection tests: **36 tests, 35 passed, 295 assertions, one skipped**.
- `cd frontend && npm run lint`: passed. `npm test`: **71 passed**. `npm run build`: passed.
- Fake HTTP tests prevent stray requests. New cases cover Tour-only Series filtering/query namespace, current local matches on cached results, empty/error/malformed responses, literal escaping, the shared gate, cross-account identity reuse, old and empty sidecars, half maps, orphan drafts, invalid positions/names/proofs, wrong-kind/expired proofs, selected-Event exclusion, namespace collisions, late rollback, distinct same-name Series, and null-proof manual Tours. The existing two-process race harness now includes Series; its PostgreSQL execution remains unrun.
- Pure/transport/SSR cases cover the fixed first-party Tour route, strict expected-kind decoding, Tour sidecar slot mapping and pruning, metadata stripping, duplicate identities, name edits clearing proofs, Artist/Song/Tour draft-key collisions, and the local-only Artist-filter label.

Actual browser interaction used a temporary isolated fixture mounting the production `WizardTourStep` with fake clients and a nonpersistent payload-inspection callback. Observed local and MusicBrainz source groups with separate same-name context; a provider selection opened review fields and disabled navigation until confirmation; duplicate MBID confirmation added no duplicate, while a second same-name MBID remained selectable. Renaming the suggestion changed its source to Manual Tour. A fake 503 retained local selection and manual creation. Inspected final payloads had provider input only in the optional sidecar and clean durable choices, with manual/local choices retaining their normal shape. The local client alone received `artist_uuid`; the provider client received only kind `series` and the typed name. Removing a choice updated the final payload. The temporary files, server and browser tab were removed.

This component fixture does not establish real authenticated save/reload, full-wizard Event detach/adopt interaction, session-change races or IME interaction. Backend and pure tests cover the relevant transactional, Event-reference, pruning and keyboard contracts, but those remaining interactive paths are still unverified. No test Attendance was saved through the user's live browser draft. No commit, staging, push, PR, merge or deployment was performed; no dependencies, migrations, durable DTO fields, account settings or provider synchronization were added.

### Live Tour QA, autocomplete policy, and Artist scope follow-up — 2026-10-25

The subsequent native in-app-browser Tour pass executed seven cases, all passing: live Eras lookup, explicit provider confirmation, Back/Next retention, one labeled QA concert saved, application reload with the selected Tour visible in normal Edit, local Tour reuse, and empty-result/manual fallback. The owned QA concert was retained for inspection; extra drafts were cancelled and the original user tab/draft preserved. This establishes the original Tour UI save/reload path, not raw provider identifiers or PostgreSQL concurrency.

Search controls were then changed to database-first lookup, a three-Unicode-character typed threshold, empty browsing capped at five local values, and removal of visible source headings. Production-used orchestration tests cover successful-zero-only provider fallback, errors, cancellation, provider availability, and page sizing. A native Safari fixture with real components confirmed browsing, short-input hints with unchanged call logs, local hits suppressing provider calls, sequential provider fallback, and matching legacy local-only behavior. These fixture observations are not authenticated network/save proof.

The final Artist-scope addition makes Tour results match any selected persisted Artist UUID or confirmed pending Artist MBID. Local matching uses existing TourArtist links and provider references. MusicBrainz lookup validates the requested Artist and the exact Tour relationship targeting a Tour Series. Complete relation sets cache per Artist; at most one cold Artist is fetched per request, with recoverable 429 while further selected identities remain unresolved. No association writes or save-contract changes were introduced. No usable identity keeps manual entry without global Tour queries.

Final combined checks before the local commit:

- Full backend `php artisan test --compact`: **201 total, 200 passed, 3,678 assertions, one skipped** (dedicated PostgreSQL concurrency).
- Latest full frontend checks on the final implementation: **77 tests passed**, lint and production build passed.
- Latest focused scoped backend checks: **69 total, 68 passed, 2,201 assertions, one skipped**; Pint and `git diff --check` passed.

Only the no-Artist guidance, empty client-call log, and usable manual/skip actions were observed in the final scoped native fixture. Further browser cases stopped when shared Safari changed to unrelated windows between observation and action; the subagent's in-app browser inventory was unavailable. Its temporary fixture server/source were removed, and the stopped fixture window could not safely be closed. Artist-A browsing, multi-Artist union interaction, scope-change races, and authenticated scoped provider/save/reload acceptance remain unverified in the browser. Automated tests cover these data/query boundaries, but neither the earlier unscoped Tour QA nor fixtures establish the complete final scoped user journey.

Private QA reports/screenshots and operator configuration remain in ignored local paths and are not part of the commit. The PostgreSQL environment limitation and outstanding browser acceptance are retained as explicit verification limits.

## Local creation-draft recovery — 2026-10-25

The subsequent explicit request to save the creation form locally supersedes the original no-browser-draft-storage scope for new concerts only. Each stable account UUID now namespaces one bounded, versioned creation snapshot. Normal dismissal and reload retain the step, entered fields, confirmed identity sidecars, pending editors, query text and explicit Event-change decisions. Only confirmed successful creation or explicit Discard draft removes that snapshot. Saved-concert editing and server collection ownership remain unchanged.

Necessary encrypted MusicBrainz public-identity proofs are preserved, with the existing server expiry/identity validation intact. No auth credentials/cookies/CSRF values, fetched result caches or saved collection mirrors are included. Invalid/unknown-version snapshots remain untouched until explicit discard or successful creation; storage failures warn without blocking editing/server saving. Writes happen synchronously on changes, with no delayed/unmount write that could resurrect a cleared draft. A creation form is bound to its captured owner and keyed again inside ConcertWizard for owner-prop changes.

Verification completed:

- Focused recovery tests: **12 passed** (8 storage/decoder/lifecycle cases; 4 Vite SSR component cases). Existing focused wizard tests: **14 passed**.
- Full frontend suite: **89 passed**. `npm run lint`, `npm run build`, and `git diff --check` passed. No PHP commands or backend edits were necessary.
- Native Safari exercised a separate `http://127.0.0.1:5178/recovery-fixture` tab mounting the actual wizard with deterministic disabled clients, synthetic account UUIDs and a nonpersistent save callback. No backend requests or domain records were created by this fixture.
- Observed: Artist name/type/disambiguation survived X dismissal and full page reload; Tour query and unconfirmed name survived Escape; Event title, notes, tickets flag and unknown-date precision survived Cancel; unconfirmed Setlist row/title/performer/tape plus nested Song fields survived dismissal; nested performer fields survived account A→B→A; B opened an empty wizard; failed save retained the staged row after outside-click/reopen; confirmed synthetic save produced a fresh next creation; explicit discard removed a typed query.
- The IAB browser inventory was empty, so this was a native Safari component-fixture check, not a new real authenticated production-save test. Existing account/session and the retained QA concert were untouched.

Sanitized evidence: `/Users/pronestivm/Projects/spectacles/qa-output/concert-recovery-20261025/nested-song-restored.png` (cropped to fixture content; browser/contact details excluded). The ignored fixture and report are in that same owned directory. Native date controls retain completed date values exposed to React; incomplete hidden browser date segments are not exposed as serializable text. Partial year/coordinate strings accepted by the state decoder are retained without requiring submission validity; coordinate inputs use decimal text entry to preserve unfinished signs/decimals.

No staging, commit, push, pull request, merge or deployment was performed.
