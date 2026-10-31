# Hybrid Catalog Form Assistance Implementation Plan

> **Execution:** Follow the user-selected execution method and repository requirements. This plan does not require any additional skill. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fill the concert wizard from MusicBrainz and setlist.fm suggestions, retain real provider identities, and recover every new creation-form state locally.

**Architecture:** Keep MusicBrainz Artist/Work reads and add Place enrichment. Combine a setlist.fm API client for historical performances with an HTML adapter for automatic Tour IDs and upcoming performances, normalizing both into small authenticated suggestion DTOs. Extend the existing pending-catalog transaction and account-scoped recovery rather than introducing background imports or synchronization.

**Tech Stack:** PHP 8.5, Laravel 13, Sanctum 4, PHPUnit 12, native DOMDocument/DOMXPath, Laravel HTTP/cache/Crypt, React 19, TypeScript 6, Vite 8, Node 24, SQLite and dedicated PostgreSQL testing. No new dependencies.

**Spec:** [Approved design](../specs/2026-10-25-setlistfm-catalog-form-assistance-design.md). Read it with this plan; it owns product scope and acceptance.

## Global Constraints

The following requirements are copied verbatim from the spec and apply to every task:

- “Use API plus HTML: the setlist.fm API supplies historical performance data; a separate HTML adapter supplies Tour identifiers and upcoming performance data.”
- “Automatic Tour ID retrieval is required; pasting a Tour URL is not a prerequisite.”
- “Search, preview and selection do not create catalog records.”
- “An Event selected from setlist.fm represents exactly one artist performance, identified by its setlist ID.”
- “Existing local Events remain authoritative.”
- “Names are display and search text, never automatic identity keys.”
- “Selecting a performance preserves existing setlist entries; this feature does not import the performance's song list.”
- “Keep the existing storage key as the compatibility key, and introduce snapshot payload version 2.”
- “Unknown, corrupt, oversized or unmigratable stored snapshots remain untouched until explicit discard or confirmed successful creation.”
- “No CAPTCHA handling, browser automation or challenge bypass is part of the integration.”
- “A synthetic component fixture alone does not establish authenticated save/reload acceptance.”
- “Plan and implement on the existing feature branch with its same-release dependencies; do not merge or rebase `dev` into it.”

Additional execution constraints: preserve the current dirty recovery/wizard changes; read the executing machine's `~/.config/agent-skills/runtime.md` before PHP/Composer; follow applicable `AGENTS.md` and `.ai/rules` if present; add no dependencies without approval. API keys and permission for production API/HTML use are external activation dependencies, not prerequisites for offline implementation/tests. Do not change provider permissions, accounts, production configuration or deployment as part of this plan.

## Review Focus

1. HTTP 200 contains a challenge or a changed template: show provider unavailability while retaining the form; do not report zero matches (Task 3).
2. An upcoming page's show ID differs from its selected performance ID, or includes other Artists: bind only the selected performance and ignore sidebar identities (Tasks 3–4).
3. An upstream page contains 20 results while the UI displays ten, or all ten displayed results fail a selected Tour-ID check: pagination must neither skip the hidden half nor imply an exhausted catalog (Task 4).
4. Another account saves the same performance after preview, with different shared facts: roll back and offer review of the local Event, without changing its facts or leaving orphan entities (Task 6).
5. Account switching, proof expiry and a late response occur around draft restoration/discard: retain the owning account's text and identity, never apply stale data or resurrect a cleared draft (Tasks 9–10).

---

## Execution preflight and boundaries

This is one connected form-assistance feature. Provider reads, identity resolution, UI and recovery share the same contracts; the tasks below have separate test cycles without separate release branches. Planning was grounded at `61e36db` with the uncommitted recovery changes still present. Do not reset to that revision or overwrite those changes.

- [x] Recheck the checkout and instructions before implementation.

```bash
pwd
git status --short --branch
git log -3 --oneline
cat ~/.config/agent-skills/runtime.md
```

Inspect `.ai/rules/index.md` if its directory exists, then its matching rules and keyword matches. No `.ai/rules` directory was found during planning. This machine uses plain `php` and `composer`; backend commands below run from `backend/`, frontend commands from `frontend/`. At planning time the installed versions were PHP 8.5.10, Laravel 13.38.0, Sanctum 4.3.3 and PHPUnit 12.5.40; DOMDocument and Dom HTMLDocument were available. Recheck on the executing machine with `php -v` and `composer show --direct`.

- [x] At execution time, obtain the user's worktree preference as required by the selected writing-plans workflow. If isolation is chosen, carry the exact recovery prerequisite through an authorized snapshot; a worktree from HEAD alone omits it. Do not stash, discard or commit the user's changes merely to prepare a checkout.
- [x] Identify the existing recovery implementation as a direct prerequisite. Its current files are `frontend/src/concertRecovery.ts`, `frontend/src/components/ConcertRecovery.tsx`, the two recovery test files, and the dirty component wiring. Preserve its accepted behavior and include it in the eventual release through the user's delivery workflow.
- [x] Establish a baseline using existing focused wizard/recovery/provider tests. Existing failures get investigated before new edits; do not label them regressions introduced here.

```bash
# frontend/
node --test tests/concert-wizard.test.mjs tests/concert-recovery.test.mjs tests/concert-recovery-ui.test.mjs
# backend/
php artisan test --compact --filter='MusicBrainz|ProviderReferences|CollectionEndpoints'
```

Commit steps below identify reviewable task boundaries. Execute them only under the user's implementation/delivery authorization, staging the named files or reviewed hunks only. They do not authorize committing the pre-existing work wholesale. Planning itself does not authorize product implementation, commits, pushes, PRs, merges or deployment.

## Implementation evidence (2026-10-25)

Backend Tasks 1–6 are implemented in the current checkout and covered by the final backend SQLite gate: 244 passed, 2 dedicated-PostgreSQL skips, 3,972 assertions. Required Pint formatting ran. Task 12's guarded HTTP router and deterministic two-process race/conflict tests are implemented; the configured PostgreSQL role `root` does not exist, so process proof remains unavailable. The actual fake-backed backend runs only against a new task-owned QA SQLite database. Frontend Tasks 7–11 are implemented with 105 passing tests, lint and production build. The real authenticated browser exercised source saves, expiry, local authority, Venue history, account isolation, recovery/discard and uncertain-response reconciliation. See `docs/verification/2026-10-25-setlistfm-catalog-form-assistance.md` for commands, observations and the remaining exhaustive manual matrix.

The implementation used native capabilities and preserved the user's dirty recovery prerequisite. Pre-implementation red runs were not performed for every new test file; unchecked red-cycle steps below retain that historical distinction. Commit steps remain unperformed because no commit/push/PR/deployment authorization was given. The structured manual fallback extends provider error data rather than issuing an unverified Tour/Event proof.

## File ownership and task order

| Task | Files and responsibility |
| --- | --- |
| 1 | Additive database migration, TourProviderId model/resource/factory and current Tour consumers; Venue metadata schema/model/resources/collection decoder. |
| 2 | `backend/app/Http/CatalogProviderSelection.php`, `CatalogProviderData.php`: proof envelopes and exact normalized contracts; shared synthetic test inputs. |
| 3 | `SetlistFmHtml.php`, `SetlistFmUrl.php`: pure HTML parsing and fixed-host resource identification; sanitized HTML fixtures. |
| 4 | `SetlistFmClient.php`, `SetlistFmDiscovery.php`, `ProviderReadGate.php`, `CatalogProviderController.php`, `CatalogProviderRequest.php`: bounded API/HTML orchestration and authenticated endpoints. |
| 5 | `MusicBrainzPlaces.php`, `VenueImageClient.php`: Place metadata, explicit cross-links, optional licensed image metadata, sharing the existing MusicBrainz gate. |
| 6 | `CatalogDraftResolver.php`, new `VenueDraftResolver.php`/`EventDraftResolver.php`, save request rules and CollectionController: validated atomic persistence and local authority. |
| 7 | `frontend/src/catalogProviders.ts`, `catalogProvidersApi.ts`: typed normalized DTOs, decoding and transport; preserve existing Artist/Work clients. |
| 8 | `frontend/src/providerDraft.ts`, `concertWizard.ts`, `concertDraft.ts`: pure preview/application, detach and pending payload composition. |
| 9 | `concertRecovery.ts`, new `concertRecoverySchema.ts`, recovery context/tests: strict version migration and all new local state. |
| 10 | Provider-aware autocomplete, new `PerformancePicker.tsx`, existing Artist/Tour/Event steps, App/wizard wiring: explicit search, preview and apply. |
| 11 | New `VenuePreview.tsx`/`VenueDraftFields.tsx`, Event step and VenueMap metadata display: Place confirmation, image and independent history sections. |
| 12 | Integration/concurrency tests and verification documentation: complete authenticated save/recovery/browser acceptance. |

New application classes remain under `backend/app/Http/`; new React components remain under `frontend/src/components/`. Test fixtures live under `backend/tests/Fixtures/` and `frontend/tests/`. Do not add an application-wide provider registry, job pipeline, public setlist catalog or independent Venue page.

## Contract decisions shared by the tasks

All JSON names below are exact. Existing Artist/Work DTOs remain unchanged. New provider endpoints return `{data: T[], meta: {next_cursor: string|null, partial: boolean}}`; the frontend decodes this to `ProviderPage<T>`.

```ts
type ProviderRef = {
  provider: 'musicbrainz' | 'setlist_fm'
  resourceType: 'artist' | 'work' | 'series' | 'tour' | 'place' | 'venue' | 'setlist'
  externalId: string
}
type VenueImage = {
  url: string; thumbnailUrl: string; sourceUrl: string
  credit: string; license: string; licenseUrl: string
}
type VenueValues = {
  name: string; city: string; region: string | null; countryCode: string | null
  address: string | null; aliases: string[]; lat: number | null; lng: number | null
  timezone: string | null; image: VenueImage | null
}
type ProviderEntity<T> = {
  ref: ProviderRef; values: T; url: string; selection: string
  localId: string | null
}
type TourSuggestion = ProviderEntity<{name: string}>
type VenueSuggestion = ProviderEntity<VenueValues> & { linkedRefs: ProviderRef[] }
type PerformanceSummary = {
  ref: ProviderRef; artistName: string; venueName: string; city: string
  date: {year: number; month: number; day: number}; url: string; locator: string
  localEventId: string | null
}
type PerformancePreview = PerformanceSummary & {
  artist: ProviderEntity<{name: string; type: string | null; disambiguation: string | null}>
  tour: TourSuggestion | null; venue: VenueSuggestion
  time: string | null; timezone: string | null; selection: string
  localEvent: CatalogEvent | null
}
type ProviderPage<T> = {items: T[]; nextCursor: string | null; partial: boolean}
type ArtistScope = {artistUuid?: string; artistMbid?: string}
type LocalVenuePreview = {localId: string; values: VenueValues; refs: ProviderRef[]}
type PerformanceFilters = {
  period: 'past' | 'upcoming'; artistUuid?: string; artistMbid?: string
  venueId?: string; tourId?: string; tourName?: string
  date?: string; year?: number; countryCode?: string; q?: string; cursor?: string
}
```

`localEvent` is the full existing `CatalogEvent` shape when present, serialized as the normalized frontend shape rather than embedded JSON:API. `localEventId` and `localEvent.id` must agree. New suggestion DTOs carry public identity proofs, not authentication tokens.

Persist Venue `address` as nullable text, `aliases` as JSON with model default `[]`, and `image` as nullable normalized JSON. Bound address to 2,000 characters, aliases to 100 entries of 255 characters, URLs to 2,048 characters, credit to 2,000 and license labels to 255. Validate paired latitude/longitude and their existing ranges. Legacy collection Venue payloads may omit these fields; normalize missing address/image to null and aliases to `[]` in decoders. Add optional `region` and `countryCode` to the collection's Venue DTO and map them to existing `region`/`country_code` columns.

Pending requests retain current `artists/songs/lineup/entries/tours/tourChoices` maps. Add optional singleton `pendingCatalog.venue = {selection: string}` and `pendingCatalog.event = {selection: string}`. Top-level `venue` continues to carry reviewed Venue values. Existing local `catalogId`/`eventId` cannot coexist with a corresponding pending binding. Provider proofs establish references; unsigned frontend `ref` fields never authorize persistence.

### Task 1: Persist Venue metadata and rename the Tour external-ID column

**Files:**
- Create: `backend/database/migrations/2026_10_25_000001_extend_catalog_provider_metadata.php`.
- Modify: `backend/app/Models/TourProviderId.php`, `backend/app/Http/Resources/TourProviderIdResource.php`, `backend/database/factories/TourProviderIdFactory.php`.
- Modify: `backend/app/Models/Venue.php`, `backend/app/Http/Resources/VenueResource.php`, `backend/app/Http/Resources/CollectionResource.php`.
- Modify: Tour-specific branches in `MusicBrainzController.php`, `CatalogDraftResolver.php`, `backend/tests/Feature/MusicBrainzConcurrencyTest.php` and `musicbrainz-race-worker.php`; Tour assertions in `MusicBrainzSaveTest.php`, `ProviderReferencesTest.php` and `JsonApiResourcesTest.php`.
- Modify: `frontend/src/domain.ts`, `catalog.ts`, `concertDraft.ts` and their decoder tests.
- Test: create `backend/tests/Feature/CatalogProviderMetadataTest.php`.

