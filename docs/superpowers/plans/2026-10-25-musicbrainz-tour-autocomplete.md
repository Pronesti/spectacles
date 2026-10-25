# MusicBrainz Tour Autocomplete Implementation Plan

> **Execution:** Follow the user-selected execution method and repository requirements. This plan does not require any additional skill. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add confirmed MusicBrainz Tour suggestions to the existing Tour step while retaining local search, manual creation, optional multiple Tours, and atomic final saving.

**Architecture:** Extend the current MusicBrainz read/selection path with the `series` resource restricted to type Tour. Carry selected new Tours through optional pending sidecar maps and resolve them against `tour_provider_ids` in the existing Attendance transaction. Reuse the combined autocomplete and inline Tour confirmation; preserve the durable collection shape and all Artist/Work behavior.

**Tech Stack:** PHP 8.5, Laravel 13, Sanctum 4, PHPUnit 12, React 19, TypeScript 6, Vite 8, Node 24, existing Laravel HTTP/cache/Crypt, SQLite and PostgreSQL. No new dependencies or migrations.

**Spec:** [MusicBrainz Tour Autocomplete Extension Design](../specs/2026-10-25-musicbrainz-tour-autocomplete-design.md), read with the [original wizard design](../specs/2026-10-25-concert-wizard-musicbrainz-design.md). The extension specification replaces only that design's external-Tour exclusion.

## Global Constraints

- Planning only: do not implement, run product tests, commit, push, open PRs, merge, or deploy while producing this plan.
- Reuse the current Laravel 13/Sanctum 4/PHP 8.5 and React 19/TypeScript 6/Vite 8/Node 24 stack; add no dependencies or schema migrations.
- Preserve current uncommitted wizard/provider work, unrelated changes, ignored environment values, and user data.
- Keep local Tour search and manual creation available independently of MusicBrainz results, configuration, limits, or failures.
- Accept only MusicBrainz Series whose returned type is Tour; store provider=musicbrainz, resource_type=series, and the Series MBID in tour_provider_ids.provider_resource_id.
- MusicBrainz MBIDs never replace Spectacles-owned UUID external_id values, and names never establish identity.
- Keep all selected suggestions in memory until final Attendance save; cancellation creates no Tour, provider reference, Event, or Attendance.
- Preserve zero/multiple Tours, distinct same-name Tours, explicit Event adoption/detachment, personal fields, and staged setlist data.
- Reuse an existing provider-linked Tour without updating its saved name or relationships; resolve concurrent creation through existing unique constraints and savepoints.
- Keep Artist/Work lookup, selection proofs, existing attendance clients, durable collection DTOs, session isolation, and manual Tour writes compatible.
- Share the existing MusicBrainz identifying User-Agent, successful-result cache, outbound pacing gate, inbound throttle, timeouts, and safe error handling.
- Apply selected-Artist filtering only to the local catalog; external suggestions search by Tour name and must not imply verified Artist membership.
- Do not import a Tour's Artists, Events, dates, locations, recordings, or setlists, add synchronization, or automatically change the Event lineup.
- Do not expose operator contact details, environment values, cookies, selection proofs, or private QA data in tracked artifacts.
- Use the active machine's runtime instructions before PHP/Composer commands and preserve SQLite/PostgreSQL compatibility and the single-feature-branch release workflow.

## Review Focus

1. A non-Tour Series shares a name with a Tour: query type filtering and returned-type validation must exclude it; wrong-kind proofs must fail before writes (Tasks 1–2).
2. Same-name candidates or a concurrently created local match: preserve distinct IDs, reuse a matching provider identity without renaming it, and leave no losing parent record (Tasks 1–2 and 4).
3. An Artist/Song-only client omits the new maps: its existing request must still save; a half-supplied Tour map or orphan pending Tour must fail atomically (Task 2).
4. Event adoption, removal, or editing the prefilled Tour name: prune abandoned drafts, retain explicit Event-detach behavior, and strip every proof/MBID/draft key from durable choices (Tasks 3–4).
5. Provider errors, stale responses, local Artist narrowing, or an unconfirmed editor: local/manual choices remain usable, filtering scope is clear, stale results cannot be applied, and no intermediate write occurs (Tasks 1, 3–4).

---

## Preflight, current evidence, and scope

Plan grounded in the working tree on `feat/live-event-data-layer` at HEAD `e440ef5` (`Add development-only login prefill`). The preceding wizard/provider implementation and its documents are still local changes. Treat these as required direct prerequisites, not disposable files. No product code or tests are changed/run by this planning task.

Before authorized implementation, recheck:

```bash
pwd
git status --short
git branch --show-current
git log -3 --oneline
cat ~/.config/agent-skills/runtime.md
```

Read applicable `AGENTS.md` and any `.ai/rules/index.md`. The root/backend rules directories were absent at planning time. PHP/Composer commands below run from `backend/`; this machine's runtime currently uses plain `php` and `composer`, but use the executing machine's profile. Frontend commands run from `frontend/`. Confirm installed package APIs before editing. Do not run destructive commands against a developer database.

Use one feature branch based on `main`, including only agreed same-release dependencies. The current branch already contains the direct wizard/catalog/account prerequisites; preserve them. Do not merge/rebase `dev`, discard untracked work, or create a worktree without the user's execution-time choice. Commits listed below are conditional on later commit authorization. No commit, push, PR, merge, or deployment is authorized by writing this plan.

Current behavior and reuse:

- `WizardTourStep.tsx`: local autocomplete/manual name editor; zero/multiple choices; local `artist_uuid` filter; explicit detachment from a selected Event.
- `EntityAutocomplete.tsx`: independent local/provider reads with debounce, cancellation, errors, and source groups; provider guard currently admits Artists/Songs only.
- `musicbrainz.ts`, `musicbrainzApi.ts`: provider kinds are `artist | work`; fixed first-party routes and strict DTO decoding.
- `MusicBrainzClient.php`: only Artist/Work resource envelopes/labels; one global cache lock/pacing gate and configured contact-derived User-Agent.
- `MusicBrainzController.php` and `MusicBrainzSelection.php`: current local-match/proof pattern; non-Artist branches currently assume Song and need explicit Series mapping.
- `concertWizard.ts`, `PendingCatalogRules.php`, `CatalogDraftResolver.php`: sidecars currently contain Artists/Songs/lineup/entries. `finishConcertDraft()` spreads Tour choice objects, requiring explicit stripping of newly introduced draft properties.
- `Tour`, `TourProviderId`, `ProviderReferencesTest`: existing string provider ID, separate UUID identity, and unique provider tuple. No new table/column is needed.
- `CollectionController.php` already links resolved local Tour UUIDs and deduplicates their Event relationships. `CollectionResource.php` emits `{name,catalogId}`; leave it unchanged.

Provider evidence:

- [Series search fields](https://musicbrainz.org/doc/MusicBrainz_API/Search#Series), checked 2026-10-25, support `series`, `type`, `sid`, aliases, comments, and tags. They do not document Artist-ID filtering for Series.
- [Series model](https://musicbrainz.org/doc/Series) defines Tour as a Series type; [Artist–Series relationships](https://musicbrainz.org/relationships/artist-series) are separate relationships, not an implied search filter or permission to populate the lineup.
- [2026-10-22 research](../../research/2026-10-22-provider-validation.md) found a Tour in one of four selected name searches. Use [Eras Tour search](../../research/evidence/2026-10-22-provider-validation/mb-t03.json) as the minimal positive fixture and a non-Tour Series as a negative fixture. Old coverage results do not prove this new prefix path's live behavior.

No material user decision remains. Implement name-based external suggestions; label the existing Artist checkbox as local-only. Do not add artist-relation hydration, Tour roster creation, Event discovery, or another provider framework.

## File map and shared interfaces

| Task | Files | Responsibility |
|---|---|---|
| 1 | Modify `backend/app/Http/MusicBrainzClient.php`, `Controllers/MusicBrainzController.php`, `MusicBrainzSelection.php`, `backend/routes/api.php`, `backend/tests/Feature/MusicBrainzLookupTest.php` | Tour-only Series read, current-local lookup, correct proof kind |
| 2 | Modify `backend/app/Http/Requests/PendingCatalogRules.php`, `backend/app/Http/CatalogDraftResolver.php`, `backend/tests/Feature/MusicBrainzSaveTest.php`, `MusicBrainzConcurrencyTest.php`, `musicbrainz-race-worker.php` | Optional Tour maps, reference validation, atomic reuse/race handling |
| 3 | Modify `frontend/src/musicbrainz.ts`, `musicbrainzApi.ts`, `concertWizard.ts`, `frontend/tests/musicbrainz-api.test.mjs`, `concert-wizard.test.mjs` | Series route/type and ephemeral Tour drafts/serialization |
| 4 | Modify `frontend/src/components/EntityAutocomplete.tsx`, `WizardTourStep.tsx`, `frontend/tests/concert-wizard-ui.test.mjs`, `README.md`, `frontend/README.md`, `docs/verification/2026-10-25-concert-wizard-musicbrainz.md` | Tour selection/review UI, local/manual fallback, interactive acceptance and evidence |

Keep `domain.ts`, durable `CatalogChoice`, `CollectionResource`, configuration/contact settings, and migrations unchanged. Add only the following contract extensions:

```ts
// musicbrainz.ts: existing candidate shape is retained.
export type MusicBrainzKind = 'artist' | 'work' | 'series'
// series is exposed only by GET /api/v1/musicbrainz/tours?q=...

// concertWizard.ts
export type TourDraftInput = { name: string; selection: string | null }
export type PendingTour = { key: string; input: TourDraftInput; mbid?: string }
export type WizardTourChoice = CatalogChoice & { draft?: PendingTour }
// Add these optional properties to existing PendingCatalog:
// tours?: Record<string, TourDraftInput>
// tourChoices?: Record<string, string>
```

Both maps are optional together. A Tour-only submission still includes the existing four empty sidecar maps because their established request rules require their presence when `pendingCatalog` exists. Each `tourChoices` position indexes `concert.catalog.tours`; its value is a pending Tour UUID key. Resolved Tours use provider namespace `musicbrainz / series / MBID`. No proof kind or stored resource type named `tour` is introduced.

### Task 1: Add Tour-only Series suggestion reads

**Files:** Modify `backend/app/Http/MusicBrainzClient.php`, `Controllers/MusicBrainzController.php`, `MusicBrainzSelection.php`, `backend/routes/api.php`, `backend/tests/Feature/MusicBrainzLookupTest.php`.

**Interfaces:**
- Consumes: current authenticated `MusicBrainzSearchRequest`, q length 2–200, configured HTTP/cache behavior and `TourProviderId`.
- Produces: `GET /api/v1/musicbrainz/tours?q=...`, normalized candidates with `kind='series'`, and existing `MusicBrainzSelection::read($proof, 'series', $path)` support. `search('series', $q)` returns only Tour Series.

- [ ] **Step 1: Add failing Series lookup/filter tests to the current lookup test class.**

Import `Tour` and `TourProviderId`. Its existing setup already enables fake provider config, isolates the array cache, and prevents stray HTTP. Use body-free GETs: Laravel `getJson()` may synthesize a body, which the real endpoint intentionally rejects.

```php
public function test_tour_lookup_filters_series_type_and_uses_the_real_resource_namespace(): void
{
    $this->withHeader('Accept', 'application/json')
        ->get('/api/v1/musicbrainz/tours?q=Eras')->assertUnauthorized();
    Http::fake(['musicbrainz.org/*' => Http::response(['series' => [
        ['id' => '23aa9d94-ffc6-4160-a7f3-31cfba1ea646', 'name' => 'The Eras Tour',
            'type' => 'Tour', 'disambiguation' => 'Taylor Swift tour'],
        ['id' => '00000000-0000-4000-8000-000000000002', 'name' => 'Eras Festival', 'type' => 'Festival'],
        ['id' => '00000000-0000-4000-8000-000000000003', 'name' => 'Eras unknown'],
        ['id' => 'bad-id', 'name' => 'Invalid', 'type' => 'Tour'],
    ]])]);
    $result = $this->actingAs(User::factory()->create())
        ->withHeader('Accept', 'application/json')->get('/api/v1/musicbrainz/tours?q=Eras')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'series')
        ->assertJsonPath('data.0.label', 'The Eras Tour')->assertJsonPath('data.0.local', null)
        ->assertJsonPath('data.0.artistType', null)->assertJsonPath('data.0.writers', []);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/ws/2/series')
        && $request['query'] === 'series:(Eras*) AND type:tour');
    $this->assertSame('23aa9d94-ffc6-4160-a7f3-31cfba1ea646',
        app(MusicBrainzSelection::class)->read($result->json('data.0.selection'), 'series', 'selection'));
    $this->assertDatabaseCount('tours', 0);
    $this->assertDatabaseCount('tour_provider_ids', 0);
}

public function test_cached_tour_results_attach_current_saved_identity_without_renaming_it(): void
{
    $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
    $mbid = '23aa9d94-ffc6-4160-a7f3-31cfba1ea646';
    Http::fake(['musicbrainz.org/*' => Http::response(['series' => [[
        'id' => $mbid, 'name' => 'The Eras Tour', 'type' => 'Tour',
    ]]])]);
    $this->get('/api/v1/musicbrainz/tours?q=Eras')->assertOk()->assertJsonPath('data.0.local', null);
    $tour = Tour::factory()->create(['name' => 'Saved local spelling']);
    TourProviderId::factory()->create(['tour_id' => $tour->id, 'provider' => 'musicbrainz',
        'resource_type' => 'series', 'provider_resource_id' => $mbid]);
    $this->get('/api/v1/musicbrainz/tours?q=Eras')->assertOk()
        ->assertJsonPath('data.0.local.id', $tour->external_id)
        ->assertJsonPath('data.0.local.label', 'Saved local spelling')
        ->assertJsonPath('data.0.local.disambiguation', null);
    Http::assertSentCount(1);
}
```

- [ ] **Step 2: Establish the missing route/resource behavior fails.**

```bash
php artisan test --compact tests/Feature/MusicBrainzLookupTest.php
```

Expected: the new Tour cases fail on missing route/resource support; existing Artist/Work assertions retain their meaning. Do not enable live HTTP to make a test pass.

- [ ] **Step 3: Extend only the fixed resource/query mapping and returned-type checks.**

In `MusicBrainzClient::cachedSearch()`, replace the two-kind assumptions with this explicit map; keep origin, cache key format, gate, timeouts, User-Agent, and retry/error behavior:

```php
$resources = [
    'artist' => ['collection' => 'artists', 'label' => 'name'],
    'work' => ['collection' => 'works', 'label' => 'title'],
    'series' => ['collection' => 'series', 'label' => 'name'],
];
if (! isset($resources[$kind])) {
    throw new \InvalidArgumentException('Unsupported kind.');
}
$plural = $resources[$kind]['collection'];
```

Use `$resources[$kind]['label']` for label extraction. Before normalizing each Series candidate, exclude any nonstring/missing type and anything other than Tour:

```php
if ($kind === 'series' && (! is_string($raw['type'] ?? null)
    || strcasecmp(trim($raw['type']), 'Tour') !== 0)) {
    continue;
}
```

Return Series context as `artistType=null`, `area=null`, `country=null`, `writers=[]`, while preserving bounded optional `disambiguation`. The existing candidate UUID/name validation and ten-result limit still apply. At the end of the existing literal prefix query builder:

```php
$literal = $kind.':('.implode(' AND ', $escaped).')';
return $kind === 'series' ? $literal.' AND type:tour' : $literal;
```

Both query and response validation are necessary: upstream results are untrusted. The cache namespace automatically separates `series` from `artist`/`work`; do not create a separate Tour outbound gate or expose a generic Series endpoint.

- [ ] **Step 4: Add the authenticated route, explicit local-model mapping, and proof kind.**

Add `MusicBrainzController::tours(MusicBrainzSearchRequest $request): JsonResponse` calling its private `search($request, 'series')`. Add this route inside the existing authenticated group, alongside existing reads:

```php
Route::get('/musicbrainz/tours', [MusicBrainzController::class, 'tours'])
    ->middleware('throttle:musicbrainz')->name('musicbrainz.tours');
```

Replace the controller's non-Artist-is-Song assumption with:

```php
[$reference, $relation] = match ($kind) {
    'artist' => [ArtistProviderId::class, 'artist'],
    'work' => [SongProviderId::class, 'song'],
    'series' => [TourProviderId::class, 'tour'],
};
```

Match `resource_type=$kind`, attach local summaries after cache retrieval, and use `name` for Artist/Tour and `title` for Work. Tour local summaries have null `artistType` and null `disambiguation`; do not read nonexistent Tour metadata or update its name. Extend `MusicBrainzSelection::read()`'s allowlist to `['artist','work','series']`; retain v1 proof shape, expected-kind checking, UUID validation, expiry, and field errors. Do not expose a public proof-creation endpoint.

- [ ] **Step 5: Add explicit compatibility/failure assertions and run the focused gate.**

Add these assertions to the lookup class:

```php
public function test_tours_share_provider_pacing_and_reject_provider_artist_filters(): void
{
    $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
    Http::fake(['musicbrainz.org/*' => Http::response(['artists' => [], 'series' => []])]);
    $this->get('/api/v1/musicbrainz/artists?q=Soda')->assertOk();
    $this->get('/api/v1/musicbrainz/tours?q=Eras')->assertStatus(429);
    Http::assertSentCount(1);
    $this->get('/api/v1/musicbrainz/tours?q=Eras&artist_uuid=00000000-0000-4000-8000-000000000001')
        ->assertUnprocessable();
    Http::assertSentCount(1);
}
```

Extend existing fake-empty, HTTP 503/429, malformed-envelope, and proof-expiry tests with `series`/`tours` cases; use `Cache::flush()` between uncached fake requests so the global gate is not the failure under test. Assert that non-Tour-only results produce 200/empty, while malformed `series` envelopes produce 503. A wrong-kind Artist proof must fail when read as Series. Existing Artist/Work tests run unchanged.

```bash
php artisan test --compact tests/Feature/MusicBrainzLookupTest.php tests/Feature/CatalogReadEndpointsTest.php
vendor/bin/pint --dirty --format agent
git diff --check
```

Rerun the affected tests if formatting changed tested PHP. Expect no real provider requests.

- [ ] **Step 6: Commit the independently testable read extension only if authorized.**

```bash
git add backend/app/Http/MusicBrainzClient.php backend/app/Http/Controllers/MusicBrainzController.php backend/app/Http/MusicBrainzSelection.php backend/routes/api.php backend/tests/Feature/MusicBrainzLookupTest.php
git commit -m "feat: add MusicBrainz Tour series suggestions"
```

Because these files currently contain uncommitted prerequisite work, inspect the complete staged diff before any authorized commit. Do not claim the extension alone is isolated if its prerequisite hunks are also included; keep commits aligned with the agreed release state.

### Task 2: Persist confirmed Tours with optional backward-compatible sidecars

**Files:** Modify `backend/app/Http/Requests/PendingCatalogRules.php`, `backend/app/Http/CatalogDraftResolver.php`, `backend/tests/Feature/MusicBrainzSaveTest.php`, `MusicBrainzConcurrencyTest.php`, `musicbrainz-race-worker.php`.

**Interfaces:**
- Consumes: optional paired `pendingCatalog.tours` and `pendingCatalog.tourChoices`, with existing four maps preserved; `series` proof from Task 1.
- Produces: the same `CatalogDraftResolver::resolve(array $concert, array $pending): array` interface, filling mapped `concert.catalog.tours[position].catalogId`. Existing controller and collection response contracts need no new durable fields.

- [ ] **Step 1: Add failing identity-reuse and legacy-compatibility tests.**

In `MusicBrainzSaveTest`, import `Tour`, `TourProviderId`, and `Event`. Add a helper that composes with either its current `draft()` or `pending()` fixture:

```php
private function withTour(?array $body = null): array
{
    $body ??= $this->draft();
    $key = (string) Str::uuid();
    $body['concert']['catalog']['tours'] = [['name' => 'The Eras Tour', 'catalogId' => null]];
    $body['pendingCatalog'] ??= ['artists' => [], 'songs' => [], 'lineup' => [], 'entries' => []];
    $body['pendingCatalog']['tours'] = [$key => ['name' => 'The Eras Tour',
        'selection' => app(MusicBrainzSelection::class)
            ->issue('series', '23aa9d94-ffc6-4160-a7f3-31cfba1ea646')]];
    $body['pendingCatalog']['tourChoices'] = ['0' => $key];
    return $body;
}

public function test_selected_tour_reuses_one_provider_identity_across_accounts_and_preserves_saved_facts(): void
{
    $this->actingAs(User::factory()->create());
    $saved = $this->postJson('/api/v1/attendances', $this->withTour())
        ->assertOk()->json('data.concerts.0');
    $tour = Tour::query()->where('external_id', $saved['catalog']['tours'][0]['catalogId'])->firstOrFail();
    $this->assertNotSame('23aa9d94-ffc6-4160-a7f3-31cfba1ea646', $tour->external_id);
    $tour->update(['name' => 'Locally reviewed name']);
    $second = $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/attendances', $this->withTour())->assertOk()->json('data.concerts.0');
    $this->assertSame($tour->external_id, $second['catalog']['tours'][0]['catalogId']);
    $this->assertSame('Locally reviewed name', $second['catalog']['tours'][0]['name']);
    $this->assertDatabaseCount('tours', 1);
    $this->assertDatabaseCount('tour_provider_ids', 1);
    $this->assertDatabaseHas('tour_provider_ids', ['provider' => 'musicbrainz',
        'resource_type' => 'series', 'provider_resource_id' => '23aa9d94-ffc6-4160-a7f3-31cfba1ea646']);
    Http::assertNothingSent();
}

public function test_old_sidecars_and_plain_manual_tours_still_save_without_new_maps(): void
{
    $this->actingAs(User::factory()->create());
    $old = $this->pending();
    $old['concert']['catalog']['tours'] = [['name' => 'Manual Tour', 'catalogId' => null]];
    $this->postJson('/api/v1/attendances', $old)->assertOk();
    $empty = $this->draft();
    $empty['pendingCatalog'] = ['artists' => [], 'songs' => [], 'lineup' => [], 'entries' => [],
        'tours' => [], 'tourChoices' => []];
    $this->postJson('/api/v1/attendances', $empty)->assertOk();
    $this->assertDatabaseCount('tours', 1);
    $this->assertDatabaseCount('tour_provider_ids', 0);
    Http::assertNothingSent();
}
```

- [ ] **Step 2: Establish pending Tour assertions fail before adding save support.**

```bash
php artisan test --compact tests/Feature/MusicBrainzSaveTest.php
```

Expected: the new selected-Tour request fails current sidecar allowlists or identity assertions. Existing requests without Tour maps retain their acceptance.

- [ ] **Step 3: Add optional primitive rules and exact reference/proof checks.**

Extend the parent allowlist with `tours,tourChoices`. Keep existing four maps required/present as they are today when `pendingCatalog` exists. Add these rules only in that existing present-object branch:

```php
$rules['pendingCatalog.tours'] = ['sometimes', 'array', 'max:100'];
$rules['pendingCatalog.tours.*'] = ['array:name,selection'];
$rules['pendingCatalog.tours.*.name'] = ['required', 'string', 'max:255'];
$rules['pendingCatalog.tours.*.selection'] = ['present', 'nullable', 'string', 'max:4096'];
$rules['pendingCatalog.tourChoices'] = ['sometimes', 'array', 'max:100'];
$rules['pendingCatalog.tourChoices.*'] = ['required', 'uuid'];
```

In `PendingCatalogRules::check()`, enforce paired map presence before processing. Use `$pending['tours'] ?? []` and `$pending['tourChoices'] ?? []`, preserving old-client omission. Extend proof/reference bookkeeping to three maps with expected resource kinds Artist→artist, Song→work, Tour→series; reject key collisions across all three, not just the current Artist/Song pair. Referenced-only checking must include Tours.

For every Tour position apply this validation:

```php
$tourDrafts = $pending['tours'] ?? [];
$tourRefs = $pending['tourChoices'] ?? [];
if (array_key_exists('tours', $pending) !== array_key_exists('tourChoices', $pending)) {
    $error('tours');
}
foreach ($tourRefs as $position => $key) {
    $input = $tourDrafts[$key] ?? null;
    $choice = $concert['catalog']['tours'][$position] ?? null;
    if (! preg_match('/^(0|[1-9][0-9]*)$/', (string) $position)
        || $choice === null || $input === null || $choice['catalogId'] !== null
        || $choice['name'] !== $input['name'] || isset($concert['catalog']['eventId'])) {
        $error('tourChoices');
    }
    $used['tours'][$key] = true;
}
```

Read each non-null Tour selection with `read($proof,'series',"pendingCatalog.tours.$key.selection")`, accumulating field errors in the existing validator. Reject invalid/expired/wrong-kind proofs before database writes. A pending Tour name may be manual with null selection. Do not add provider calls during validation or final save.

- [ ] **Step 4: Extend the resolver's explicit job/model mapping.**

Add `tours => series` jobs using `foreach ($pending[$map] ?? [] ...)`. The current sorted jobs and nested savepoint algorithm remain unchanged. Import Tour/TourProviderId and make the return type explicit:

```php
private function identity(string $kind, array $fields, ?string $mbid): Artist|Song|Tour
{
    [$model, $reference, $foreignKey, $relation] = match ($kind) {
        'artist' => [Artist::class, ArtistProviderId::class, 'artist_id', 'artist'],
        'work' => [Song::class, SongProviderId::class, 'song_id', 'song'],
        'series' => [Tour::class, TourProviderId::class, 'tour_id', 'tour'],
    };
    if ($mbid === null) {
        return $model::query()->create($fields);
    }
    $tuple = ['provider' => 'musicbrainz', 'resource_type' => $kind, 'provider_resource_id' => $mbid];
    $existing = $reference::query()->where($tuple)->first();
    if ($existing !== null) {
        return $existing->{$relation};
    }
    try {
        return DB::transaction(function () use ($model, $reference, $foreignKey, $tuple, $fields): Artist|Song|Tour {
            $parent = $model::query()->create($fields);
            $reference::query()->create([$foreignKey => $parent->id, ...$tuple]);
            return $parent;
        });
    } catch (UniqueConstraintViolationException $failure) {
        $winner = $reference::query()->where($tuple)->first();
        if ($winner === null) {
            throw $failure;
        }
        return $winner->{$relation};
    }
}
```

The existing algorithm now includes Tour in both return annotations. It rolls parent creation back on a losing provider-reference insert and reuses the winning tuple. Tour `$fields` contains only `name`, since selection is removed before resolution.

Apply resolved Tour keys before returning the Concert:

```php
foreach ($pending['tourChoices'] ?? [] as $position => $key) {
    $concert['catalog']['tours'][$position]['catalogId'] = $resolved['tours'][$key];
}
```

The existing controller consumes these UUIDs and prevents duplicate Event–Tour links. Reused Tour fields/roster remain unchanged. Do not edit Tour models, provider migrations, resources, or collection serialization.

- [ ] **Step 5: Add adversarial mapping, rollback, and existing-Event tests.**

Use fresh bodies in each iteration so failures are isolated:

```php
public function test_invalid_tour_maps_or_proofs_create_no_domain_records(): void
{
    $this->actingAs(User::factory()->create());
    foreach (['half', 'unused', 'position', 'name', 'forged', 'wrong-kind', 'expired'] as $case) {
        $body = $this->withTour();
        $key = array_key_first($body['pendingCatalog']['tours']);
        if ($case === 'half') { unset($body['pendingCatalog']['tourChoices']); }
        if ($case === 'unused') { $body['pendingCatalog']['tourChoices'] = []; }
        if ($case === 'position') { $body['pendingCatalog']['tourChoices'] = ['01' => $key]; }
        if ($case === 'name') { $body['concert']['catalog']['tours'][0]['name'] = 'Different'; }
        if ($case === 'forged') { $body['pendingCatalog']['tours'][$key]['selection'] = 'forged'; }
        if ($case === 'wrong-kind') {
            $body['pendingCatalog']['tours'][$key]['selection'] = app(MusicBrainzSelection::class)
                ->issue('artist', '3f8a5e5b-c24b-4068-9f1c-afad8829e06b');
        }
        if ($case === 'expired') { $this->travel(25)->hours(); }
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
        if ($case === 'expired') { $this->travelBack(); }
        foreach (['artists', 'songs', 'tours', 'tour_provider_ids', 'events', 'attendances'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
```

Add an existing Event factory case with pending Tour refs and assert 422/no Tour; a same-key Artist/Tour collision with both legitimately referenced and assert 422; and a valid null-selection Tour draft saving without a provider reference. For late rollback, compose `withTour($this->pending())`, duplicate its resolved lineup Artist into a second slot, and assert the existing duplicate-lineup failure rolls back Tours/provider IDs too. Preserve distinct same-name Tours with different MBIDs using two proof-backed pending keys and two slots; assert two Tour IDs. Use `Http::assertNothingSent()` for every save path.

- [ ] **Step 6: Extend the existing guarded PostgreSQL race harness to Series.**

In `MusicBrainzConcurrencyTest`, add `series` to the existing resource loop. Replace its input-construction conditional with three explicit branches, using the same random-MBID proof/barrier directory:

```php
if ($kind === 'artist') {
    $concert['artists'] = [$label];
    $concert['catalog']['artistIds'] = [null];
    $pending['artists'][$key] = ['name' => $label, 'type' => null,
        'disambiguation' => null, 'selection' => $proof];
    $pending['lineup'][0] = $key;
} elseif ($kind === 'work') {
    $concert['setlist'] = [['id' => $entry, 'type' => 'song', 'title' => $label,
        'artist' => '', 'tape' => false]];
    $pending['songs'][$key] = ['title' => $label, 'disambiguation' => null, 'selection' => $proof];
    $pending['entries'][$entry] = ['song' => $key];
} else {
    $concert['catalog']['tours'] = [['name' => $label, 'catalogId' => null]];
    $pending['tours'] = [$key => ['name' => $label, 'selection' => $proof]];
    $pending['tourChoices'] = ['0' => $key];
}
$referenceMapping = match ($kind) {
    'artist' => [ArtistProviderId::class, Artist::class, 'name'],
    'work' => [SongProviderId::class, Song::class, 'title'],
    'series' => [TourProviderId::class, Tour::class, 'name'],
};
[$reference, $parent, $field] = $referenceMapping;
```

In the worker, replace its model/field mapping and final output with:

```php
[$model, $labelField] = match ($input['kind']) {
    'artist' => [Artist::class, 'name'],
    'work' => [Song::class, 'title'],
    'series' => [Tour::class, 'name'],
};
```

Extend the existing created-event callback parameter to `Artist|Song|Tour`. After the existing transaction, emit the correct resolved UUID:

```php
fwrite(STDOUT, match ($input['kind']) {
    'artist' => $saved['catalog']['artistIds'][0],
    'work' => $saved['setlist'][0]['songCatalogId'],
    'series' => $saved['catalog']['tours'][0]['catalogId'],
});
```

Import Tour in the worker and both Tour models in the parent test. Preserve its two-created-parent barrier, database-name guard, HTTP prevention, process cleanup, and scoped random-data cleanup. Do not let a Series case fall into the old Work branch. Existing Artist/Work cases must still execute.

- [ ] **Step 7: Run the focused save/constraint gate and conditionally commit.**

```bash
php artisan test --compact tests/Feature/MusicBrainzSaveTest.php tests/Feature/CollectionEndpointsTest.php tests/Feature/ProviderReferencesTest.php
vendor/bin/pint --dirty --format agent
git diff --check
```

Run the guarded PostgreSQL harness under the dedicated test connection in Task 4. SQLite skipping it is not concurrency proof. With commit authorization and a reviewed prerequisite-aware staged diff:

```bash
git add backend/app/Http/Requests/PendingCatalogRules.php backend/app/Http/CatalogDraftResolver.php backend/tests/Feature/MusicBrainzSaveTest.php backend/tests/Feature/MusicBrainzConcurrencyTest.php backend/tests/Feature/musicbrainz-race-worker.php
git commit -m "feat: persist confirmed MusicBrainz Tour identities atomically"
```

### Task 3: Extend provider types and pure Tour draft serialization

**Files:** Modify `frontend/src/musicbrainz.ts`, `musicbrainzApi.ts`, `concertWizard.ts`, `frontend/tests/musicbrainz-api.test.mjs`, `concert-wizard.test.mjs`.

**Interfaces:**
- Consumes: Task 1 Series DTO and Task 2 optional maps; current `CatalogChoice`, `WizardState`, and draft finalizer.
- Produces: `MusicBrainzKind` including `series`; `client.search('series',q,signal)` using the Tour route; `TourDraftInput`, `PendingTour`, `WizardTourChoice`; optional Tour maps in `PendingCatalog`; `changeTourInput(input: TourDraftInput, patch: Partial<TourDraftInput>): TourDraftInput`; extended `finishWizard()` with unchanged `WizardSubmission`/durable Concert shape.

- [ ] **Step 1: Add failing Series transport/decoder and Tour draft tests.**

Append to `musicbrainz-api.test.mjs`, reusing its existing `row` fixture only for common shape fields:

```js
test('Tour suggestions use the Series kind and fixed first-party Tour route', async () => {
  let call
  const tour = { ...row, id: '23aa9d94-ffc6-4160-a7f3-31cfba1ea646', kind: 'series',
    label: 'The Eras Tour', artistType: null, disambiguation: 'Taylor Swift tour',
    area: null, country: null, writers: [], local: null }
  const client = createMusicBrainzClient('https://api.spectacles.test', async (url, init) => {
    call = { url, init }; return Response.json({ data: [tour] })
  })
  const results = await client.search('series', ' Eras & Tour ')
  assert.equal(call.url, 'https://api.spectacles.test/api/v1/musicbrainz/tours?q=Eras+%26+Tour')
  assert.equal(call.init.credentials, 'include'); assert.equal(call.init.body, undefined)
  assert.equal(results[0].kind, 'series'); assert.equal(results[0].label, 'The Eras Tour')
  await assert.rejects(createMusicBrainzClient('', async () => Response.json({ data: [row] }))
    .search('series', 'Eras'))
})
```

Add `changeTourInput` to the existing wizard test imports. Define this helper in `concert-wizard.test.mjs` using its existing `id`, `createWizard`, `decodeCatalogEvent`, and `catalogFixture` imports:

```js
const tourChoice = (number = 80, mbid = '23aa9d94-ffc6-4160-a7f3-31cfba1ea646') => ({
  name: 'The Eras Tour', catalogId: null,
  draft: { key: id(number), mbid, input: { name: 'The Eras Tour', selection: 'series-proof' } },
})
test('Tour-only sidecars preserve slot mapping and never leak draft data into Concert choices', () => {
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Concert'
  state.draft.tours = [{ name: 'Existing', catalogId: id(5) }, tourChoice()]
  const body = finishWizard(state)
  assert.deepEqual(body.pendingCatalog.tourChoices, { '1': id(80) })
  assert.deepEqual(body.pendingCatalog.tours, { [id(80)]: { name: 'The Eras Tour', selection: 'series-proof' } })
  assert.deepEqual(body.concert.catalog.tours, [
    { name: 'Existing', catalogId: id(5) }, { name: 'The Eras Tour', catalogId: null },
  ])
  assert.equal(JSON.stringify(body.concert).includes('series-proof'), false)
  assert.equal(JSON.stringify(body.concert).includes('draft'), false)
})
test('removed Tours and adopted Events prune pending Tour metadata while retaining personal data', () => {
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Concert'
  state.draft.tours = [tourChoice()]; state.draft.concert.notes = 'Keep notes'
  const adopted = adoptWizardEvent(state, decodeCatalogEvent(catalogFixture().detail), [])
  assert.equal(finishWizard(adopted).pendingCatalog, undefined)
  assert.equal(adopted.draft.concert.notes, 'Keep notes')
  state.draft.tours = []
  assert.equal(finishWizard(state).pendingCatalog, undefined)
})
test('Tour name edits become manual and duplicates use identity rather than name', () => {
  const input = { name: 'The Eras Tour', selection: 'series-proof' }
  assert.equal(changeTourInput(input, { name: 'My Tour' }).selection, null)
  assert.equal(input.selection, 'series-proof')
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Concert'
  state.draft.tours = [tourChoice(80), tourChoice(81)]
  assert.throws(() => finishWizard(state), /once/)
  state.draft.tours[1] = tourChoice(81, id(99))
  assert.equal(finishWizard(state).concert.catalog.tours.length, 2)
})
```

- [ ] **Step 2: Run the new frontend tests to establish missing route/serialization behavior.**

```bash
node --test tests/musicbrainz-api.test.mjs tests/concert-wizard.test.mjs
```

Expected: unsupported Series search and absent Tour map/helper assertions fail. Existing Artist/Work cases remain meaningful; do not rewrite them to accept the wrong resource.

- [ ] **Step 3: Add the fixed Series route/kind without widening provider access.**

Extend `MusicBrainzKind` to include `series`. Preserve the strict candidate object shape, expected-kind/UUID/duplicate checks, safe allowlisted decoding, and existing response limits. In `createMusicBrainzClient`, replace the two-way route ternary and guard with:

```ts
const paths = { artist: 'artists', work: 'works', series: 'tours' } as const
if (!Object.hasOwn(paths, kind)) throw new MusicBrainzApiError(422, 'Unsupported search.')
const path = paths[kind]
```

Use `${path}` in the existing first-party URL; retain its `URLSearchParams`, deadline, cookie GETs, redirect prevention, abort cleanup, and safe failures. No browser request targets MusicBrainz directly. Existing Artist/Work routes and errors remain unchanged. Extend the current safe-error/timeout/cancellation tests with one Series invocation to verify it follows the existing transport rather than a second client implementation.

- [ ] **Step 4: Add transient Tour types and identity-changing helper.**

Add the three Tour types from Shared Interfaces. Change `WizardDraft` to omit and redefine `tours` in addition to its current Artists/Concert overrides:

```ts
export type WizardDraft = Omit<ConcertDraft, 'artists' | 'tours' | 'concert'> & {
  artists: WizardArtistChoice[]
  tours: WizardTourChoice[]
  concert: Omit<ConcertDraft['concert'], 'setlist'> & { setlist: WizardEntry[] }
}
export function changeTourInput(input: TourDraftInput, patch: Partial<TourDraftInput>): TourDraftInput {
  return { ...input, ...patch,
    ...(patch.name !== undefined && patch.name !== input.name ? { selection: null } : {}) }
}
```

Add optional `tours` and `tourChoices` fields to `PendingCatalog`, leaving the established four fields intact. Tour choices returned by `adoptCatalogEvent` remain structurally valid because `draft` is optional. No changes to durable `CatalogChoice` or `domain.ts` are needed.

Update choice identity checking in `wizardStepError` to handle Artist and Tour draft shapes directly:

```ts
const keys = choices.map(choice => {
  if (choice.catalogId) return `local:${choice.catalogId}`
  if (!choice.draft) return null
  return choice.draft.input.selection && choice.draft.mbid
    ? `musicbrainz:${choice.draft.mbid}` : `draft:${choice.draft.key}`
}).filter((key): key is string => key !== null)
if (new Set(keys).size !== keys.length) throw new Error('Choose each identity only once.')
```

Use the MBID only while its input selection remains non-null; cleared manual identities must not retain provider duplicate semantics. Unidentified manual choices with the same name remain allowed as before. Keep current maximum 100 choices and nonempty-name validation.

- [ ] **Step 5: Collect referenced Tours and strip their draft fields during finalization.**

Within `finishWizard`, register each new Tour once per stable draft key and emit its slot mapping. Insert this block after existing Artist/setlist registration and Artist–Song collision checks, before `finishConcertDraft`; it assumes existing `pending` and `state` variables:

```ts
state.draft.tours.forEach((choice, index) => {
  const draft = choice.draft
  if (!draft) return
  if (choice.catalogId || state.draft.eventId || choice.name !== draft.input.name)
    throw new Error('Choose one Tour identity.')
  if (!catalogUuid(draft.key) || !draft.input.name.trim() || draft.input.name.length > 255 ||
      draft.input.selection !== null && (typeof draft.input.selection !== 'string' ||
        !draft.input.selection.trim() || draft.input.selection.length > 4096))
    throw new Error('Check new Tour fields.')
  if (pending.artists[draft.key] || pending.songs[draft.key])
    throw new Error('Draft identities must be distinct.')
  pending.tours ??= {}; pending.tourChoices ??= {}
  const previous = pending.tours[draft.key]
  if (previous && JSON.stringify(previous) !== JSON.stringify(draft.input))
    throw new Error('Check catalog draft identity.')
  pending.tours[draft.key] = { name: draft.input.name, selection: draft.input.selection }
  pending.tourChoices[String(index)] = draft.key
})
```

After the existing `finishConcertDraft(state.draft)`, build durable choices explicitly rather than spreading a Tour object:

```ts
const result = finishConcertDraft(state.draft)
const catalog = result.concert.catalog!
const tours = catalog.tours.map(choice => ({ name: choice.name, catalogId: choice.catalogId }))
const hasPending = Object.keys(pending.artists).length + Object.keys(pending.songs).length +
  Object.keys(pending.tours ?? {}).length > 0
return { ...result, concert: { ...result.concert, catalog: { ...catalog, tours }, setlist },
  ...(hasPending ? { pendingCatalog: pending } : {}) }
```

This replaces the existing return, retaining its separately finalized setlist. `catalog!` is justified by the existing finalizer that always constructs it; do not make the durable domain type broader. Old Artist/Song-only requests keep the new maps absent. Removing a Tour/adopting an Event naturally drops its pending metadata because only current choices are collected.

- [ ] **Step 6: Add exact zero/manual/conflicting-map regression cases and run the module gate.**

Append this to the wizard test file:

```js
test('empty and plain manual Tours need no sidecar; selected Event rejects an injected pending Tour', () => {
  const state = createWizard(); state.draft.precision = 'unknown'; state.draft.concert.title = 'Concert'
  assert.equal(finishWizard(state).pendingCatalog, undefined)
  state.draft.tours = [{ name: 'Manual Tour', catalogId: null }]
  assert.equal(finishWizard(state).pendingCatalog, undefined)
  const adopted = adoptWizardEvent(state, decodeCatalogEvent(catalogFixture().detail), [])
  adopted.draft.tours = [tourChoice()]
  assert.throws(() => finishWizard(adopted), /Tour identity/)
})
```

Also assert that a Tour draft key colliding with a currently referenced Artist or Song throws, and a label-edited Tour with null proof serializes as manual creation. These protect the server's reference rules before the user reaches final Save.

```bash
node --test tests/musicbrainz-api.test.mjs tests/concert-wizard.test.mjs tests/catalog-draft.test.mjs tests/collection-api.test.mjs
npm run build
git diff --check
```

- [ ] **Step 7: Commit the typed draft/client extension only if authorized.**

```bash
git add frontend/src/musicbrainz.ts frontend/src/musicbrainzApi.ts frontend/src/concertWizard.ts frontend/tests/musicbrainz-api.test.mjs frontend/tests/concert-wizard.test.mjs
git commit -m "feat: carry MusicBrainz Tour selections through wizard drafts"
```

### Task 4: Connect Tour autocomplete, verify fallback behavior, and document evidence

**Files:** Modify `frontend/src/components/EntityAutocomplete.tsx`, `WizardTourStep.tsx`, `frontend/tests/concert-wizard-ui.test.mjs`, `README.md`, `frontend/README.md`, `docs/verification/2026-10-25-concert-wizard-musicbrainz.md`.

**Interfaces:**
- Consumes: existing `EntityOption` candidate union, Task 3 Series client/kind and Tour draft types, unchanged step props and editing-state callback.
- Produces: the current Tour select shows local and MusicBrainz choices, confirms new names, preserves manual/local operation, and submits the extended sidecar through the already wired `WizardSubmission`. No new App/collection-client wiring or public shared-catalog filter is necessary.

- [ ] **Step 1: Extend the existing SSR test imports and Tour-step assertions.**

Load `WizardTourStep` in `concert-wizard-ui.test.mjs` using its existing Vite server lifecycle. Add `WizardTourStep` to the declared component bindings, and assign it from `/src/components/WizardTourStep.tsx` in `before()`.

```js
test('Tour keeps manual/optional entry and labels Artist filtering as local-only', () => {
  const state = createWizard()
  state.draft.artists = [{ name: 'Taylor Swift', catalogId: '00000000-0000-4000-8000-000000000001' }]
  const html = render(WizardTourStep, { state, venues: [], catalog, musicbrainz,
    onChange: () => {}, onEditingChange: () => {} })
  assert.match(html, /Find Tours/)
  assert.match(html, /Filter local Tours by selected Artist/)
  assert.match(html, /Create manually/)
  assert.match(html, /Skip tour/)
  assert.doesNotMatch(html, /<form/)
})
```

Run before implementation:

```bash
node --test tests/concert-wizard-ui.test.mjs
```

Expected: the new explicit filtering label is absent. SSR cannot prove provider effects or interaction; use the browser matrix below for those requirements.

- [ ] **Step 2: Enable the existing provider effect for Tours consistently.**

In `EntityAutocomplete`, import `MusicBrainzKind` and derive the supported provider kind once:

```ts
const providerKind: MusicBrainzKind | null = kind === 'artists' ? 'artist'
  : kind === 'songs' ? 'work' : kind === 'tours' ? 'series' : null
```

Use that value in the provider effect guard, request, effect dependencies, disabled/sign-in hint, and loading hint. Replace every Artists/Songs-only conditional, not just the HTTP call. The actual request remains `providerClient.search(providerKind, query, signal)` with no local filters passed to it. Events/Venues still have no MusicBrainz request.

Retain independent results/errors, current request keys, shared latest-read cancellation, two-character minimum, 350 ms debounce, source grouping, local duplicate suppression by `candidate.local.id`, pagination behavior, IME handling, and all manual creation actions. Show Tour context from the candidate's Series kind/disambiguation; do not display inferred Artist membership. Local narrowing is still sent only through `localClient.search` and its existing allowed filter. A failed external query must not clear local results or disable manual fields.

- [ ] **Step 3: Extend the existing inline Tour editor and source-aware selection.**

Replace its string-only `name` editor state with:

```ts
type TourEditor = { input: TourDraftInput; mbid?: string; context?: string | null }
const [editing, setEditing] = useState<TourEditor | null>(null)
```

Use `editing !== null` in the existing `onEditingChange` effect, alongside its selected-Event decision state. Keep the same `modify()`/detach decision mechanism. Its add handler now takes `WizardTourChoice` and ignores duplicate local UUIDs or active identical provider MBIDs; it must allow distinct same-name records. Rejecting a duplicate must not write or alter an existing Tour.

Handle sources as follows:

```ts
if (option.source === 'catalog' && option.kind === 'tours') {
  add({ name: option.row.name, catalogId: option.row.id })
}
if (option.source === 'musicbrainz' && option.candidate.kind === 'series') {
  const candidate = option.candidate
  if (candidate.local) {
    add({ name: candidate.local.label, catalogId: candidate.local.id })
  } else {
    setEditing({ input: { name: candidate.label, selection: candidate.selection },
      mbid: candidate.id, context: candidate.disambiguation })
  }
}
if (option.source === 'create') {
  setEditing({ input: { name: option.query, selection: null } })
}
```

Render the existing New Tour name field (maximum 255), with source hint and optional provider disambiguation while its proof remains selected. Use `changeTourInput` on name edits; clear the editor's `mbid`/context when the proof becomes null. Keep Use tour/Cancel as `type="button"`, disabling confirmation for a blank trimmed name. Confirm normalizes its label and adds a draft only for selected provider identity:

```ts
const input = { ...editing.input, name: editing.input.name.trim() }
if (input.selection) {
  add({ name: input.name, catalogId: null,
    draft: { key: crypto.randomUUID(), input, mbid: editing.mbid } })
} else {
  add({ name: input.name, catalogId: null })
}
setEditing(null)
```

This keeps ordinary manual Tours on their existing request path, including a renamed provider-prefilled choice that has become manual. Cancel only clears the child editor. Maintain stable pending keys for confirmed choices; remove controls refer to each selected item, including distinct same-name Tours. Preserve Skip tour, multiple choices, and existing Event-detach decisions.

Change the checkbox text to **Filter local Tours by selected Artist** and add a short hint that MusicBrainz suggestions use the Tour name. Its `artist_uuid` value is still a persisted local UUID; unsaved Artist keys must not become filter IDs. Do not set Artist relationships on confirmed Tours.

- [ ] **Step 4: Run the complete automated acceptance gates once integration is wired.**

From `frontend/`:

```bash
npm run lint
npm run build
npm test
```

From `backend/` using the active machine's PHP prefix:

```bash
php artisan test --compact tests/Feature/MusicBrainzLookupTest.php tests/Feature/MusicBrainzSaveTest.php tests/Feature/ProviderReferencesTest.php tests/Feature/CollectionEndpointsTest.php
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

If formatting changes tested PHP, rerun its affected focused gate. No real provider requests belong in these tests.

For concurrency/SQL portability, resolve the current dedicated test connection without printing credentials. Only after confirming `spectacles_testing` is disposable and isolated:

```bash
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=spectacles_testing DB_URL= php artisan migrate --no-interaction
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=spectacles_testing DB_URL= php artisan test --compact tests/Feature/MusicBrainzSaveTest.php tests/Feature/MusicBrainzConcurrencyTest.php tests/Feature/ProviderReferencesTest.php
```

Preserve the guard and scoped cleanup in the existing race test. If the test connection remains unavailable, report PostgreSQL/race verification as unrun. Do not guess users, create roles, or use the developer database to remove that limit.

- [ ] **Step 5: Verify the user-facing Tour path with deterministic provider fixtures.**

Use the established local app/runtime only after confirming it serves this checkout. Use disposable test records or a temporary fixture mounting the real wizard with fake catalog/provider clients and a nonpersistent save callback. Keep fixture files outside permanent production routes; remove them after acceptance. Do not alter an existing user draft, save an unrequested attendance, or add a production debug bypass. Record actual observations for this matrix:

| Scenario | Expected result |
|---|---|
| Tour name prefix | Local and MusicBrainz groups load independently; source and disambiguation distinguish candidates |
| New Series selection | Name prefilled; no selection saved before explicit Use tour; Next blocked while child editor is open |
| Rename provider-prefilled Tour | Source becomes manual, proof/MBID cleared, final payload has ordinary manual Tour |
| Existing local/provider-matched Tour | Saved local name/UUID reused, no edit of shared facts |
| Duplicate MBID/local ID versus same-name different IDs | Duplicate not added twice; distinct Tour identities remain possible |
| No Tour, multiple Tours, remove a pending Tour, Back navigation | Existing optional/multiple behavior retained and sidecar refs match current positions |
| Adopt existing Event | Prior pending Tours disappear from final request; notes/setlist retained |
| Change selected Event's Tour | Explicit detach decision; declining retains the saved Event relationship |
| Provider disabled/503/429/malformed/empty | Local search and manual creation continue, failure distinguished from no results |
| Filter local Tours by Artist | Only local request gets `artist_uuid`; external suggestions remain name searches with clear labeling |
| Slow A then fast B, close/reopen, session changes, keyboard/IME | Only current options applied; no stale 401, accidental submit, or premature save |
| Final save/reload and cancellation | Confirmed Series reference resolves to one local Tour; abandoned suggestions create no domain rows |
| Existing Artist/Work search and saved-concert edit | Prior selection, independent performer behavior, and edit flow remain intact |

Inspect first-party requests to prove no Tour/Attendance mutation before final Save, and prove the saved collection contains no `selection`, `draft`, or transient MBID fields. Backend tests establish database changes; a nonpersistent fixture callback alone does not prove authenticated save/reload.

If live acceptance is available, use the already configured contact-derived User-Agent and shared gate for one identified Tour prefix search such as `Eras`. Inspect type/name/MBID and explicit confirmation; make no linked-Event/Artist imports. Space any second query by at least two seconds. Do not invent contact details, print the operator's email, loop through retries, or treat a tiny sample as a coverage guarantee. Report live failure as unavailable and preserve local/manual fallback.

- [ ] **Step 6: Update accurate usage/verification notes and review the final diff.**

Update `README.md` and `frontend/README.md` statements that MusicBrainz supports only Artist/Work or that Tours are local/manual only. Describe Tours as Tour-filtered Series suggestions with local/manual fallback, unchanged optional choices, name-based external search, local-only Artist narrowing, and confirmed `musicbrainz/series` identity. Do not edit the historical scope/design to claim this extension was previously shipped.

Append a dated extension result to `docs/verification/2026-10-25-concert-wizard-musicbrainz.md`, listing actual commands, pass/fail/skip counts, browser scenarios, live results, and PostgreSQL limits. Do not include secrets, proofs, account data, or fabricated passing checks.

```bash
git diff --check
git diff --stat
git status --short
rg -n 'series|tourChoices|pendingCatalog|MusicBrainz|Tours.*local' backend/app/Http backend/routes frontend/src README.md frontend/README.md
```

Review all new/untracked implementation files as well as tracked diffs. Confirm no migrations/dependencies, metadata leakage, automatic domain writes, roster imports, provider-filter invention, duplicate provider infrastructure, or unrelated login/configuration edits. Preserve the live MusicBrainz contact/enablement settings as found.

- [ ] **Step 7: Commit the integrated UI/evidence extension only if authorized.**

```bash
git add frontend/src/components/EntityAutocomplete.tsx frontend/src/components/WizardTourStep.tsx frontend/tests/concert-wizard-ui.test.mjs README.md frontend/README.md docs/verification/2026-10-25-concert-wizard-musicbrainz.md
git commit -m "feat: add MusicBrainz autocomplete to the Tour step"
```

Report implementation, verification limits, and exact delivery status. Push/open PRs to `dev` and `main` only with applicable authorization, using the same feature branch and no integration from `dev`.

## Self-review and execution handoff

| Requirement | Owner/proof |
|---|---|
| Tour-only Series query/response and correct identity namespace | Task 1 fake HTTP/type/proof tests; Task 2 saved tuple assertions |
| Local/manual fallback, zero/multiple Tours, source review | Tasks 3–4 draft/SSR tests and browser failure/selection matrix |
| Optional maps preserve old clients; reject bad references | Task 2 legacy/empty/half-map/proof/rollback tests |
| Existing provider-linked Tour reused without shared mutations | Tasks 1–2 cache-local-match and cross-account saved-identity tests |
| Same-name distinct IDs and duplicate identity protection | Tasks 2–3 same-name/MBID tests; Task 4 browser choices |
| Event adoption/detachment, pruning, no durable metadata | Task 3 payload/pruning tests and Task 4 browser/network checks |
| Shared provider budgets and existing Artist/Work behavior | Task 1 shared-gate tests; Tasks 3–4 current client suites and full gates |
| Race rollback and SQLite/PostgreSQL compatibility | Task 2 existing harness extended to Series; Task 4 dedicated PostgreSQL checks |
| No roster/event imports, no schema/dependency change | Global constraints, explicit file map, and Task 4 scope review |

Planning self-review completed against the extension spec: the four tasks cover every requirement; shared names consistently use `series`, `tours`, and `tourChoices`; optional maps preserve legacy requests; callback/worker return types include Tour; and finalization explicitly strips transient metadata. Each Review Focus item has an automated-test owner or explicit interactive acceptance where pure/SSR tests cannot establish the behavior. Matching global constraints, local document links, Markdown fences, unresolved-marker scans, and whitespace checks passed. No product code, product tests, browser state, configuration, commits, or delivery actions changed during planning. No additional product choice blocks the plan; implementation requires a separate user instruction.