**Interfaces:**
- Consumes: existing Venue/TourProviderId models and collection version 1.
- Produces: persisted `tour_provider_ids.external_provider_id`; Venue metadata and optional collection fields specified above. Other provider tables retain `provider_resource_id`.

- [ ] **Step 1: Pin persistence and backward-compatible decoding with failing tests.**

```php
public function test_metadata_and_both_tour_provider_kinds_round_trip(): void
{
    $venue = \App\Models\Venue::factory()->create([
        'address' => 'Humboldt 450, C1414 CABA',
        'aliases' => ['Arena Ñ'], 'image' => null,
    ]);
    $this->assertSame(['Arena Ñ'], $venue->fresh()->aliases);
    foreach ([['musicbrainz', 'series', (string) \Illuminate\Support\Str::uuid()],
              ['setlist_fm', 'tour', 'bdac5de']] as [$provider, $kind, $id]) {
        $ref = \App\Models\TourProviderId::factory()->create([
            'provider' => $provider, 'resource_type' => $kind,
            'external_provider_id' => $id,
        ]);
        $this->assertSame($id, $ref->fresh()->external_provider_id);
    }
    $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn(
        'tour_provider_ids', 'provider_resource_id'));
}
```

In existing frontend decoder tests, assert old Venue fixtures still decode and a new fixture preserves `address`, `aliases`, `region`, `countryCode` and `image`. In the new PHP test class use `RefreshDatabase` and prevent stray HTTP. Add a duplicate Tour tuple test that expects the database unique violation, plus a rollback migration test preserving a pre-existing MusicBrainz Tour reference.

- [ ] **Step 2: Run the focused tests red.**

```bash
# backend/
php artisan test --compact --filter='CatalogProviderMetadata|ProviderReferences|JsonApiResources|MusicBrainzSave'
# frontend/
node --test tests/domain.test.mjs tests/catalog-api.test.mjs tests/collection-api.test.mjs
```

Expected initial failure: absent metadata columns/renamed Tour field, followed by old decoder loss of added metadata.

- [x] **Step 3: Add the migration, casts and serializers; update only Tour field consumers.**

```php
// Migration up(); down() reverses these operations and restores the old unique index.
Schema::table('tour_provider_ids', function (Blueprint $table): void {
    $table->dropUnique('tour_provider_ids_resource_unique');
    $table->renameColumn('provider_resource_id', 'external_provider_id');
});
Schema::table('tour_provider_ids', function (Blueprint $table): void {
    $table->unique(['provider', 'resource_type', 'external_provider_id'],
        'tour_provider_ids_resource_unique');
});
Schema::table('venues', function (Blueprint $table): void {
    $table->text('address')->nullable();
    $table->json('aliases')->nullable();
    $table->json('image')->nullable();
});
// Venue fillable adds address/aliases/image; casts retain existing coordinate casts.
protected function casts(): array
{
    return ['lat' => 'decimal:7', 'lng' => 'decimal:7',
        'aliases' => 'array', 'image' => 'array'];
}
```

Normalize nullable database aliases to `[]` in resources. Tour lookup/resolution selects its ID column explicitly (`$kind === 'series' ? 'external_provider_id' : 'provider_resource_id'`); never globally replace Artist/Song columns. Update existing Tour tests/factory and race-worker queries together. Extend the frontend Venue type and strict serializers/decoders with the bounds above; preserve unknown-coordinate semantics and paired-coordinate rejection. Do not rewrite old migrations or run migrations on the developer database during this task.

- [x] **Step 4: Rerun Step 2 and run migration checks.**

```bash
php artisan test --compact --filter='DomainMigration|CatalogProviderMetadata|MusicBrainzConcurrency'
```

Expected: SQLite cases pass; PostgreSQL concurrency reports a skip unless the dedicated environment is selected. Record it as a skip, not proof.

- [ ] **Step 5: Review and commit this deliverable when authorized.**

```bash
git diff --check
git add backend/database/migrations/2026_10_25_000001_extend_catalog_provider_metadata.php backend/app/Models/TourProviderId.php backend/app/Models/Venue.php backend/app/Http/Resources/TourProviderIdResource.php backend/app/Http/Resources/VenueResource.php backend/app/Http/Resources/CollectionResource.php backend/database/factories/TourProviderIdFactory.php backend/app/Http/Controllers/MusicBrainzController.php backend/app/Http/CatalogDraftResolver.php backend/tests/Feature frontend/src/domain.ts frontend/src/catalog.ts frontend/src/concertDraft.ts frontend/tests/domain.test.mjs frontend/tests/catalog-api.test.mjs frontend/tests/collection-api.test.mjs
git diff --cached --stat
git commit -m "feat: persist venue metadata and tour external IDs"
```

Before staging any directory, inspect its changes and stage only task-owned files/hunks; the directory entry above covers the named existing tests, not unrelated tests.

### Task 2: Define normalized data and authenticated provider selection proofs

**Files:**
- Create: `backend/app/Http/CatalogProviderData.php`, `CatalogProviderSelection.php`, `CatalogProviderUnavailable.php`.
- Create: `backend/tests/Fixtures/catalog-provider-inputs.php`.
- Test: create `backend/tests/Feature/CatalogProviderSelectionTest.php`.
- Read/reuse: `backend/app/Http/MusicBrainzSelection.php`.

**Interfaces:**
- `CatalogProviderData::entity(array $value): array` and `::performance(array $value): array` validate the exact DTOs above and return normalized arrays or throw `CatalogProviderUnavailable` (introduced here with public `status=503`, `retryAfter=1`).
- `CatalogProviderSelection::issue(string $kind, array $payload): string` supports `tour`, `venue`, `performance` and `locator` version-2 proofs.
- `::read(string $proof, string $kind, string $path): array` returns `{ref, values, linkedRefs?}` or `{ref, artist, tour, venue, date, time, timezone}` for a performance; invalid/expired/wrong-kind values throw field-specific ValidationException.
- `::tour(string $proof, string $path): array` accepts version-2 setlist.fm Tour proofs or translates a valid legacy MusicBrainz Series proof to its provider tuple. Existing Artist/Work proof handling remains unchanged.

- [ ] **Step 1: Create shared synthetic inputs and failing proof tests.**

The fixture file returns this complete reusable data (no credentials or scraped song lists):

```php
<?php
$mbid = '55441123-89a2-49ab-ae3f-df7a3e86835a';
$tour = ['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'tour',
    'externalId' => '2bde703a'], 'values' => ['name' => 'Drama y luz'],
    'url' => 'https://www.setlist.fm/search?artist=4bd7afb6&tour=2bde703a',
    'selection' => '', 'localId' => null];
$venue = ['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'venue',
    'externalId' => '73d6ca11'], 'values' => ['name' => 'Allstate Arena',
    'city' => 'Rosemont', 'region' => 'IL', 'countryCode' => 'US',
    'address' => null, 'aliases' => [], 'lat' => null, 'lng' => null,
    'timezone' => null, 'image' => null], 'linkedRefs' => [],
    'url' => 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html',
    'selection' => '', 'localId' => null];
$artist = ['ref' => ['provider' => 'musicbrainz', 'resourceType' => 'artist',
    'externalId' => $mbid], 'values' => ['name' => 'Maná', 'type' => 'group',
    'disambiguation' => null], 'url' => 'https://www.setlist.fm/setlists/mana-4bd7afb6.html',
    'selection' => '', 'localId' => null];
$performance = ['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'setlist',
    'externalId' => 'b4cc196'], 'artistName' => 'Maná', 'venueName' => 'Allstate Arena',
    'city' => 'Rosemont', 'date' => ['year' => 2026, 'month' => 10, 'day' => 30],
    'url' => 'https://www.setlist.fm/show/2026-10-30/allstate-arena-rosemont-il-2b893c8a.html?setlist=b4cc196',
    'locator' => '', 'localEventId' => null, 'artist' => $artist, 'tour' => $tour,
    'venue' => $venue, 'time' => null, 'timezone' => null, 'selection' => '',
    'localEvent' => null];
return compact('mbid', 'artist', 'tour', 'venue', 'performance');
```

```php
public function test_proof_binds_kind_identity_group_and_expiry(): void
{
    $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
    $service = app(\App\Http\CatalogProviderSelection::class);
    $proof = $service->issue('performance', $f['performance']);
    $this->assertSame('b4cc196', $service->read($proof, 'performance', 'pendingCatalog.event.selection')['ref']['externalId']);
    $this->travel(24)->hours();
    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $service->read($proof, 'performance', 'pendingCatalog.event.selection');
}
```

Add independent tests for corrupted ciphertext, wrong kind, forged ID/group/date, an oversized proof, invalid URL hosts, invalid dates, duplicate linked refs, and legacy Series proof acceptance through `tour()`. Test that issuing a locator cannot authorize a save.

- [ ] **Step 2: Run `php artisan test --compact --filter=CatalogProviderSelection`; expect missing classes.**

- [x] **Step 3: Implement strict envelope issuance/reading and data validation.**

```php
$allowed = ['tour', 'venue', 'performance', 'locator'];
if (! in_array($kind, $allowed, true)) {
    throw new \InvalidArgumentException('Unsupported provider selection kind.');
}
$normalized = $this->payload($kind, $payload);
return Crypt::encryptString(json_encode([
    'v' => 2, 'kind' => $kind, 'payload' => $normalized,
    'expiresAt' => now()->addHours(24)->timestamp,
], JSON_THROW_ON_ERROR));
```

Define private `payload(string $kind, array $payload): array` on the same class. It removes transport-only `selection`, `locator`, local-match fields and unrelated keys, then validates the canonical payload using CatalogProviderData. A performance payload retains all child refs/values plus its exact date/time group. A locator payload contains only `{ref, url, period}` with a validated fixed-host URL and matching ID. Bound encrypted proof strings to 32,768 characters consistently in validators/recovery/decoders; retain the old 4,096-character limit for unchanged legacy fields where no version-2 group is accepted. On `read()`, validate version, supported kind, exact expected kind, expiry and payload again. Read legacy version-1 Series through MusicBrainzSelection, never by accepting an invalid version-2 token as legacy.

- [x] **Step 4: Run proof tests and existing MusicBrainz save tests green; assert issuance/reading makes no HTTP or database writes.**

```bash
php artisan test --compact --filter='CatalogProviderSelection|MusicBrainzSave'
```

- [ ] **Step 5: Commit the normalized proof contract when authorized.**

```bash
git add backend/app/Http/CatalogProviderData.php backend/app/Http/CatalogProviderSelection.php backend/app/Http/CatalogProviderUnavailable.php backend/tests/Fixtures/catalog-provider-inputs.php backend/tests/Feature/CatalogProviderSelectionTest.php
git commit -m "feat: validate catalog provider selections"
```

### Task 3: Parse automatic Tour IDs and upcoming performance identity from HTML

**Files:**
- Create: `backend/app/Http/SetlistFmHtml.php`, `SetlistFmUrl.php`.
- Create: `backend/tests/Fixtures/setlistfm/{artist-tours,past-performance,upcoming-search,upcoming-performance,empty,challenge}.html`.
- Test: create `backend/tests/Feature/SetlistFmHtmlTest.php`.

**Interfaces:**
- `SetlistFmUrl::resolve(string $href, string $base): string` resolves relative links and accepts only HTTPS `www.setlist.fm` resource paths without credentials/fragments/unrecognized query keys.
- `::id(string $url, string $kind): string` extracts website Artist/Tour/Venue/performance IDs; for upcoming performance URLs requires `setlist`, never substitutes the show slug ID.
- `SetlistFmHtml::tours(string $html, string $artistId): array` returns normalized `{id,name,url}` rows.
- `::search(string $html, string $pageUrl): array` returns `{items: array<PerformanceSummary-without-locator>, nextUrl: ?string}` from main result cards only.
- `::performance(string $html, string $url, string $expectedId, array $artist): array` returns normalized preview data without proofs/local matches. `$artist` is the already verified Artist descriptor containing its MBID and website ID; verify the main performance's Artist link against that website ID. Do not obtain an MBID from advertising/sidebar scripts.

- [ ] **Step 1: Add minimal fixtures and failing identity/empty-state tests.**

The Tour fixture must include generated IDs that can change and aggregate options that cannot become entities:

```html
<!doctype html><html><head><title>Search for setlists</title></head><body>
<form action="search"><select name="artist"><option value="4bd7afb6" selected>Maná</option></select>
<select name="tour" id="generated-new-value">
<option value="">All Tours</option><option value="73d4a2f1">No Tour (154)</option>
<option value="2bde703a">Drama y luz (109)</option>
<option value="bdac5de">Vivir sin aire (53)</option><option disabled>Other (7)</option>
</select></form></body></html>
```

Create performance/search fixtures by retaining the actual primary structural containers and selected Artist/Venue/Tour/date links from the previously checked pages. Keep the source DOM structure while clearly synthetic test values can be substituted consistently in API/HTML pairs. Include a distracting sidebar with different IDs, two same-name Artists, and an upcoming URL with show ID `2b893c8a` and performance ID `b4cc196`. Strip scripts, images, advertisements and songs. The empty fixture must include the site's expected search form and primary `.alert.alert-info` paragraph containing “did not match any setlists.” Include unrelated Popular Setlists after that marker and assert none becomes a result. This exact empty state was checked with curl against the user's filtered Ken-Y URL during planning (HTTP 200). The challenge fixture has a normal HTML title/body but no search/result structure. Do not teach the parser invented markup that the real pages never use.

```php
public function test_real_tour_ids_and_aggregate_exclusion(): void
{
    $html = file_get_contents(base_path('tests/Fixtures/setlistfm/artist-tours.html'));
    $rows = app(\App\Http\SetlistFmHtml::class)->tours($html, '4bd7afb6');
    $this->assertSame(['2bde703a', 'bdac5de'], array_column($rows, 'id'));
    $this->assertSame('Drama y luz', $rows[0]['name']);
}
public function test_upcoming_identity_is_not_the_show_id(): void
{
    $url = 'https://www.setlist.fm/show/2026-10-30/allstate-arena-rosemont-il-2b893c8a.html?setlist=b4cc196';
    $this->assertSame('b4cc196', app(\App\Http\SetlistFmUrl::class)->id($url, 'setlist'));
}
```

Add tests asserting `challenge.html` throws CatalogProviderUnavailable, `empty.html` returns an empty page, changed generated element IDs still parse, a missing performance parameter rejects, conflicting main Artist identities reject, relative URLs resolve safely, and city coordinates never populate Venue coordinates.

- [ ] **Step 2: Run `php artisan test --compact --filter=SetlistFmHtml`; expect missing parser classes.**

- [x] **Step 3: Implement pure DOM parsing and fixed-host URL rules.**

```php
$document = new \DOMDocument;
$previous = libxml_use_internal_errors(true);
try {
    if (! $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        throw new CatalogProviderUnavailable;
    }
    $xpath = new \DOMXPath($document);
    $options = $xpath->query('//select[@name="tour"]/option');
    if ($options === false || $options->length === 0) {
        throw new CatalogProviderUnavailable;
    }
} finally {
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
}
```

Use page-specific private readers for historical `.setlistInfo`/`.setlistHeadline`, search `.setlistPreview`, and the upcoming primary concert container. Scope links before extracting values; never search the entire document for the first Tour/Artist/date. Normalize text to safe decoded Unicode; remove only the terminal result count from dropdown labels. Validate IDs, dates, host, paths and expected identity using CatalogProviderData/SetlistFmUrl. `nextUrl` is parsed only from the main pagination control and is bounded again by the caller. Require an explicit supported empty marker to return no results.

- [x] **Step 4: Run parser tests green and read the fixture diff for accidental scripts/content.**

```bash
php artisan test --compact --filter=SetlistFmHtml
rg -n 'script|cookie|apiKey|Authorization' tests/Fixtures/setlistfm
```

Expected: tests pass; the fixture search has no matches. Do not make live requests in these tests.

- [ ] **Step 5: Commit the pure parser and sanitized fixtures when authorized.**

```bash
git add backend/app/Http/SetlistFmHtml.php backend/app/Http/SetlistFmUrl.php backend/tests/Fixtures/setlistfm backend/tests/Feature/SetlistFmHtmlTest.php
git commit -m "feat: parse setlist.fm tours and upcoming performances"
```

### Task 4: Expose bounded API plus HTML discovery

**Files:**
- Create: `backend/app/Http/SetlistFmClient.php`, `SetlistFmDiscovery.php`, `ProviderReadGate.php`.
- Create: `backend/app/Http/Controllers/CatalogProviderController.php`, `backend/app/Http/Requests/CatalogProviderRequest.php`.
- Modify: `backend/config/services.php`, `backend/.env.example`, `backend/app/Providers/AppServiceProvider.php`, `backend/routes/api.php`.
- Test: create `backend/tests/Feature/SetlistFmDiscoveryTest.php` and `ProviderReadGateTest.php`.

**Interfaces:**
- `ProviderReadGate::run(string $channel, string $cacheKey, callable $fetch): array` returns cached normalized source data or performs a paced/quota-counted read; `channel` is `setlist_api`, `setlist_html`, `wikidata` or `commons`. It throws CatalogProviderUnavailable with 429/503 semantics.
- `SetlistFmClient::artist(string $mbid): array`, `::search(array $query): array`, `::performance(string $id): array`, `::venue(string $id): array`, `::html(string $path, array $query = []): string` perform fixed-host, bounded HTTP only. `::artistByWebsiteId(string $id, string $name): array` verifies an API Artist-search result by its URL's exact website ID, never by name equality; it inspects one bounded API page and fails if no unique exact-ID match exists. It does not claim the Artist is absent from the provider.
- `SetlistFmDiscovery::tours(array $scope, string $q, ?string $cursor): array`, `::performances(array $filters): array`, `::preview(string $id, string $locator): array` return endpoint DTOs using Tasks 2–3.
- A scope contains exactly one selected `artistUuid` or `artistMbid`. Resolve a local UUID through ArtistProviderId. The UI selects one active Artist from the lineup for each external request; adding Tours for multiple Artists remains possible through separate searches.

Endpoints under existing Sanctum middleware and new `throttle:catalog-providers`:

```text
GET /api/v1/providers/setlistfm/tours
  q (0–200 chars), exactly one artistUuid/artistMbid, optional cursor
GET /api/v1/providers/setlistfm/performances
  period=past|upcoming, optional q, artistUuid|artistMbid, venueId,
  tourId+tourName together, date=YYYY-MM-DD, year, countryCode, cursor
GET /api/v1/providers/setlistfm/performances/{id}
  locator (required encrypted locator proof from search)
GET /api/v1/providers/setlistfm/venues/{id}
```

Performance search requires an identified Artist or Venue; it is not an unbounded global catalog crawl. Supplying both Artist and Venue narrows the search. Use ISO date only at the app boundary; convert to API `dd-MM-yyyy`. Unknown query/body keys and caller-supplied upstream URLs are rejected. Tour filtering needs both a verified ID and display name: use the name upstream and verify each offered performance's HTML association against the ID.

- [ ] **Step 1: Add deterministic API/HTML routing, pagination and local-match tests.**

```php
public function test_tours_are_automatic_and_reads_create_nothing(): void
{
    $this->actingAs(\App\Models\User::factory()->create());
    config(['services.setlistfm.enabled' => true,
        'services.setlistfm.api_key' => 'offline-test-key',
        'services.setlistfm.user_agent' => 'SpectaclesOfflineTests/1.0',
        'services.setlistfm.api_daily_limit' => 100,
        'services.setlistfm.html_daily_limit' => 100]);
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
    \Illuminate\Support\Facades\Http::fake([
        'https://api.setlist.fm/rest/1.0/artist/*' => \Illuminate\Support\Facades\Http::response([
            'mbid' => $f['mbid'], 'name' => 'Maná',
            'url' => 'https://www.setlist.fm/setlists/mana-4bd7afb6.html']),
        'https://www.setlist.fm/search*' => \Illuminate\Support\Facades\Http::response(
            file_get_contents(base_path('tests/Fixtures/setlistfm/artist-tours.html')),
            200, ['Content-Type' => 'text/html']),
    ]);
    $r = $this->getJson('/api/v1/providers/setlistfm/tours?artistMbid='.$f['mbid'].'&q=Drama')->assertOk();
    $r->assertJsonPath('data.0.ref.externalId', '2bde703a');
    $r->assertJsonPath('data.0.values.name', 'Drama y luz');
    $this->assertDatabaseCount('tours', 0);
    $this->assertDatabaseCount('events', 0);
}
```

Add named tests `test_cursor_keeps_both_halves_of_twenty_result_api_page`, `test_tour_rejection_advances_cursor_without_false_exhaustion`, `test_upcoming_uses_html_and_the_locator_binds_performance_id`, `test_local_matches_are_reattached_after_cache_hit`, `test_missing_key_does_not_trigger_html_history_fallback`, and `test_inbound_auth_and_query_allowlists`. Build the 20 API records by copying the synthetic fixture and assigning IDs `perf01` through `perf20`; record the actual returned cursor each time and assert all 20 unique IDs arrive once across requests. Inject deterministic responses per cursor/offset, without live calls.

- [ ] **Step 2: Run new discovery/gate tests red.**

```bash
php artisan test --compact --filter='SetlistFmDiscovery|ProviderReadGate'
```

Expected: missing endpoints/classes. No user data or provider accounts are needed.

- [x] **Step 3: Add opt-in configuration, transport and quota/pacing guards.**

```php
'setlistfm' => [
    'enabled' => env('SETLISTFM_ENABLED', false),
    'api_key' => env('SETLISTFM_API_KEY', ''),
    'user_agent' => env('SETLISTFM_USER_AGENT', ''),
    'api_interval_ms' => (int) env('SETLISTFM_API_INTERVAL_MS', 1000),
    'html_interval_ms' => (int) env('SETLISTFM_HTML_INTERVAL_MS', 2000),
    'api_daily_limit' => (int) env('SETLISTFM_API_DAILY_LIMIT', 0),
    'html_daily_limit' => (int) env('SETLISTFM_HTML_DAILY_LIMIT', 0),
    'cache_seconds' => (int) env('SETLISTFM_CACHE_SECONDS', 0),
],
```

Zero quota means unconfigured/unavailable, not unlimited. Intervals are conservative app defaults, not assertions about account entitlement. Require a configured identifying User-Agent; never put key values in fixtures/docs. Use `https://api.setlist.fm/rest/1.0/` with `Accept: application/json` and `x-api-key` for API calls. Website requests go only to `https://www.setlist.fm/` with English page formatting for predictable date parsing. Reject redirects, use a 2-second connect/5-second total timeout, and cap response bodies at 2 MiB using Content-Length plus the HTTP client's bounded response stream; do not read an arbitrarily large body before checking its size.

Under a shared cache lock, check the channel's next-start timestamp and UTC-day quota, reserve one request, release the lock, then perform HTTP. Lock failure/rate or quota exhaustion throws 429 with Retry-After. Normalize successful public data before caching; do not cache errors. The configured cache lifetime must satisfy the eventual provider agreement; zero disables caching. Cache keys include operation/filters/page/locale; API keys and user UUIDs are excluded. Use a shared production cache store and isolated test stores. Add inbound limit `Limit::perMinute(30)->by($request->user()->external_id)`.

- [x] **Step 4: Implement normalized orchestration, bounded pagination and preview issuance.**

```php
// An encrypted cursor is operation/filter-bound and never an upstream URL.
$cursor = Crypt::encryptString(json_encode([
    'operation' => $operation,
    'scope' => hash('sha256', json_encode($canonicalFilters, JSON_THROW_ON_ERROR)),
    'page' => $upstreamPage, 'offset' => $unconsumedOffset,
    'scanned' => $cumulativeCandidatesExamined,
    'expiresAt' => now()->addMinutes(15)->timestamp,
], JSON_THROW_ON_ERROR));
```

Validate cursor kind/scope/expiry; cap upstream page to 10, offset to the actual upstream page size and total candidate scanning to 100 per browsing operation. Each discovery request displays at most ten candidates and performs at most two cold upstream reads, with at most one read per channel. If additional candidates need a cold Tour-ID HTML check, return the exact next unconsumed offset and `partial=true` before attempting a second website read; continue only on explicit Load more. Advance past rejected candidates but never skip unexamined entries. If the app cap is reached while upstream reports more, return `partial=true` even if `next_cursor=null`, so the UI can explain narrowing filters. Do not describe such a page as a complete catalog.

Resolve website Artist IDs from API Artist URLs or saved explicit references. Historical API searches can use the supplied MBID directly and obtain website Artist URLs from their own response; do not spend a separate API Artist request before that search. Normalize API city metadata without using its coordinates as Venue coordinates. HTML resolves Tour IDs and upcoming results. Keep the primary upcoming Artist descriptor verified against its API Artist URL; when Venue-scoped search first discovers a website Artist ID, `artistByWebsiteId()` must match that exact ID. If required identity resolution is unavailable, retain the selected summary and offer retry/manual entry rather than guessing. In search pagination, stop at an unconsumed candidate before exhausting a channel budget.

SetlistFmClient is transport-only. Discovery wraps transport plus normalization inside ProviderReadGate so its cached result is an array of normalized fields, never a raw HTML string:

```php
$result = $gate->run('setlist_html', $cacheKey, fn (): array => [
    'rows' => $parser->tours($client->html('/search', ['artist' => $artistId]), $artistId),
]);
```

Treat a search-only upstream 404 as empty only when its bounded JSON body is the provider's structured not-found error for that fixed search endpoint; test it explicitly. A missing detail, non-JSON 404, malformed envelope or wrong resource is unavailable, not a fabricated empty page. Issue a fresh locator for every actual summary returned.

Search summaries issue locator proofs containing validated detail URL/period/performance ID. Preview verifies the locator and path ID before using its stored safe path. Historical detail comes from API plus targeted HTML Tour enrichment; upcoming detail comes from HTML plus the verified Artist descriptor. Issue child proofs and the bound performance-group proof only after normalization. Attach local UUIDs/full local Event through provider-ID queries after cache reads; no source fetch may create or update a catalog row.

Add separate API and HTML failure tests, malformed sibling/envelope tests, no-retry tests, a cached-success test proving freshly created local matches appear, expired locator tests and request-budget assertions. Never pass `lastUpdated` into time/date facts.

- [x] **Step 5: Run focused tests green and inspect all newly registered routes.**

```bash
php artisan test --compact --filter='SetlistFmDiscovery|ProviderReadGate|SetlistFmHtml|CatalogProviderSelection'
php artisan route:list --path=api/v1/providers --except-vendor
```

- [ ] **Step 6: Commit the bounded read service when authorized.**

```bash
git add backend/app/Http/SetlistFmClient.php backend/app/Http/SetlistFmDiscovery.php backend/app/Http/ProviderReadGate.php backend/app/Http/Controllers/CatalogProviderController.php backend/app/Http/Requests/CatalogProviderRequest.php backend/config/services.php backend/.env.example backend/app/Providers/AppServiceProvider.php backend/routes/api.php backend/tests/Feature/SetlistFmDiscoveryTest.php backend/tests/Feature/ProviderReadGateTest.php
git commit -m "feat: expose hybrid setlist.fm catalog discovery"
```

### Task 5: Add MusicBrainz Places and optional attributed Venue images

**Files:**
- Create: `backend/app/Http/MusicBrainzPlaces.php`, `VenueImageClient.php`.
- Modify: `MusicBrainzClient.php` (shared paced request primitive), `CatalogProviderController.php`, `CatalogProviderRequest.php`, `backend/routes/api.php`, `backend/config/services.php`, `backend/.env.example`.
- Test: create `backend/tests/Feature/VenueProviderLookupTest.php`.

**Interfaces:**
- `MusicBrainzClient::publicRead(string $cacheKey, string $path, array $parameters, callable $normalize): array` exposes the existing private paced/cached-response primitive to the focused Place client; no second MusicBrainz gate.
- `MusicBrainzPlaces::search(string $q): array` and `::detail(string $mbid): array` return normalized VenueSuggestion DTOs; detail includes aliases/address/Place coordinates and explicit URL relationships.
- `VenueImageClient::find(array $relations): ?array` returns normalized VenueImage or null, with fixed-host metadata requests only.
- `CatalogProviderController::confirmVenue(CatalogProviderRequest $request): JsonResponse` accepts `{selections: [proofA, proofB]}` on `POST /api/v1/providers/venues/confirm`; verifies two Venue proofs and issues one combined Venue proof. It creates no database row. Automatic pairing requires an explicit matching link; otherwise the UI labels this as a user-confirmed association.
- Add `GET /api/v1/musicbrainz/places?q=...` and `GET /api/v1/musicbrainz/places/{mbid}` under current auth/throttle. Search emits reviewable candidates; detail emits the confirmed full normalized proof.
- Add `GET /api/v1/providers/venues/{uuid}` returning `{data: LocalVenuePreview}` from saved Venue facts/provider references. A manual Venue has an empty refs list; do not invent a provider identity to enable history.

- [ ] **Step 1: Add failing Place and image tests using fixed public metadata fixtures.**

```php
public function test_place_coordinates_and_explicit_setlist_link_are_preserved(): void
{
    $this->actingAs(\App\Models\User::factory()->create());
    config(['services.musicbrainz.enabled' => true,
        'services.musicbrainz.user_agent' => 'SpectaclesOfflineTests/1.0']);
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    \Illuminate\Support\Facades\Http::fake([
        'https://musicbrainz.org/ws/2/place/*' => \Illuminate\Support\Facades\Http::response([
            'id' => '50573105-4880-4a66-b668-0356374918f0',
            'name' => 'Movistar Arena', 'address' => 'Humboldt 450, C1414 CABA',
            'coordinates' => ['latitude' => '-34.594335', 'longitude' => '-58.448113'],
            'aliases' => [['name' => 'Arena Ñ']],
            'relations' => [['type' => 'setlistfm', 'target-type' => 'url',
                'url' => ['resource' => 'https://www.setlist.fm/venue/wd-33d3d86d.html']]],
        ]),
    ]);
    $r = $this->getJson('/api/v1/musicbrainz/places/50573105-4880-4a66-b668-0356374918f0')->assertOk();
    $r->assertJsonPath('data.0.values.lat', -34.594335)
      ->assertJsonPath('data.0.linkedRefs.0.externalId', '33d3d86d')
      ->assertJsonPath('data.0.values.aliases.0', 'Arena Ñ');
    $this->assertDatabaseCount('venues', 0);
}
```

Add tests for mismatched provider links, manual confirmation of two independently valid Venue proofs, safe full-address retention without address splitting, missing coordinates/aliases, Place/Artist proof confusion, no image, missing license/credit, malicious credit HTML, untrusted URLs, failed image fetch and the shared MusicBrainz pacing gate across an Artist then Place request.

- [ ] **Step 2: Run `php artisan test --compact --filter=VenueProviderLookup`; expect missing routes/classes.**

- [x] **Step 3: Implement Place search/detail and bounded image metadata discovery.**

```php
return $client->publicRead('musicbrainz:place:'.$mbid, 'place/'.$mbid,
    ['inc' => 'aliases+url-rels', 'fmt' => 'json'],
    fn (array $body): array => $this->normalizePlace($body, $mbid));
```

Define private `normalizePlace(array $body, string $mbid): array` in MusicBrainzPlaces. Search `/ws/2/place` uses the existing literal-escaped prefix query strategy and limit ten. Validate ID/name/coordinates, preserve full address, normalize optional aliases and country/area context, and save unknown timezones as null. Existing saved Venue facts take precedence when attaching a local match.

Resolve image discovery through explicit `image` or `wikidata` relationships. For Wikidata allow only validated `Q[1-9][0-9]*` items at `www.wikidata.org/wiki/Special:EntityData/{id}.json`; take one preferred/current P18 image, then request Commons `action=query&prop=imageinfo&iiprop=url|extmetadata&iiurlwidth=800&titles=File:...&format=json`. Preserve license/credit/source metadata; strip HTML from credit rather than rendering it. Direct image relationships need usable rights metadata from a supported Commons file page; otherwise return null. Do not infer rights from an image extension or a URL alone.

Allow metadata hosts `www.wikidata.org` and `commons.wikimedia.org`, image hosts `upload.wikimedia.org` and `thumb.wikimedia.org`, and source/license URLs validated against Commons/Creative Commons. The checked example returned its thumbnail on `thumb.wikimedia.org`, so accepting only the original-file host would discard a usable image. Do not fetch arbitrary direct image URLs, redirects, private hosts or image bytes. Configure independent metadata pacing/quota/cache bounds through ProviderReadGate; image failure returns null and does not fail an otherwise valid Place. Use existing no-store response headers and fresh proofs after cache retrieval.

Add `services.venue_images` keys `enabled` (default false), `wikidata_interval_ms` and `commons_interval_ms` (default 1000 each), `wikidata_daily_limit` and `commons_daily_limit` (default 0 each), and `cache_seconds` (default 0), with matching `VENUE_IMAGES_*` environment names. Separate host channels allow one Wikidata then one Commons request without violating their pacing; the optional lookup has a two-request budget and never retries. Tests set explicit synthetic quotas and fake both hosts. No issued entitlement is assumed by these defaults.

For two independently selected Venue proofs, confirmation keeps the reviewed Place values and unions their valid refs; it does not override a ref already linked to a different local Venue. Such a local conflict returns 409 for explicit resolution, never automatic reassignment. Bound confirmation to two proofs and issue a normal Venue proof for final save/recovery.

- [x] **Step 4: Run Place and existing Artist/Work tests green.**

```bash
php artisan test --compact --filter='VenueProviderLookup|MusicBrainzLookup|ProviderReadGate|CatalogProviderSelection'
```

- [ ] **Step 5: Commit Venue enrichment when authorized.**

```bash
git add backend/app/Http/MusicBrainzPlaces.php backend/app/Http/VenueImageClient.php backend/app/Http/MusicBrainzClient.php backend/app/Http/Controllers/CatalogProviderController.php backend/app/Http/Requests/CatalogProviderRequest.php backend/routes/api.php backend/config/services.php backend/.env.example backend/tests/Feature/VenueProviderLookupTest.php
git commit -m "feat: enrich venue suggestions with MusicBrainz and image credits"
```

### Task 6: Resolve provider-assisted drafts atomically without changing local facts

**Files:**
- Create: `backend/app/Http/VenueDraftResolver.php`, `EventDraftResolver.php`.
- Modify: `backend/app/Http/CatalogDraftResolver.php`, `Requests/PendingCatalogRules.php`, `Requests/SaveConcertRequest.php`, `Controllers/CollectionController.php`.
- Test: create `backend/tests/Feature/CatalogProviderSaveTest.php`.
- Reuse: Artist/Tour/Venue provider models, EventProviderReference, existing savepoint/unique-constraint handling and authorization.

**Interfaces:**
- Preserve `CatalogDraftResolver::resolve(array $concert, array $pending): array`; extend only Tour proof dispatch/tuple resolution. Artist/Work contracts remain compatible.
- `VenueDraftResolver::resolve(array $concert, ?array $venue, ?array $binding): ?Venue` resolves current local/manual/proof-assisted Venue inputs.
- `EventDraftResolver::resolve(array $concert, ?Venue $venue, ?array $binding): Event` owns the Event/lineup/Tour creation block currently in CollectionController, including existing Event reuse and new performance references. Both resolvers run inside CollectionController's existing transaction after authorization.
- Singleton bindings contain exactly `selection`; allowed proof size is 32,768. CatalogProviderSelection supplies trusted tuples and group data.

- [ ] **Step 1: Add a complete provider request helper and failing save/reuse/conflict tests.**

```php
private function requestBody(): array
{
    $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
    $proofs = app(\App\Http\CatalogProviderSelection::class);
    $artist = (string) \Illuminate\Support\Str::uuid();
    $tour = (string) \Illuminate\Support\Str::uuid();
    $venue = (string) \Illuminate\Support\Str::uuid();
    return [
        'concert' => ['id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Maná', 'artists' => ['Maná'], 'venueId' => $venue,
            'date' => $f['performance']['date'], 'time' => '', 'timezone' => null,
            'interested' => true, 'ticketsBought' => false, 'notes' => 'Keep my note',
            'catalog' => ['artistIds' => [null],
                'tours' => [['name' => 'Drama y luz', 'catalogId' => null]]],
            'setlist' => []],
        'venue' => ['id' => $venue, 'name' => 'Allstate Arena', 'city' => 'Rosemont',
            'neighborhood' => '', 'lat' => null, 'lng' => null,
            'address' => null, 'aliases' => [], 'image' => null],
        'pendingCatalog' => ['artists' => [$artist => ['name' => 'Maná', 'type' => 'group',
            'disambiguation' => null, 'selection' => app(\App\Http\MusicBrainzSelection::class)
                ->issue('artist', $f['mbid'])]], 'songs' => [], 'lineup' => ['0' => $artist],
            'entries' => [], 'tours' => [$tour => ['name' => 'Drama y luz',
                'selection' => $proofs->issue('tour', $f['tour'])]],
            'tourChoices' => ['0' => $tour],
            'venue' => ['selection' => $proofs->issue('venue', $f['venue'])],
            'event' => ['selection' => $proofs->issue('performance', $f['performance'])]],
    ];
}
public function test_same_performance_reuses_event_without_upstream_calls(): void
{
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    $first = $this->actingAs(\App\Models\User::factory()->create())
        ->postJson('/api/v1/attendances', $this->requestBody())->assertOk()->json('data.concerts.0');
    $second = $this->actingAs(\App\Models\User::factory()->create())
        ->postJson('/api/v1/attendances', $this->requestBody())->assertOk()->json('data.concerts.0');
    $this->assertSame($first['catalog']['eventId'], $second['catalog']['eventId']);
    $this->assertDatabaseCount('events', 1);
    $this->assertDatabaseCount('attendances', 2);
    $this->assertDatabaseHas('tour_provider_ids', ['provider' => 'setlist_fm',
        'resource_type' => 'tour', 'external_provider_id' => '2bde703a']);
    \Illuminate\Support\Facades\Http::assertNothingSent();
}
```

Use RefreshDatabase in the class. Add `test_late_local_event_conflict_rolls_back_new_entities`: save once as another account, change the saved Event title/date, then submit a previously prepared body as a different account and assert 409, original facts retained, no new Attendance or orphan Artist/Tour/Venue. Add separate tests for altered date/Artist/Venue claiming the proof (422), Event/local-ID plus pending binding (422), expired/wrong-kind proof (422), unused maps (422), two Artists sharing Venue/date but distinct performance IDs (two Events), standalone Venue enrichment, same-name different-ID Tours, same-account duplicate Attendance (409), and a failed setlist row rolling back all preceding catalog writes. Existing legacy Artist/Work/Series/manual tests must pass unchanged except the intentional Tour column rename.

- [ ] **Step 2: Run new save tests red.**

```bash
php artisan test --compact --filter='CatalogProviderSave|MusicBrainzSave|CollectionEndpoints'
```

- [x] **Step 3: Extend strict request rules and resolve only signed identities.**

```php
// Extend the current allowed key lists; do not replace their existing validation.
$rules['pendingCatalog.event'] = ['sometimes', 'array:selection'];
$rules['pendingCatalog.event.selection'] = ['required_with:pendingCatalog.event', 'string', 'max:32768'];
$rules['pendingCatalog.venue'] = ['sometimes', 'array:selection'];
$rules['pendingCatalog.venue.selection'] = ['required_with:pendingCatalog.venue', 'string', 'max:32768'];
$rules['venue.address'] = ['sometimes', 'nullable', 'string', 'max:2000'];
$rules['venue.aliases'] = ['sometimes', 'array', 'max:100'];
$rules['venue.aliases.*'] = ['required', 'string', 'max:255', 'distinct'];
```

Extend allowed `venue` keys with address/aliases/image/region/countryCode and validate the exact image object/URL hosts defined in Tasks 1/5. Add date/group checks after ordinary rules succeed. Read proofs before creating identities; compare the submitted source-linked Artist ref, Tour ref/absence, Venue ref (including an explicitly linked setlist.fm ref) and date against the signed performance group. Manual title and independently entered time/timezone remain validated form values; do not invent them from source edit timestamps. Changing structural identities uses explicit detachment rather than accepting mismatched proofs.

Tour resolution translates legacy Series or new Tour proofs into the actual provider/resource/ID tuple; use `external_provider_id` only on TourProviderId. Sort identity jobs by full provider/resource/ID tuple to maintain stable lock order. Venue resolution uses all signed linked refs, reuses one existing local Venue without editing it, and rejects refs pointing to different local Venues. Create a new Venue and its refs within a savepoint, rolling back a losing parent and reusing the winner only for the relevant unique tuple. Do not swallow unrelated integrity exceptions.

- [x] **Step 4: Move the Event creation block to EventDraftResolver and attach performance identity under the unique constraint.**

```php
// Inside CollectionController's current DB::transaction, after authorization:
$data = app(CatalogDraftResolver::class)->resolve($data, $pending);
$venue = app(VenueDraftResolver::class)->resolve($data, $request->validated('venue'),
    $pending['venue'] ?? null);
$data['venueId'] = $venue?->external_id;
$event = app(EventDraftResolver::class)->resolve($data, $venue,
    $pending['event'] ?? null);
// Continue the current Attendance/member/seat/setlist persistence block.
```

Reject new shared-field pending records when `concert.catalog.eventId` is selected. For a source-assisted new Event, compare any newly found existing Event's entire reviewed group (title, lineup IDs, Tour IDs, Venue ID, date/time/timezone) with the submitted normalized group. Return 409 if reuse changes it; otherwise reuse the Event without updates. Create Event, lineup/Tour links and EventProviderReference in a savepoint. On the specific performance unique collision, roll back the losing graph and perform the same comparison against the winning Event. Attach an explicit website Artist ID only when it comes from the signed, verified Artist URL; do not match by its display name.

Keep request/member authorization, duplicate membership checks, seat preservation, archived-Attendance behavior, ordered/repeated setlist entries and returned collection ownership in CollectionController. Saved collection DTOs contain durable catalog UUIDs and Venue metadata, never selection proofs, draft refs or source response caches.

- [x] **Step 5: Run focused tests green and inspect the diff for duplicate Event-creation paths.**

```bash
php artisan test --compact --filter='CatalogProviderSave|MusicBrainzSave|CollectionEndpoints|AttendanceRecords|ProviderReferences'
rg -n 'Event::query\(\)->create|event_provider_references' app/Http
```

Expected: ordinary creation now has one owning EventDraftResolver path, source saves are atomic, old manual/local paths pass and no upstream requests occur during save.

- [ ] **Step 6: Commit the transactional save extension when authorized.**

```bash
git add backend/app/Http/VenueDraftResolver.php backend/app/Http/EventDraftResolver.php backend/app/Http/CatalogDraftResolver.php backend/app/Http/Requests/PendingCatalogRules.php backend/app/Http/Requests/SaveConcertRequest.php backend/app/Http/Controllers/CollectionController.php backend/tests/Feature/CatalogProviderSaveTest.php
git commit -m "feat: save reviewed provider-assisted concerts atomically"
```

### Task 7: Decode and transport normalized provider suggestions in the frontend

**Files:**
- Create: `frontend/src/catalogProviders.ts`, `frontend/src/catalogProvidersApi.ts`, `frontend/tests/provider-fixtures.mjs`, `frontend/tests/catalog-providers-api.test.mjs`.
- Modify: `frontend/src/collectionApi.ts` only if exposing structured provider validation/conflict details; retain its no-replay behavior.

**Interfaces:**
- Export every type in Contract decisions from `catalogProviders.ts`, importing `CatalogEvent` from `catalog.ts`. Export `decodeProviderPage<T>(value: unknown, decode: (value: unknown) => T): ProviderPage<T>`, `decodeTour`, `decodeVenue`, `decodePerformanceSummary`, `decodePerformancePreview`.
- `createCatalogProvidersClient(baseUrl: string, fetcher: typeof fetch = fetch)` follows the existing client signature and returns `tours(scope:ArtistScope,q:string,signal?:AbortSignal,cursor?:string)`, `performances(filters:PerformanceFilters,signal?:AbortSignal)`, `preview(summary:PerformanceSummary,signal?:AbortSignal)`, `places(q:string,signal?:AbortSignal)`, `place(id:string,signal?:AbortSignal)`, `venue(id:string,signal?:AbortSignal)`, `catalogVenue(uuid:string,signal?:AbortSignal)`, `confirmVenue(selections:[string,string],signal?:AbortSignal)`.
- Reads return typed pages; `preview`, `place`, `venue`, `confirmVenue` return a single decoded entity/preview. `catalogVenue` returns decoded LocalVenuePreview. `CatalogProvidersError` exposes `status`, `message`, nullable `retryAfter`; 401 invokes the same account recovery path when wired later.

- [ ] **Step 1: Add complete synthetic frontend fixtures and failing decoder/transport tests.**

`provider-fixtures.mjs` exports the following constants; fresh test inputs use `structuredClone` so tests do not mutate each other:

```js
export const owner = '00000000-0000-4000-8000-000000000001'
export const mbid = '55441123-89a2-49ab-ae3f-df7a3e86835a'
export const tour = {ref:{provider:'setlist_fm',resourceType:'tour',externalId:'2bde703a'},
  values:{name:'Drama y luz'},url:'https://www.setlist.fm/search?artist=4bd7afb6&tour=2bde703a',
  selection:'public-tour-proof',localId:null}
export const venue = {ref:{provider:'setlist_fm',resourceType:'venue',externalId:'73d6ca11'},
  linkedRefs:[],values:{name:'Allstate Arena',city:'Rosemont',region:'IL',countryCode:'US',
  address:null,aliases:[],lat:null,lng:null,timezone:null,image:null},
  url:'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html',
  selection:'public-venue-proof',localId:null}
export const artist = {ref:{provider:'musicbrainz',resourceType:'artist',externalId:mbid},
  values:{name:'Maná',type:'group',disambiguation:null},
  url:'https://www.setlist.fm/setlists/mana-4bd7afb6.html',selection:'public-artist-proof',localId:null}
export const performance = {ref:{provider:'setlist_fm',resourceType:'setlist',externalId:'b4cc196'},
  artistName:'Maná',venueName:'Allstate Arena',city:'Rosemont',date:{year:2026,month:10,day:30},
  url:'https://www.setlist.fm/show/2026-10-30/allstate-arena-rosemont-il-2b893c8a.html?setlist=b4cc196',
  locator:'public-locator-proof',localEventId:null,artist,tour,venue,time:null,timezone:null,
  selection:'public-performance-proof',localEvent:null}
```

```js
import assert from 'node:assert/strict'
import {test} from 'node:test'
import {decodePerformancePreview, decodeTour} from '../src/catalogProviders.ts'
import {performance,tour} from './provider-fixtures.mjs'
test('opaque source IDs remain distinct from UUIDs and the show slug', () => {
  assert.equal(decodePerformancePreview(performance).ref.externalId,'b4cc196')
  assert.equal(decodeTour({...tour,ref:{...tour.ref,externalId:'bdac5de'}}).ref.externalId,'bdac5de')
  assert.throws(() => decodePerformancePreview({...performance,localEventId:'b4cc196'}))
})
```

Add tests for unknown kind/provider pairs, malformed localEvent shape/ID mismatch, invalid dates, mismatched child identity, unsafe source/image URLs, one missing coordinate, duplicate page IDs, oversized proofs/meta, request encoding/cookies/credentials, abort/timeout, 401/429/503, cursor pass-through and confirmVenue's CSRF-bearing POST. Confirm no POST is automatically retried.

- [ ] **Step 2: Run `node --test tests/catalog-providers-api.test.mjs`; expect missing modules.**

- [x] **Step 3: Implement exact decoders and same-origin backend transport.**

```ts
export function decodeProviderPage<T>(value: unknown, decode: (row: unknown) => T): ProviderPage<T> {
  if (!isRecord(value) || !Array.isArray(value.data) || value.data.length > 10 ||
      !isRecord(value.meta) || typeof value.meta.partial !== 'boolean' ||
      !(value.meta.next_cursor === null || boundedText(value.meta.next_cursor, 8192))) {
    throw new Error('Invalid provider response')
  }
  return {items:value.data.map(decode),nextCursor:value.meta.next_cursor as string|null,
    partial:value.meta.partial}
}
```

Define and export `isRecord(value: unknown): value is Record<string,unknown>` and `boundedText(value:unknown,max:number): value is string` in the same module. `boundedText` requires nonempty string and Unicode length within max. Define domain-aware decoders for every nested type, including complete CatalogEvent parsing for local matches; do not cast unknown response objects into the declared types. Source IDs accept bounded safe opaque text; MBIDs/local UUIDs use `catalogUuid`. Reject duplicate provider/resource/ID tuples in a page.

Use URLSearchParams for filters and encodeURIComponent for path IDs. Always call the application's backend, never setlist.fm/MusicBrainz directly from React. Copy current credential/header/timeout/session handling from existing clients without introducing shared global auth state. Handle JSON and non-JSON failures safely; retain Retry-After. ConfirmVenue uses existing CSRF acquisition semantics but performs no catalog save. Keep fetch/result state outside local recovery snapshots.

- [x] **Step 4: Run transport/decoder and existing collection tests green.**

```bash
node --test tests/catalog-providers-api.test.mjs tests/musicbrainz-api.test.mjs tests/collection-api.test.mjs
npm run build
```

- [ ] **Step 5: Commit the frontend boundary when authorized.**

```bash
git add frontend/src/catalogProviders.ts frontend/src/catalogProvidersApi.ts frontend/tests/provider-fixtures.mjs frontend/tests/catalog-providers-api.test.mjs
git commit -m "feat: decode hybrid catalog provider suggestions"
```

### Task 8: Apply and detach performance groups through pure wizard transitions

**Files:**
- Create: `frontend/src/providerDraft.ts`, `frontend/tests/provider-draft.test.mjs`.
- Modify: `frontend/src/concertWizard.ts`, `frontend/src/concertDraft.ts`, existing `frontend/tests/concert-wizard.test.mjs`.

**Interfaces:**
- Add optional `ref?: ProviderRef` to PendingTour, retaining optional legacy `mbid`. Add optional `providerEvent?: {key:string; ref:ProviderRef; selection:string}` and `providerVenue?: {key:string; ref:ProviderRef; selection:string}` to WizardDraft.
- Add optional singleton bindings `venue`/`event` to PendingCatalog as specified above.
- `applyPerformance(state:WizardState, preview:PerformancePreview, venues:Venue[]): WizardState` applies the full group, or delegates to `adoptWizardEvent` when `preview.localEvent` exists.
- `detachPerformance(state:WizardState): WizardState` clears providerEvent/local Event identity while preserving field values; local Event detachment still uses existing explicit decisions.
- `applyProviderVenue(state:WizardState, venue:VenueSuggestion): WizardState` prepares/reuses the chosen Venue; structural change requires the caller's explicit detach decision first.
- `performanceChangeNeedsDetach(state:WizardState, patch:{artist?:boolean;tour?:boolean;venue?:boolean;date?:boolean}): boolean` supports pre-change confirmation.

- [ ] **Step 1: Pin complete application, stable identity and preserved personal/setlist state.**

```js
import assert from 'node:assert/strict'
import {test} from 'node:test'
import {createWizard,finishWizard} from '../src/concertWizard.ts'
import {applyPerformance,detachPerformance} from '../src/providerDraft.ts'
import {performance} from './provider-fixtures.mjs'
test('one apply replaces the full group but keeps personal fields and setlist', () => {
  const before=createWizard(), id=before.draft.concert.id
  before.draft.concert.notes='My note'
  before.draft.concert.ticketsBought=true
  before.draft.concert.time='21:00';before.draft.timezoneText='America/Argentina/Buenos_Aires'
  before.draft.concert.setlist=[{id:'00000000-0000-4000-8000-000000000021',
    type:'note',title:'My section',artist:'',tape:false}]
  const after=applyPerformance(before,performance,[])
  assert.equal(after.draft.concert.id,id)
  assert.deepEqual(after.draft.artists.map(a=>a.name),['Maná'])
  assert.deepEqual(after.draft.tours.map(t=>t.name),['Drama y luz'])
  assert.equal(after.draft.dateText,'2026-10-30')
  assert.equal(after.draft.concert.time,'')
  assert.equal(after.draft.timezoneText,'')
  assert.equal(after.draft.concert.notes,'My note')
  assert.deepEqual(after.draft.concert.setlist,before.draft.concert.setlist)
  const submitted=finishWizard(after)
  assert.equal(submitted.pendingCatalog.event.selection,'public-performance-proof')
  assert.equal(detachPerformance(after).draft.providerEvent,undefined)
})
```

Add tests for a full localEvent preview adopting saved facts, cancelled preview doing nothing (no transition call), null Tour clearing old Tours, selected Venue/local UUID reuse, same names with different source IDs, name changes clearing only the affected identity, explicit structural detach, preservation of a performer draft referenced by a kept setlist row, removal of unused lineup/Tour drafts, and source refs/proofs absent from durable Concert payloads.

- [ ] **Step 2: Run `node --test tests/provider-draft.test.mjs tests/concert-wizard.test.mjs`; expect absent transitions/payload bindings.**

- [x] **Step 3: Implement pure state transitions and extend final composition.**

```ts
export function detachPerformance(state:WizardState):WizardState {
  const {providerEvent:removed,eventId:local,...draft}=state.draft
  void removed;void local
  return {...state,draft}
}
// At the end of finishWizard, after existing live-reference registration/pruning:
if (state.draft.providerVenue) pending.venue={selection:state.draft.providerVenue.selection}
if (state.draft.providerEvent) pending.event={selection:state.draft.providerEvent.selection}
```

Create stable draft keys only when a new source selection is explicitly applied; do not regenerate them during render or final serialization. Build the one Artist choice from its MBID proof/local UUID, one Tour choice or none, and one staged Venue from normalized values. Set exact date/dateText/precision, derive title from Artist, and clear unavailable old time/timezone. Keep old pending Artist/Song drafts only where preserved setlist rows still reference them. Deduplicate choices by provider tuple rather than name, preserving the legacy Series MBID path for recovered drafts.

Update final `pendingCatalog` inclusion to count the new singleton bindings as well as maps. Strip providerEvent/providerVenue, draft refs and proofs from durable Concert/Venue DTOs; send only bindings through pendingCatalog. A newly selected full Venue's normalized public metadata belongs in the submitted Venue values; selection proof and source refs do not. When adopting a local Event, remove all pending Event/lineup/Tour/Venue bindings; preserve pending setlist-only identities. Extend `finishConcertDraft` to preserve staged metadata without guessing unknown coordinates.

- [x] **Step 4: Run focused wizard/draft/domain tests green.**

```bash
node --test tests/provider-draft.test.mjs tests/concert-wizard.test.mjs tests/catalog-draft.test.mjs tests/domain.test.mjs
```

- [ ] **Step 5: Commit the pure draft behavior when authorized.**

```bash
git add frontend/src/providerDraft.ts frontend/src/concertWizard.ts frontend/src/concertDraft.ts frontend/tests/provider-draft.test.mjs frontend/tests/concert-wizard.test.mjs
git commit -m "feat: apply reviewed performance groups to concert drafts"
```

### Task 9: Upgrade local creation recovery before wiring the new UI

**Files:**
- Create: `frontend/src/concertRecoverySchema.ts`.
- Modify: `frontend/src/concertRecovery.ts`, `frontend/src/components/ConcertRecovery.tsx` only where needed for new field types.
- Test: extend `frontend/tests/concert-recovery.test.mjs` and `concert-recovery-ui.test.mjs`.

**Interfaces:**
- `concertRecoverySchema.ts` exports `decodeStoredSnapshot(raw:string, owner:string): RecoverySnapshot|null`; its version-1 validator preserves the exact existing shape, while its version-2 validator accepts the new bounded fields. Import types with `import type` to avoid a runtime circular dependency.
- RecoverySnapshot becomes `{version:2;owner:string;state:WizardState;fields:Partial<RecoveryFields>}`. Keep `decodeRecovery(raw,owner)` as a compatible exported wrapper.
- `recoveryKey(owner)` remains exactly `spectacles.concert-creation.v1:${owner}`. The payload version changes; the key does not.
- RecoveryFields adds `performanceFilters:Omit<PerformanceFilters,'cursor'>`, `performancePreview:PerformanceSummary|null`, `performanceDecision:'apply'|'detach'|null`, `venuePreviewRef:ProviderRef|null`, `venueHistoryTab:'past'|'upcoming'`, `activeProviderArtist:ArtistScope|null`, and nullable `venueEditor` described below. TourEditor accepts optional `ref` alongside legacy optional `mbid`.
- `venueEditor` is `{name:string;city:string;address:string;aliasesText:string;lat:string;lng:string;selection:string|null;ref?:ProviderRef}`. Bound name/city to 255, address/aliasesText to 2,000, coordinate text to 100 and proof to 32,768. These are raw unfinished editor strings, not already valid Venue DTOs.

- [ ] **Step 1: Add real version-1 migration and version-2 round-trip tests.**

Use existing A/B/store helpers in `concert-recovery.test.mjs` and import applyPerformance/performance. Preserve all current tests.

```js
test('valid v1 draft migrates in memory without changing its key or raw storage on read', () => {
  const storage=store(), before=openRecovery(A,undefined,storage)
  const old={version:1,owner:A,state:before.snapshot.state,fields:{tourEditor:{
    input:{name:'Legacy tour ',selection:'legacy-public-proof'},
    mbid:'00000000-0000-4000-8000-000000000009',context:'Saved context'}}}
  const raw=JSON.stringify(old)
  storage.setItem(recoveryKey(A),raw)
  const restored=openRecovery(A,undefined,storage)
  assert.equal(restored.snapshot.version,2)
  assert.equal(restored.snapshot.fields.tourEditor.input.name,'Legacy tour ')
  assert.equal(storage.getItem(recoveryKey(A)),raw)
  restored.changeField('eventQuery','Next edit')
  assert.equal(JSON.parse(storage.getItem(recoveryKey(A))).version,2)
  assert.equal(storage.map.size,1)
})
test('new source group and unfinished venue fields survive reload without becoming applied actions', () => {
  const storage=store(), first=openRecovery(A,undefined,storage)
  first.changeState(applyPerformance(first.snapshot.state,performance,[]))
  first.changeField('venueEditor',{name:'Arena Ñ ',city:'',address:'Humboldt ',
    aliasesText:'Alias one\nAlias two ',lat:'-',lng:'.',selection:'public-venue-proof',
    ref:performance.venue.ref})
  first.changeField('performancePreview',performanceSummary(performance))
  const restored=openRecovery(A,undefined,storage)
  assert.equal(restored.snapshot.state.draft.providerEvent.ref.externalId,'b4cc196')
  assert.equal(restored.snapshot.fields.venueEditor.lat,'-')
  assert.equal(restored.snapshot.fields.venueEditor.aliasesText,'Alias one\nAlias two ')
  assert.equal(restored.snapshot.fields.performancePreview.ref.externalId,'b4cc196')
})
```

Import performanceSummary from providerDraft.ts in this test. Its explicit field selection prevents full preview/local Event responses from entering recovery. Add a test inspecting stored JSON to assert no `localEvent`, fetched `items`, HTML, token/cookie/CSRF keys or callbacks survive. Passing unknown raw keys to the strict storage decoder still rejects the snapshot; do not silently strip unknown storage keys.

Add tests for every new field and pending proof, unknown version 3, malformed version 1/2, foreign owner, oversized storage, quota/read/removal failure, and source proof expiry retaining visible fields. Retain the test where clear() is followed by late `changeField`/`changeState` calls and assert no storage resurrection. Test A → B → A with a held provider response and a pending venue editor.

- [ ] **Step 2: Run recovery tests red.**

```bash
node --test tests/concert-recovery.test.mjs tests/concert-recovery-ui.test.mjs
```

- [x] **Step 3: Extract strict schemas and implement lossless read migration.**

```ts
// In decodeStoredSnapshot, after parsing and enforcing the existing 2,000,000-char limit:
if (value.version===1 && validV1(value) && value.owner===owner) {
  return {version:2,owner,state:value.state,fields:value.fields}
}
if (value.version===2 && validV2(value) && value.owner===owner) return value
return null
```

Define private `validV1(value:unknown): value is LegacySnapshot` and `validV2(value:unknown): value is RecoverySnapshot` in the schema file, using the current strict object/array/string primitives. LegacySnapshot is the frozen pre-feature state/editor schema; do not alias it to the newly extended WizardState validator. Validate UUIDs, opaque refs, enum values, normalized selected summaries, optional metadata and proof bounds; permit blank/unfinished raw editor values without requiring final submission validity.

Keep openRecovery's corrupt/unknown snapshot block, owner capture, synchronous write path and closed guard. Initialize new drafts at version 2. A successful valid-v1 read only migrates the in-memory shape; first subsequent user edit writes the version-2 snapshot to the same key. Do not fetch providers, run a callback, regenerate stable draft UUIDs or apply a pending preview while restoring.

Add `performanceSummary(preview:PerformanceSummary):PerformanceSummary` in `providerDraft.ts`: explicitly pick the declared summary fields, excluding localEvent and child full DTOs. Call it before storing preview state. A restored preview offers Resume review/Dismiss; Resume re-fetches by locator, and an expired locator offers reselection without clearing typed fields. Normalized confirmed values already applied to the draft remain available without a network request.

- [x] **Step 4: Run recovery and draft tests green; preserve the existing discard/save lifecycle.**

```bash
node --test tests/concert-recovery.test.mjs tests/concert-recovery-ui.test.mjs tests/provider-draft.test.mjs tests/concert-wizard.test.mjs
```

- [ ] **Step 5: Commit only the reviewed recovery extension when authorized.**

```bash
git diff -- frontend/src/concertRecovery.ts frontend/src/components/ConcertRecovery.tsx
git add frontend/src/concertRecoverySchema.ts frontend/src/concertRecovery.ts frontend/src/components/ConcertRecovery.tsx frontend/src/providerDraft.ts frontend/tests/concert-recovery.test.mjs frontend/tests/concert-recovery-ui.test.mjs
git commit -m "feat: recover hybrid provider-assisted concert drafts"
```

The current recovery files are pre-existing uncommitted work. If their prerequisite has not yet been recorded through the user's delivery workflow, review that boundary before staging whole files; preserve its authorship/scope rather than presenting the entire existing implementation as newly written here.

### Task 10: Wire explicit external search, automatic Tours and performance preview

**Files:**
- Create: `frontend/src/components/PerformancePicker.tsx`, `frontend/tests/provider-ui.test.mjs`.
- Modify: `frontend/src/autocomplete.ts`, `components/EntityAutocomplete.tsx`, `WizardArtistStep.tsx`, `WizardTourStep.tsx`, `WizardEventStep.tsx`, `ConcertWizard.tsx`, `wizardTypes.ts`, `frontend/src/App.tsx`, `frontend/src/wizard.css`.
- Test: extend `frontend/tests/autocomplete.test.mjs` and `concert-wizard-ui.test.mjs`.

**Interfaces:**
- Export `CatalogProvidersAccess = {client:ReturnType<typeof createCatalogProvidersClient>; enabled:boolean; sessionKey:string; onUnauthorized:()=>void}` from catalogProvidersApi.ts. Add `providers?:CatalogProvidersAccess` to WizardStepProps and ConcertWizard props; optional supports unchanged tests/manual-only embedding.
- `PerformancePicker({providers, filters, onApply, onEditingChange})` takes `filters:PerformanceFilters`, `onApply:(preview:PerformancePreview)=>void`, `onEditingChange:(value:boolean)=>void`. It owns temporary results/detail requests and recovers only filters, selected summary and decision markers through useRecoveryField.
- EntityOption adds `{source:'provider';candidate:TourSuggestion|VenueSuggestion}` while preserving existing catalog/draft/MusicBrainz Artist/Work/create branches. Tour kind no longer selects MusicBrainz Series.
- Extend `searchEntity` with optional `externalRequested?:boolean`; a successful local result may trigger a provider read when this flag is true. Local and external cancellation/session identity remain coupled to the current input but their error states remain independent.

- [ ] **Step 1: Add reducer/SSR tests for source access and recovered preview state.**

```js
test('explicit external search works even when a local match exists', async () => {
  let calls=0
  await searchEntity({query:'Maná',signal:new AbortController().signal,
    providerEnabled:true,externalRequested:true,
    local:async()=>({total:1,items:[]}),publishLocal:()=>{},
    provider:async()=>{calls++;return []},publishProvider:()=>{}})
  assert.equal(calls,1)
})
```

In provider-ui.test.mjs use the existing Vite SSR setup from concert-recovery-ui.test.mjs to load PerformancePicker, WizardTourStep and ConcertWizard. Render provider access backed by functions that throw if invoked during SSR/restoration. Assert source labels, an explicit Search setlist.fm action with local matches, a preview replacement explanation, Apply/Cancel actions, an Artist-scope selector for external Tours, and Resume review/Dismiss for a recovered summary. Assert only one outer `<form>` and no automatic open result list or network request. Test manual local Tour search works without an external Artist identity.

- [ ] **Step 2: Run the new source/UI tests red.**

```bash
node --test tests/autocomplete.test.mjs tests/provider-ui.test.mjs tests/concert-wizard-ui.test.mjs
```

- [x] **Step 3: Add independent external state, explicit source actions and authenticated client wiring.**

```tsx
<button type="button" disabled={!providers?.enabled || composing}
  onClick={() => setExternalRequested(true)}>Search setlist.fm</button>
```

Keep local choices first and manual creation always available. Preserve zero-local automatic fallback where already supported; the new explicit action enables external reads with local matches. Local errors do not silently trigger provider search. A manually requested provider search may still run independently of a failed local read; show both statuses. Artist/Work continue through their existing client, Tour suggestions through providers.tours, Venue suggestions through providers.places.

Instantiate createCatalogProvidersClient with the same backend base URL as existing clients in App. Capture the authenticated owner/sessionKey for each read; cancel and discard responses when owner, scope, query, period, selected performance or component lifetime changes. Propagate providers through ConcertWizard/wizardTypes to every relevant step. Empty/short queries do not launch broad global reads; external Tour requests require one identified active Artist, while local/manual Tour lookup remains available without one.

PerformancePicker uses createLatestRead for searches and a separate latest read for preview. Store a sanitized summary when opening preview; detail remains temporary until explicit Apply. The preview shows local Event authority or the complete replacement group, source attribution, missing fields and Apply/Cancel. On Apply call applyPerformance once; on Cancel leave WizardState untouched. Inform the parent about an unconfirmed preview/decision through onEditingChange so navigation/final Save cannot silently discard it.

For edits to a selected local Event or a source-assisted performance group, show the existing explicit detach choice before changing Artist/Tour/Venue/date. Declining preserves all fields/identities. Confirming calls the pure detach transition, then the proposed field change. Name edits on a pending Tour clear its source ref/proof; legacy recovered Series choices remain usable but are not newly searched. Preserve setlist-only pending Artists when replacing the lineup.

- [x] **Step 4: Add held-response and pending-decision tests and run the focused suite green.**

```js
test('a cancelled read cannot publish a different account result', async () => {
  let release
  const pending=new Promise(resolve=>{release=resolve})
  const abort=new AbortController(),seen=[]
  const run=searchEntity({query:'Maná',signal:abort.signal,providerEnabled:true,
    externalRequested:true,local:async()=>({total:0}),publishLocal:()=>{},
    provider:async()=>pending,publishProvider:r=>seen.push(r)})
  await Promise.resolve();abort.abort();release(['old-account'])
  await run;assert.deepEqual(seen,[])
})
```

```bash
node --test tests/autocomplete.test.mjs tests/provider-ui.test.mjs tests/concert-wizard-ui.test.mjs tests/concert-recovery-ui.test.mjs
npm run lint
npm run build
```

SSR/pure tests establish markup and state contracts, not click/focus/browser network behavior; Task 12 owns that proof.

- [ ] **Step 5: Commit the integrated search/preview UI when authorized.**

```bash
git add frontend/src/components/PerformancePicker.tsx frontend/src/autocomplete.ts frontend/src/components/EntityAutocomplete.tsx frontend/src/components/WizardArtistStep.tsx frontend/src/components/WizardTourStep.tsx frontend/src/components/WizardEventStep.tsx frontend/src/components/ConcertWizard.tsx frontend/src/components/wizardTypes.ts frontend/src/App.tsx frontend/src/catalogProvidersApi.ts frontend/src/wizard.css frontend/tests/provider-ui.test.mjs frontend/tests/autocomplete.test.mjs frontend/tests/concert-wizard-ui.test.mjs
git commit -m "feat: preview and apply hybrid concert suggestions"
```

### Task 11: Add Venue metadata review, image credits and independent history sections

**Files:**
- Create: `frontend/src/components/VenuePreview.tsx`, `VenueDraftFields.tsx`, `frontend/tests/venue-preview.test.mjs`.
- Modify: `frontend/src/components/WizardEventStep.tsx`, `frontend/src/components/VenueMap.tsx`, `frontend/src/wizard.css`.
- Reuse: PerformancePicker, CatalogProvidersAccess, useRecoveryField, applyProviderVenue and existing local Venue selection.

**Interfaces:**
- `VenuePreview({venue,providers,onApplyPerformance,onChooseVenue})` takes `venue:Venue|VenueSuggestion`, `onApplyPerformance:(value:PerformancePreview)=>void`, and `onChooseVenue:(value:VenueSuggestion)=>void`.
- `VenueDraftFields({onConfirm,onCancel})` takes `onConfirm:(value:VenueValues, selection:string|null)=>void` and `onCancel:()=>void`, retaining raw unfinished values through recovery's venueEditor.
- Existing local Venues load their saved refs via client.catalogVenue(localUUID). Source suggestions use their signed ref/linkedRefs. No-source local Venues can still show metadata/map; history explains the missing source connection without inventing one.

- [ ] **Step 1: Add failing SSR/state tests for metadata and source independence.**

Use the established Vite SSR component test setup and synthetic Venue fixture. Include an image descriptor with `credit:'BugWarp'`, `license:'CC BY-SA 4.0'`, Commons source and Creative Commons license URLs. Assert the full address, alias text, credit/license/source links and both history section labels are rendered. Verify null image renders normal metadata, a city-only Venue renders no guessed map pin, and an existing local Venue's readonly facts remain authoritative.

```js
test('venue history filters use the setlist Venue ID rather than Place MBID', () => {
  const refs=[{provider:'musicbrainz',resourceType:'place',externalId:mbid},venue.ref]
  assert.deepEqual(venueHistoryFilters(refs,'upcoming'),{
    period:'upcoming',venueId:'73d6ca11'})
  assert.equal(venueHistoryFilters(refs.slice(0,1),'past'),null)
})
```

Define/export `venueHistoryFilters(refs:ProviderRef[],period:'past'|'upcoming'):PerformanceFilters|null` from providerDraft.ts in this task. Reject conflicting multiple setlist Venue refs rather than taking the first. Add tests for malicious metadata HTML, missing credit/license, mismatched explicit links requiring confirmation and pending metadata text surviving dismiss/reopen.

- [ ] **Step 2: Run `node --test tests/venue-preview.test.mjs tests/provider-draft.test.mjs`; expect missing components/helper.**

- [x] **Step 3: Implement the Venue preview and recovered editor.**

```tsx
{values.image && <figure>
  <img src={values.image.thumbnailUrl} alt={values.name} loading="lazy" />
  <figcaption>{values.image.credit} · <a href={values.image.licenseUrl}>
    {values.image.license}</a> · <a href={values.image.sourceUrl}>Image source</a></figcaption>
</figure>}
```

Render normalized text through React escaping; never dangerouslySetInnerHTML. Let the thumbnail fail without hiding metadata or blocking Save. Keep the original full address as one value and aliases in an editable newline input while drafting; trim/deduplicate only on confirmation. Coordinates remain decimal text until valid paired confirmation, retaining unfinished signs/decimals in recovery. Optional metadata updates apply only to a newly drafted Venue; selecting an existing local Venue does not update it.

Use separate request/result/error state and explicit Load more for historical/upcoming history. A failure in one section cannot erase the other; opening/restoring the preview does not automatically reapply any performance. Upcoming search includes its explicit period and verified setlist Venue ID. History selection opens PerformancePicker's standard performance preview and applies the whole group only after confirmation.

Automatically join a Place's explicitly linked setlist Venue when the IDs agree. For an independently selected candidate without that link, show both source names/locations and a Confirm same Venue action invoking client.confirmVenue([proofA,proofB]); declining keeps the prior selection. A 409 local-ref conflict offers selection of the existing local Venue or manual creation, not reassignment. Preserve old fields while any enrichment request is pending/fails.

VenueMap may display a saved Venue's optional address/image attribution in its existing detail popup; retain its current map behavior and render only venue-level coordinates. Do not add a Venue browsing page or upload flow.

- [x] **Step 4: Run the focused Venue/recovery/UI tests green.**

```bash
node --test tests/venue-preview.test.mjs tests/provider-draft.test.mjs tests/concert-recovery.test.mjs tests/provider-ui.test.mjs
npm run build
```

- [ ] **Step 5: Commit the Venue experience when authorized.**

```bash
git add frontend/src/components/VenuePreview.tsx frontend/src/components/VenueDraftFields.tsx frontend/src/components/WizardEventStep.tsx frontend/src/components/VenueMap.tsx frontend/src/providerDraft.ts frontend/src/wizard.css frontend/tests/venue-preview.test.mjs
git commit -m "feat: preview venue metadata and concert history"
```

### Task 12: Prove concurrent identity reuse and authenticated browser recovery

**Files:**
- Create: `backend/tests/Feature/CatalogProviderConcurrencyTest.php`, `backend/tests/Feature/catalog-provider-race-worker.php`.
- Create: `backend/tests/Fixtures/catalog-provider-http.php` (test-only local HTTP router; no production registration).
- Create: `backend/tests/Fixtures/catalog-provider-http-responses.php` (complete synthetic upstream response map for that router).
- Create: `docs/verification/2026-10-25-setlistfm-catalog-form-assistance.md`.
- Modify: `backend/README.md`, `frontend/README.md` with source configuration, limitations and recovery behavior; preserve their existing dirty changes.

**Interfaces:**
- Race worker receives a private temp input directory and slot `a|b`, boots the current app on the dedicated PostgreSQL test database and reads `input.json = {concert,venueUuid,binding}`. It calls EventDraftResolver with prepared shared catalog identities and a signed performance binding, printing only the resulting Event UUID on success or `CONFLICT` for an expected 409. Other exceptions exit nonzero with bounded diagnostic text. It must not use a developer database.
- The test-only HTTP router boots the real Laravel HTTP kernel with fake upstream HTTP for the synthetic API/HTML/Place/image fixtures. Accounts, authentication/session, requests, transactions and collection responses use the actual local backend. Its guard requires `APP_ENV=testing` and the isolated QA SQLite path; it refuses to serve if either is wrong.
- Verification report records actual commands, pass/skip/fail counts, manual observations, screenshots, environment limits and any remaining acceptance failures.

- [x] **Step 1: Add a two-process Event-reference race test with a deterministic insertion barrier.**

Use the existing MusicBrainzConcurrencyTest/worker process pattern, requiring `DB::connection()->getDatabaseName()==='spectacles_testing'` and proc_open support. Precreate shared Artist, Tour and Venue plus their refs in committed setup, so workers cannot deadlock on child-identity creation before the Event-reference barrier. Generate a random bounded performance ID and update all fixture IDs/URLs/proofs consistently.

```php
// In the worker only, after app bootstrap and before calling EventDraftResolver:
\App\Models\EventProviderReference::creating(function ($ref) use ($directory, $slot): void {
    if ($ref->provider !== 'setlist_fm' || $ref->resource_type !== 'setlist') {
        return;
    }
    file_put_contents($directory.'/'.$slot.'.ready', 'ready');
    $other = $slot === 'a' ? 'b' : 'a';
    $deadline = microtime(true) + 5;
    while (! is_file($directory.'/'.$other.'.ready')) {
        if (microtime(true) > $deadline) {
            throw new \RuntimeException('Race insertion barrier timed out.');
        }
        usleep(10000);
    }
});
```

Both workers must reach the reference insert before either commits. Assert equal Event UUIDs, one performance reference, one Event graph and no orphan Event/lineup/Tour links. Add a second case where the committed winner's facts differ, expecting one conflict and one retained graph. Clean only the generated test rows and private temporary files in finally. Never use RefreshDatabase around this process test; workers need committed shared state.

- [ ] **Step 2: Run SQLite suites, then the dedicated PostgreSQL checks.**

```bash
# backend/; ordinary test configuration uses in-memory SQLite.
php artisan test --compact
# Only after verifying the existing isolated spectacles_testing connection:
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=spectacles_testing DB_URL= php artisan migrate --no-interaction
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=spectacles_testing DB_URL= php artisan test --compact --filter='CatalogProvider|SetlistFm|VenueProvider|ProviderReadGate|MusicBrainz|CollectionEndpoints|ProviderReferences'
```

Resolve connection host/user from current configuration without printing secrets. If the dedicated PostgreSQL connection is unavailable, record the exact limitation and retain the runnable process test; do not create roles/databases or guess credentials. A uniqueness assertion on SQLite does not replace the two-process proof.

- [x] **Step 3: Assemble an isolated local HTTP acceptance fixture using the real application.**

The router lives under tests/Fixtures and is never loaded by normal app startup. Its kernel path is:

```php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
if (! $app->environment('testing') ||
    ! str_contains((string) config('database.connections.sqlite.database'),
        '/qa-output/catalog-providers-')) {
    http_response_code(503);
    exit;
}
\Illuminate\Support\Facades\Http::preventStrayRequests();
$fixtures = require __DIR__.'/catalog-provider-http-responses.php';
\Illuminate\Support\Facades\Http::fake($fixtures);
$request = \Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
```

Create `backend/tests/Fixtures/catalog-provider-http-responses.php` with this response-map structure. Keep the synthetic historical API values consistent with the historical HTML fixture's main performance fields when assembling the fixture; its DOM structure comes from Task 3 and its values are deliberately synthetic, not coverage evidence.

```php
<?php
use Illuminate\Support\Facades\Http;
$f = require __DIR__.'/catalog-provider-inputs.php';
$artist = ['mbid' => $f['mbid'], 'name' => 'Maná', 'sortName' => 'Maná',
    'disambiguation' => '', 'url' => $f['artist']['url']];
$venue = ['id' => '73d6ca11', 'name' => 'Allstate Arena', 'url' => $f['venue']['url'],
    'city' => ['id' => '4908052', 'name' => 'Rosemont', 'state' => 'Illinois',
        'stateCode' => 'IL', 'country' => ['code' => 'US', 'name' => 'United States']]];
$past = ['id' => '6bc56e8a', 'versionId' => 'version-a', 'artist' => $artist,
    'venue' => $venue, 'tour' => ['name' => 'Drama y luz'], 'eventDate' => '14-02-2014',
    'lastUpdated' => '2026-10-25T00:00:00.000+0000', 'sets' => ['set' => []],
    'url' => 'https://www.setlist.fm/setlist/mana/2014/parque-david-ben-gurion-pachuca-mexico-6bc56e8a.html'];
$place = ['id' => '50573105-4880-4a66-b668-0356374918f0', 'name' => 'Allstate Arena',
    'address' => 'Fixture street 1', 'coordinates' => ['latitude' => '42.005', 'longitude' => '-87.887'],
    'aliases' => [['name' => 'Arena Ñ']], 'relations' => [
        ['type' => 'setlistfm', 'target-type' => 'url', 'url' => ['resource' => $f['venue']['url']]],
        ['type' => 'wikidata', 'target-type' => 'url',
            'url' => ['resource' => 'https://www.wikidata.org/wiki/Q62005881']]]];
$file = 'Movistar_Arena_junio_de_2025_-_BugWarp_04.jpg';
$image = ['url' => 'https://upload.wikimedia.org/wikipedia/commons/e/e2/'.$file,
    'thumburl' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/e2/'.$file.'/960px-'.$file,
    'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:'.$file,
    'extmetadata' => ['Artist' => ['value' => 'BugWarp'],
        'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
        'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0/']]];
return [
    'https://api.setlist.fm/rest/1.0/artist/*' => Http::response($artist),
    'https://api.setlist.fm/rest/1.0/search/artists*' => Http::response([
        'artist' => [$artist], 'total' => 1, 'page' => 1, 'itemsPerPage' => 30]),
    'https://api.setlist.fm/rest/1.0/search/setlists*' => Http::response([
        'setlist' => [$past], 'total' => 1, 'page' => 1, 'itemsPerPage' => 20]),
    'https://api.setlist.fm/rest/1.0/setlist/*' => Http::response($past),
    'https://api.setlist.fm/rest/1.0/venue/*' => Http::response($venue),
    'https://www.setlist.fm/search*' => function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $name = ($query['upcoming'] ?? '') === 'true' ? 'upcoming-search' : 'artist-tours';
        return Http::response(file_get_contents(__DIR__.'/setlistfm/'.$name.'.html'),
            200, ['Content-Type' => 'text/html']);
    },
    'https://www.setlist.fm/setlist/*' => Http::response(
        file_get_contents(__DIR__.'/setlistfm/past-performance.html'), 200, ['Content-Type' => 'text/html']),
    'https://www.setlist.fm/show/*' => Http::response(
        file_get_contents(__DIR__.'/setlistfm/upcoming-performance.html'), 200, ['Content-Type' => 'text/html']),
    'https://musicbrainz.org/ws/2/place/*' => Http::response($place),
    'https://musicbrainz.org/ws/2/place?*' => Http::response(['places' => [$place]]),
    'https://musicbrainz.org/ws/2/artist?*' => Http::response(['artists' => [[
        'id' => $f['mbid'], 'name' => 'Maná', 'type' => 'Group', 'disambiguation' => '',
        'country' => 'MX', 'area' => ['name' => 'Mexico']]]]),
    'https://musicbrainz.org/ws/2/artist/*' => Http::response(['id' => $f['mbid'],
        'name' => 'Maná', 'relations' => [['type-id' => 'f03d59f3-97db-424b-885e-e8be69d65225',
            'target-type' => 'series', 'series' => ['id' => '00000000-0000-4000-8000-000000000077',
                'name' => 'Legacy tour', 'type' => 'Tour']]]]),
    'https://musicbrainz.org/ws/2/work?*' => Http::response(['works' => []]),
    'https://www.wikidata.org/wiki/Special:EntityData/*' => Http::response([
        'entities' => ['Q62005881' => ['claims' => ['P18' => [[
            'rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => str_replace('_', ' ', $file)]]]]]]]]),
    'https://commons.wikimedia.org/w/api.php*' => Http::response([
        'query' => ['pages' => ['1' => ['imageinfo' => [$image]]]]]),
];
```

The thumbnail is a licensed public image used as fixture display content; its use does not assert the synthetic Venue's real appearance. If the live thumbnail is unavailable, record that available-image browser check separately from its passing normalized-credit/rendering tests and test the graceful missing-image path. The backend's metadata/API requests remain faked and unmatched outbound requests blocked.

Add an ignored local fixture-state JSON file under the same task-owned QA directory. Read it only after the router guard; its `upstream503`, `failSave`, `dropSaveResponse` booleans and `advanceHours` integer control deterministic faults:

```php
$state = json_decode((string) @file_get_contents(dirname(config('database.connections.sqlite.database')).'/fixture-state.json'), true) ?: [];
if (isset($state['advanceHours'])) {
    \Illuminate\Support\Carbon::setTestNow(now()->addHours((int) $state['advanceHours']));
}
if (($state['upstream503'] ?? false) === true) {
    Http::fake(fn () => Http::response(['message' => 'Fixture unavailable'], 503));
}
$isSave = $request->isMethod('POST') && $request->path() === 'api/v1/attendances';
if ($isSave && ($state['failSave'] ?? false) === true) {
    http_response_code(503);
    echo '{"message":"Fixture save failure"}';
    exit;
}
// Run kernel->handle($request), then optionally replace its response after a successful commit.
if ($isSave && ($state['dropSaveResponse'] ?? false) === true && $response->isSuccessful()) {
    http_response_code(503);
    echo '{"message":"Fixture could not confirm response"}';
    $kernel->terminate($request, $response);
    exit;
}
```

Insert the before-save blocks before kernel->handle, and the after-save block before response->send in the router above. No fault branch belongs in a product controller. Set fixture-only provider/image quotas and identifying headers explicitly; never use an issued real API key in this harness.

Use a fresh task-owned ignored `qa-output/catalog-providers-<timestamp>/` directory and SQLite file, not the app database or another QA directory. Prepare it with current runtime-prefixed Artisan migrations and a copied nonsecret test configuration; keep any APP_KEY/session credentials out of tracked files/output. Run the router with PHP's local server and the existing frontend dev server on free loopback ports, using the frontend's actual API-base environment variable discovered in App.tsx. Configure stateful origin/CORS/session storage for those local ports. Keep exact launch commands and teardown steps in the verification report; close only sessions started for this fixture.

- [ ] **Step 4: Exercise and record the browser acceptance matrix on the actual wizard.**

Use the available browser/native UI tools; do not claim SSR constitutes browser proof. Register/sign in a disposable local account on the isolated backend and save via the real Attendance endpoint. Use a second isolated account for ownership checks. Capture sanitized screenshots under the task-owned ignored QA directory.

| Case | Actions | Required observation |
| --- | --- | --- |
| Historical group | Artist → automatic Tour → historical performance preview → Apply → Save → collection reload | Correct Artist/Tour/Venue/date and real IDs; one Event; no imported songs. |
| Upcoming group | Preview the upcoming page containing both show/performance IDs, Apply and save | Event ref uses performance ID; missing time is unknown; no average setlist copied. |
| Local authority | Save as A, select same performance as B, inspect preview | Saved Event facts/relationships are reused and readonly; explicit detach creates a separate manual Event without the original performance ref. |
| Venue | Inspect full address/aliases/map/image credit; load past and future sections; fail one section | Coordinates are Venue-level; attribution is visible; other section and manual form remain usable. |
| Each-step recovery | Type partial Artist/Tour/Venue fields and a nested setlist editor; dismiss, reopen and reload at each step | Exact text/step/draft UUIDs/source IDs restored; no automatic selection/application/server mutation. |
| Unconfirmed preview | Open a provider preview, close/reload, then Resume or Dismiss | Summary is recovered; Resume explicitly fetches detail; Dismiss changes no form fields. |
| Account isolation | Hold a read, switch A → B → A | B sees none of A's draft; late response cannot publish; A's fields and selections return. |
| Expiry | Apply a source group, advance test clock 25 hours, save | Field-specific rejection preserves text/IDs; reselect or explicit detach enables a deliberate next save. |
| Failure/reconciliation | Fail save; dismiss/reload; simulate uncertain transport after a real save | Draft remains; collection reload resolves uncertainty before retry; no automatic duplicate mutation. |
| Clear lifecycle | Confirm successful save; start a new creation; separately Discard an unfinished draft | Creation key clears; fresh IDs are generated; late callbacks cannot restore cleared data. |
| Compatibility | Restore a real version-1 fixture with a legacy Series proof, then edit/save; test unknown version and storage failure | Existing data migrates without loss; unreadable raw data remains; editing/server save remain available. |
| Accessibility/layout | Keyboard combobox selection, Enter/Escape, IME composition; 390 px and 1440 px widths | No accidental submit, focus loss or clipped long labels/source links; pending decisions are explicit. |

For uncertain transport, allow the real local save to complete and interrupt only its response in the task-owned fixture; verify collection reconciliation before another POST. Do not fabricate success by changing browser collection state. Verify server database/ref counts after reload as well as visible form values.

- [x] **Step 5: Complete project gates once and review the full diff.**

```bash
# frontend/
npm test
npm run lint
npm run build
# backend/
vendor/bin/pint --dirty --format agent
php artisan test --compact
# repository root
git diff --check
git status --short
```

Pint is required after PHP edits; review any formatting touching pre-existing work and retain only task-relevant changes. Rerun focused tests if formatting or fixes change behavior. Run broader tests again only after new changes/failures justify it. Check no credentials, raw provider pages, song lists, private screenshots, source caches or unrelated recovery changes entered the intended diff.

- [x] **Step 6: Write the verification report, operator docs and acceptance handoff.**

Create the report with these concrete sections: Implemented behavior; Automated commands and results; PostgreSQL concurrency; Authenticated browser observations; Local recovery matrix; Provider configuration and permission dependency; Remaining failures or environment limits. Record unperformed items as unperformed. Document each SETLISTFM configuration variable from Task 4, image metadata configuration, source roles, manual fallback, short caching policy, API-key secrecy, draft storage key/version migration and clear behavior in the existing READMEs. Do not claim source completeness or activation permission.

- [ ] **Step 7: Commit verified acceptance artifacts when authorized.**

```bash
git add backend/tests/Feature/CatalogProviderConcurrencyTest.php backend/tests/Feature/catalog-provider-race-worker.php backend/tests/Fixtures/catalog-provider-http.php backend/tests/Fixtures/catalog-provider-http-responses.php docs/verification/2026-10-25-setlistfm-catalog-form-assistance.md backend/README.md frontend/README.md
git commit -m "test: verify hybrid catalog saving and local draft recovery"
```

## Spec coverage and plan self-review

| Spec requirement | Owning tasks |
| --- | --- |
| API plus HTML, automatic scoped Tour IDs and upcoming performances | 3–4, 10 |
| Artist/Work continuity, Place metadata, explicit cross-source Venue matching and optional image | 1–2, 5, 7, 11 |
| One Event per source performance; exact show/performance distinction | 2–4, 6, 8, 12 |
| Complete field-group application; personal/setlist preservation; manual/nonmusic behavior | 6, 8, 10, 12 |
| Local catalog authority; explicit detach; identity uniqueness and concurrency | 1–2, 6, 8, 12 |
| Fixed-host authenticated reads, bounds, quotas, pacing, no retry/crawl, safe errors | 2–5, 7, 10 |
| Transactional pending bindings, proof expiry, no save-time provider dependency | 2, 6–8, 12 |
| Version-1 migration and every new local creation-form state | 8–10, 12 |
| Browser keyboard/mobile/manual/failure/session/save/recovery acceptance | 10–12 |
| No synchronization, source song import, raw payload storage, production activation or unrelated rewrite | Global constraints, 3–6, 9, 12 |

Before delivery, check every Create/Modify path against task code, each producer/consumer signature against Contract decisions, all five Review Focus cases against named tests, and every spec section against this table. Search for unfinished instructions and fix them inline. Product tests and acceptance actions above are execution requirements; they were not run by writing this plan.

## Execution handoff

The approved spec and this plan travel together. No product decision remains open. Provider permission, issued API entitlements/quotas and the dedicated PostgreSQL connection remain external operational dependencies; offline implementation and deterministic checks can proceed without resolving production activation. Stop here when the requested deliverable is the plan. Implementation requires the user's selected execution method and execution-time worktree choice; do not automatically select another workflow or delegate work.
