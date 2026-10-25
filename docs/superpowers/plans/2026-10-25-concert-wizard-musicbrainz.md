# Concert Wizard and MusicBrainz Implementation Plan

> **Execution:** Follow the user-selected execution method and repository requirements. This plan does not require any additional skill. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create an Artist → Tour → Event → Setlist wizard with local-record reuse, manual creation, and user-confirmed MusicBrainz Artist/Work autocomplete.

**Architecture:** Maintain one in-memory wizard draft and submit it once through the existing transactional Attendance write. Laravel mediates MusicBrainz reads and resolves confirmed pending Artists/Songs against the existing provider-ID tables; existing shared facts and durable collection DTOs remain unchanged. The current edit form and post-creation setlist editor continue to work.

**Tech Stack:** Installed PHP 8.5.10, Laravel 13.38.0, Sanctum 4.3.3, PHPUnit 12.5.40, React 19, TypeScript 6, Vite 8, Node 24, native fetch, Laravel HTTP/cache/cryptography, SQLite and PostgreSQL. No new packages.

**Spec:** [Concert Creation Wizard and MusicBrainz Design](../specs/2026-10-25-concert-wizard-musicbrainz-design.md). Read it with the [existing domain specification](../specs/2026-10-22-live-events-database-design.md); the companion spec controls this release's narrower provider scope and wizard behavior.

## Global Constraints

- Scope is planning only until the user authorizes implementation.
- Use the installed Laravel 13, Sanctum 4, PHP 8.5, React 19, TypeScript 6, Vite 8, and Node 24 toolchain; add no dependencies.
- Preserve the existing typography, colors, drawer, fields, buttons, and responsive visual language.
- Keep one in-memory draft across all four steps and persist domain records only on final Save concert.
- Preserve multiple Artists, zero or multiple Tours, nullable Venue/timezone, incomplete dates, midnight/seconds, Unicode names, zero coordinates, repeated songs, notes, and tape flags.
- Local catalog UUIDs and provider identifiers remain separate; never match records by name or title alone.
- MusicBrainz Artist IDs belong to artist_provider_ids with provider=musicbrainz and resource_type=artist; composition IDs belong to song_provider_ids with provider=musicbrainz and resource_type=work.
- Suggestions require user confirmation; searching, highlighting, or abandoning a suggestion creates no catalog or Attendance records.
- Keep manual creation available during empty results, missing configuration, rate limiting, malformed provider responses, and provider/network failures.
- Preserve Sanctum cookie authentication, CSRF for writes, account isolation, shared Event authorization, and last-save-wins behavior.
- Existing shared catalog records are reused without modifying their saved facts.
- Setlists remain Attendance-owned ordered entries; do not introduce a public setlist catalog.
- Do not add setlist.fm, Geoapify, external Tour/Event lookup, Spotify resolution, recording-to-work discovery, background refresh, synchronization, schema migrations, or browser storage of drafts.
- Preserve SQLite and PostgreSQL compatibility and use the machine's configured PHP/Composer command prefixes.
- Implement on one feature branch based on main with only release dependencies; do not merge or rebase dev into it. Commit, push, PR, and deployment actions require the applicable user authorization.

## Review Focus

1. An existing Event replaces earlier Artist/Tour choices: preview the replacement, preserve personal/setlist drafts, and omit abandoned pending entities (Tasks 3 and 5).
2. Same-name Artists and a wrong first Work match: require an explicit choice, preserve distinct identities, and never infer a performer (Tasks 1, 3, and 4).
3. A slow response, input-method composition, or account change occurs mid-search: suppress stale choices, preserve text, and prevent accidental final submission (Tasks 3, 4, and 6).
4. Provider failures, two users selecting the same MBID, or an expired selection proof: preserve manual entry and atomic saves, reuse the winning identity, and keep the draft on failure (Tasks 1, 2, and 6).
5. Empty lineup/Tour/setlist, partial date, midnight/seconds, zero coordinates, or repeated song: preserve those supported values without inventing missing facts (Tasks 2, 3, 5, and 6).

---

## Execution preflight and evidence

This is a planning deliverable. Do not execute its code, generate application files, install packages, or run the prescribed tests until implementation is authorized. The plan was grounded in a clean checkout at `38099a5` on `feat/live-event-data-layer`. Runtime versions were inspected, not inferred from the OS. No product tests were run while writing this plan.

Before execution, inspect the current checkout again:

```bash
pwd
git status --short
git branch --show-current
git log -5 --oneline
git diff --stat main...HEAD
cat ~/.config/agent-skills/runtime.md
```

Use the PHP and Composer prefixes in that machine's runtime file. On the planning machine these are plain `php` and `composer`, run from `backend/`. Read `backend/AGENTS.md`; `.ai/rules` and `backend/.ai/rules` were absent at planning time, so check again. Check package versions before relying on APIs. Keep unrelated changes/data and never use a developer database for destructive tests. If the feature already exists, verify it and stop; if partially present, implement only the missing behavior.

The current branch contains required catalog/account/collection work. Before code changes, establish that these are direct dependencies in the same release. Reuse the single release feature branch if so; otherwise create `feat/concert-wizard-musicbrainz` from `main` and include only agreed direct prerequisites. Do not merge/rebase `dev`. Worktree isolation is optional; ask about it at execution time if proposing a worktree. This plan does not authorize commits, pushes, PRs, merges, or deployment. Each task's commit command is conditional on later commit authorization.

Use these existing sources:

- `docs/research/2026-10-22-provider-validation.md`: nine successful intended Artist rankings, missing-country and ambiguity cases, eleven observed 503 responses, and the wrong first Work match for Eres.
- `docs/research/evidence/2026-10-22-provider-validation/mb-a02.json`, `mb-a06.json`, `mb-a09.json`, `mb-w03.json`, `mb-w05.json`: canonical accents, duplicate artist names, missing country, composer-versus-performer relationships, wrong Work identity. Read the actual JSON wrappers and copy only minimal public fields into test fixtures.
- `frontend/src/components/ConcertForm.tsx`, `concertDraft.ts`, `catalogApi.ts`, `collectionApi.ts`, `App.tsx`: existing boundaries to extend.
- `backend/app/Http/Requests/SaveConcertRequest.php`, `Controllers/CollectionController.php`, `Models/ArtistProviderId.php`, `Models/SongProviderId.php`: current validation, transaction, and identity constraints.
- [MusicBrainz search](https://musicbrainz.org/doc/MusicBrainz_API/Search), [API](https://musicbrainz.org/doc/MusicBrainz_API), [Work](https://musicbrainz.org/doc/Work), [data license](https://musicbrainz.org/doc/About/Data_License). The 2026-10-22 exact-name batches do not prove incremental prefix search quality. Verify that separately during acceptance. Later API-overview reads during planning received 429; no new coverage benchmark was performed.

## File structure and responsibilities

All paths below are repository-relative. No new application base directory is needed.

| Task | Files | Responsibility |
|---|---|---|
| 1 | Create `backend/app/Http/MusicBrainzClient.php`, `MusicBrainzSelection.php`, `MusicBrainzUnavailable.php`, `Requests/MusicBrainzSearchRequest.php`, `Controllers/MusicBrainzController.php`; modify `backend/routes/api.php`, `backend/config/services.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/.env.example`; create `backend/tests/Feature/MusicBrainzLookupTest.php` | Authenticated normalized provider reads, global pacing/cache, selection proof, safe errors |
| 2 | Create `backend/app/Http/CatalogDraftResolver.php`, `backend/app/Http/Requests/PendingCatalogRules.php`, `backend/tests/Feature/MusicBrainzSaveTest.php`; modify `backend/app/Http/Requests/SaveConcertRequest.php`, `backend/app/Http/Controllers/CollectionController.php` | Validate optional pending catalog payload and resolve identities in the Attendance transaction |
| 3 | Create `frontend/src/musicbrainz.ts`, `musicbrainzApi.ts`, `concertWizard.ts`, `frontend/tests/musicbrainz-api.test.mjs`, `concert-wizard.test.mjs` | Strict provider DTOs/client, typed ephemeral draft, transitions, and save payload |
| 4 | Create `frontend/src/autocomplete.ts`, `components/EntityAutocomplete.tsx`, `ArtistDraftFields.tsx`, `SongDraftFields.tsx`, `frontend/tests/autocomplete.test.mjs`, `autocomplete-ui.test.mjs`; modify `frontend/src/catalog.css`, `concertWizard.ts` | Accessible combined local/provider select and explicit creation fields |
| 5 | Create `frontend/src/components/ConcertWizard.tsx`, `WizardArtistStep.tsx`, `WizardTourStep.tsx`, `WizardEventStep.tsx`, `WizardSetlistStep.tsx`, `SetlistEntryFields.tsx`, `frontend/src/wizard.css`, `frontend/tests/concert-wizard-ui.test.mjs`; modify `frontend/src/components/SetlistEditor.tsx` | Four-step drawer UI and staged setlist editing; preserve existing edit behavior |
| 6 | Modify `frontend/src/App.tsx`, `collectionApi.ts`, `frontend/tests/collection-api.test.mjs`, `frontend/README.md`, `README.md`; create `backend/tests/Feature/MusicBrainzConcurrencyTest.php`, `musicbrainz-race-worker.php`, `docs/verification/2026-10-25-concert-wizard-musicbrainz.md` | Wire creation/final save, prove provider races, and document verified acceptance |

Keep `frontend/src/domain.ts` durable DTOs, the collection response shape, existing resources, and database migrations unchanged. Pending metadata travels beside the Concert, not inside a durable Workspace.

## Contracts shared by the tasks

### Provider suggestion

Task 1 produces and Task 3 strictly decodes this plain read DTO:

```ts
export type MusicBrainzKind = 'artist' | 'work'
export type MusicBrainzCandidate = {
  id: string                 // validated lowercase MBID; never a Spectacles UUID
  kind: MusicBrainzKind
  label: string
  artistType: string | null  // e.g. "person" or "group"; null for Works
  disambiguation: string | null
  area: string | null
  country: string | null
  writers: string[]          // composer/lyricist display names, never performers
  local: { id: string; label: string; artistType: string | null;
    disambiguation: string | null } | null // authoritative local summary, attached after cache read
  selection: string          // opaque encrypted proof issued after cache read
}
export type MusicBrainzPage = { data: MusicBrainzCandidate[] }
```

Routes are `GET /api/v1/musicbrainz/artists?q=...` and `/api/v1/musicbrainz/works?q=...`; query length 2–200, at most ten results. They accept no bodies/extra query fields and return `Cache-Control: private, no-store`. A legitimate zero-result response is `200 {"data":[]}`. Missing config, upstream timeout/503, and malformed envelopes return 503. Application/upstream busy responses return 429 with an integer `Retry-After` header. Both return only a safe `message`, not the upstream body. Authentication errors remain 401.

### Pending catalog metadata

Task 3 produces this optional top-level addition to existing POST/PATCH attendance bodies; Task 2 consumes it. Draft IDs and entry IDs are UUIDs. The lineup keys are canonical zero-based decimal positions (`"0"`, `"1"`).

```ts
export type ArtistDraftInput = {
  name: string; type: string | null; disambiguation: string | null
  selection: string | null
}
export type SongDraftInput = {
  title: string; disambiguation: string | null; selection: string | null
}
export type PendingCatalog = {
  artists: Record<string, ArtistDraftInput>
  songs: Record<string, SongDraftInput>
  lineup: Record<string, string>
  entries: Record<string, { song?: string; artist?: string }>
}
export type WizardSubmission = {
  concert: Concert; venue?: Venue; pendingCatalog?: PendingCatalog
}
```

For a mapped lineup position, `concert.catalog.artistIds[position]` is null and its display name equals the referenced Artist draft name. Mapped performers/Songs have no corresponding local UUID, and their display name/title equals the referenced draft fields. Existing event references prohibit lineup mappings. Existing Event selections still allow pending setlist Songs/performers. An explicit Song draft creates a Song; an unmapped text-only setlist entry preserves null `song_id`. Empty pending maps are omitted from the request. Responses contain the current durable Workspace with canonical local UUIDs, never pending metadata or proofs.

## Task 1: Authenticated MusicBrainz suggestion reads

**Files:** All Task 1 paths in the file table. No migration; cache/lock tables already exist.

**Interfaces:**
- Consumes: authenticated Sanctum user; `q`; Laravel shared cache/HTTP/Crypt; existing provider-ID models.
- Produces: the two GET routes and `MusicBrainzPage`; `MusicBrainzClient::search(string $kind, string $query): array`; `MusicBrainzSelection::issue(string $kind, string $mbid): string`; `MusicBrainzSelection::read(string $proof, string $kind, string $path): string` returning the MBID or field-specific `ValidationException`; `MusicBrainzUnavailable` with public readonly `int $status` and `int $retryAfter`.

- [ ] **Step 1: Generate the endpoint and test classes with the repository's Artisan commands.**

From `backend/`, after checking the runtime and `php artisan make:class --help` / `make:test --help`:

```bash
php artisan make:class Http/MusicBrainzClient --no-interaction
php artisan make:class Http/MusicBrainzSelection --no-interaction
php artisan make:class Http/MusicBrainzUnavailable --no-interaction
php artisan make:request MusicBrainzSearchRequest --no-interaction
php artisan make:controller MusicBrainzController --no-interaction
php artisan make:test MusicBrainzLookupTest --phpunit --no-interaction
```

- [ ] **Step 2: Add failing endpoint tests before wiring routes.**

Use `RefreshDatabase`, `Http::preventStrayRequests()`, `Cache::flush()` on the test-only array cache, and these settings in `setUp()`:

```php
config(['services.musicbrainz.enabled' => true,
    'services.musicbrainz.user_agent' => 'SpectaclesTests/1.0 (offline tests)',
    'cache.default' => 'array']);
Http::preventStrayRequests();
```

Representative tests inside `MusicBrainzLookupTest` (import User, Artist, ArtistProviderId, Cache, Http, RefreshDatabase, TestCase):

```php
public function test_search_requires_authentication_and_does_not_create_artists(): void
{
    $this->getJson('/api/v1/musicbrainz/artists?q=Soda')->assertUnauthorized();
    Http::fake(['musicbrainz.org/ws/2/artist*' => Http::response([
        'artists' => [['id' => '3f8a5e5b-c24b-4068-9f1c-afad8829e06b',
            'name' => 'Soda Stereo', 'type' => 'Group', 'area' => ['name' => 'Buenos Aires']]],
    ])]);
    $this->actingAs(User::factory()->create())->getJson('/api/v1/musicbrainz/artists?q=Soda')
        ->assertOk()->assertJsonPath('data.0.label', 'Soda Stereo')
        ->assertJsonPath('data.0.artistType', 'group')
        ->assertJsonPath('data.0.country', null)->assertJsonPath('data.0.local', null);
    $this->assertDatabaseCount('artists', 0);
    $this->assertDatabaseCount('attendances', 0);
    Http::assertSentCount(1);
}

public function test_cached_search_attaches_a_new_local_match_without_another_provider_call(): void
{
    $this->actingAs(User::factory()->create());
    Http::fake(['musicbrainz.org/*' => Http::response(['artists' => [[
        'id' => '3f8a5e5b-c24b-4068-9f1c-afad8829e06b', 'name' => 'Soda Stereo',
    ]]])]);
    $this->getJson('/api/v1/musicbrainz/artists?q=Soda')->assertOk()
        ->assertJsonPath('data.0.local', null);
    $artist = Artist::factory()->create(['name' => 'Saved local name']);
    ArtistProviderId::factory()->create(['artist_id' => $artist->id,
        'provider' => 'musicbrainz', 'resource_type' => 'artist',
        'provider_resource_id' => '3f8a5e5b-c24b-4068-9f1c-afad8829e06b']);
    $this->getJson('/api/v1/musicbrainz/artists?q=Soda')->assertOk()
        ->assertJsonPath('data.0.local.id', $artist->external_id)
        ->assertJsonPath('data.0.local.label', 'Saved local name');
    Http::assertSentCount(1);
}

public function test_artist_and_work_requests_share_one_outbound_gate(): void
{
    $this->freezeTime();
    $this->actingAs(User::factory()->create());
    Http::fake(['musicbrainz.org/*' => Http::response(['artists' => [], 'works' => []])]);
    $this->getJson('/api/v1/musicbrainz/artists?q=Soda')->assertOk();
    $this->getJson('/api/v1/musicbrainz/works?q=Eres')->assertStatus(429)
        ->assertHeader('Retry-After');
    Http::assertSentCount(1);
    $this->travelTo(now()->addMilliseconds(1101));
    $this->getJson('/api/v1/musicbrainz/works?q=Eres')->assertOk();
    Http::assertSentCount(2);
}
```

Add explicit data-driven cases in this same class:

| Input/setup | Assertion |
|---|---|
| `q=AC/DC`, `q=WOS OR *:*`, quotes, backslash, accents, emoji | Outbound query contains only literal escaped terms plus application-added suffix wildcards; no user operator is evaluated |
| empty/one-character/201-character q, extra `url` query, GET JSON body | 422 and `Http::assertNothingSent()` |
| missing country, duplicate names with different IDs, unknown type | Candidate preserved; unknown type null; distinct candidates retained |
| wrong `Eres` Work first, composer/lyricist relations and recording/performance relations | Correct Work IDs/writer names; no performer field/inference and no automatic selection |
| one malformed candidate beside a valid one | Only valid sibling returned; malformed envelope returns 503 |
| HTTP 503, 429 with huge/invalid Retry-After, HTML, connection exception | Safe 503/429; Retry-After bounded to 1–60 seconds; no debug body, no cached false empty list |
| success with empty list, then same query | Two successful empty responses, one outbound call |
| blank User-Agent or disabled integration | 503, no outbound call; local catalog route still 200 |
| held shared lock | 429, no outbound call; no request thread sleeping |
| forged/expired/wrong-kind selection proof | `read()` throws field-specific validation error |

- [ ] **Step 3: Run the new tests and record the intended failure.**

```bash
php artisan test --compact tests/Feature/MusicBrainzLookupTest.php
```

Expected initially: missing endpoints/classes, not a network call or unrelated database failure.

- [ ] **Step 4: Implement the literal query builder, normalization, cache, and global request gate.**

Keep a fixed origin/path allowlist in `MusicBrainzClient`; `artist` maps to `artist`/`artists` and `work` to `work`/`works`. Construct prefix queries as follows, then pass them through Laravel HTTP query parameters for URL encoding:

```php
private function query(string $kind, string $query): string
{
    $terms = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);
    $escaped = array_map(function (string $term): string {
        foreach (str_split('\\+-!(){}[]^"~*?:/&|') as $character) {
            $term = str_replace($character, '\\'.$character, $term);
        }
        return $term.'*';
    }, $terms);
    return $kind.':('.implode(' AND ', $escaped).')';
}
```

Cache key: `musicbrainz:v1:{kind}:{sha256(trimmed-query)}`. Cache only normalized public candidate arrays for 900 seconds. Do not lowercase saved provider names or strip accents. Normalize known Artist type names to lowercase English; unsupported/missing type becomes null. Work `writers` includes distinct nonempty `artist.name` values from relations with `type` composer or lyricist only. Exclude malformed candidates with non-UUID IDs, missing/overlong label, or invalid optional field shapes. Bound labels, disambiguation, and contextual strings to 255 characters by excluding invalid candidate fields/records as specified; never silently truncate an identity label. Optional invalid context can become null; invalid required IDs/labels exclude the record. Ignore duplicate provider IDs after their first valid candidate. Limit output to ten.

The shared gate surrounds the actual outbound call, not just per-user inbound traffic:

```php
$lock = Cache::lock('musicbrainz:v1:outbound', 10);
if (! $lock->get()) {
    throw new MusicBrainzUnavailable(429, 2);
}
try {
    $nowMs = now()->getTimestampMs();
    $nextMs = (int) Cache::get('musicbrainz:v1:next-start-ms', 0);
    if ($nextMs > $nowMs) {
        throw new MusicBrainzUnavailable(429, (int) ceil(($nextMs - $nowMs) / 1000));
    }
    Cache::put('musicbrainz:v1:next-start-ms', $nowMs + 1100, now()->addSeconds(10));
    $response = Http::acceptJson()->withUserAgent($userAgent)
        ->connectTimeout(2)->timeout(5)->withoutRedirecting()
        ->get('https://musicbrainz.org/ws/2/'.$kind, [
            'query' => $this->query($kind, $query), 'fmt' => 'json', 'limit' => 10,
        ]);
} finally {
    $lock->release();
}
```

Declare `MusicBrainzUnavailable::__construct(public readonly int $status = 503, public readonly int $retryAfter = 2)` and call `parent::__construct('MusicBrainz suggestions are unavailable. Manual entry is still available.')`. Translate only known transport/response/cache-unavailable failures into this exception; do not hide programming errors. Recheck the successful cache entry after acquiring the lock to avoid duplicate same-query misses. Do not auto-retry HTTP errors. Keep cache lookup outside the gate for successful hits. Use the shared default database cache in deployment; never switch it to a per-process store to make tests pass.

- [ ] **Step 5: Implement selection proof and endpoint configuration/wiring.**

In `MusicBrainzSelection`, use `Crypt::encryptString(json_encode([...], JSON_THROW_ON_ERROR))` with `{v:1,kind,mbid,expiresAt:now()->addDay()->timestamp}`. `read()` decrypts and validates all four values, allows only `artist`/`work`, validates UUID syntax, requires the expected kind and a future expiry, and throws `ValidationException::withMessages([$path => 'Select this MusicBrainz result again or use manual creation.'])` on invalid proofs. It performs no HTTP or domain writes.

`MusicBrainzSearchRequest` requires a signed-in user, q string length 2–200, and rejects body/extra query fields. The controller's separate `artists()`/`works()` methods call `search()` with fixed kinds, bulk query matching provider IDs, attach a nullable `local` summary using the saved name/title/type/disambiguation and `external_id`, issue fresh proofs, and return the DTO with no-store. Local Artist types use saved values; local Song summaries use null artistType. Do not cache local matches or selection proofs. Catch `MusicBrainzUnavailable` here and return its safe status/header.

```php
Route::get('/musicbrainz/artists', [MusicBrainzController::class, 'artists'])
    ->middleware('throttle:musicbrainz')->name('musicbrainz.artists');
Route::get('/musicbrainz/works', [MusicBrainzController::class, 'works'])
    ->middleware('throttle:musicbrainz')->name('musicbrainz.works');
```

Place both inside the existing `auth:sanctum` group. Register `RateLimiter::for('musicbrainz', fn (Request $request): Limit => Limit::perMinute(30)->by($request->user()->external_id));`. This inbound user limit supplements the global outbound gate.

Add `services.musicbrainz` with `enabled => env('MUSICBRAINZ_ENABLED', false)` and `user_agent => env('MUSICBRAINZ_USER_AGENT', '')`. Add these literal example defaults:

```dotenv
MUSICBRAINZ_ENABLED=false
MUSICBRAINZ_USER_AGENT=
```

Enabling requires the operator to supply an identifying application name/version plus a real contact address or URL. Do not invent contact details, read credentials into the plan, or add a MusicBrainz API key setting. Missing configuration returns a recoverable provider-only failure.

- [ ] **Step 6: Prove the complete read contract and review the diff.**

```bash
php artisan test --compact tests/Feature/MusicBrainzLookupTest.php tests/Feature/CatalogReadEndpointsTest.php tests/Feature/AuthSessionTest.php
vendor/bin/pint --dirty --format agent
git diff --check
```

Expected: normalized fake-provider reads pass; local catalogs/auth retain existing behavior; no real provider request. If formatting changes tested PHP, rerun the affected tests. Check each table case above has a concrete assertion, not only a status code.

- [ ] **Step 7: Commit this independently testable endpoint deliverable if authorized.**

```bash
git add backend/app/Http/MusicBrainzClient.php backend/app/Http/MusicBrainzSelection.php backend/app/Http/MusicBrainzUnavailable.php backend/app/Http/Requests/MusicBrainzSearchRequest.php backend/app/Http/Controllers/MusicBrainzController.php backend/routes/api.php backend/config/services.php backend/app/Providers/AppServiceProvider.php backend/.env.example backend/tests/Feature/MusicBrainzLookupTest.php
git commit -m "feat: add authenticated MusicBrainz suggestions"
```

Otherwise leave this scoped change local and report the same evidence.

## Task 2: Resolve pending catalog entities in the existing save transaction

**Files:** Create `backend/app/Http/CatalogDraftResolver.php`, `backend/app/Http/Requests/PendingCatalogRules.php`, `backend/tests/Feature/MusicBrainzSaveTest.php`; modify `backend/app/Http/Requests/SaveConcertRequest.php` and `backend/app/Http/Controllers/CollectionController.php`.

**Interfaces:**
- Consumes: validated `PendingCatalog`, existing Concert payload, and Task 1 selection proofs. Existing clients may omit `pendingCatalog`.
- Produces: `PendingCatalogRules::rules(bool $present): array`, `PendingCatalogRules::check(array $concert, array $pending, Validator $validator): void`, and `CatalogDraftResolver::resolve(array $concert, array $pending): array` returning the Concert array with resolved local UUIDs. `resolve()` runs only inside the caller's transaction; it never calls MusicBrainz. New public request fields are only the optional top-level `pendingCatalog` contract above.

- [ ] **Step 1: Create the focused classes and failing save tests.**

```bash
php artisan make:class Http/CatalogDraftResolver --no-interaction
php artisan make:class Http/Requests/PendingCatalogRules --no-interaction
php artisan make:test MusicBrainzSaveTest --phpunit --no-interaction
```

Use `RefreshDatabase` and `Http::preventStrayRequests()` for every test. Define these private helpers in `MusicBrainzSaveTest` so the tests do not depend on another test class's private fixtures:

```php
private function draft(): array
{
    return ['concert' => [
        'id' => (string) Str::uuid(), 'title' => 'Festival Ñ', 'artists' => ['Soda Stereo'],
        'venueId' => null, 'date' => ['year' => 2024, 'month' => null, 'day' => null],
        'time' => '00:00:07', 'timezone' => null, 'interested' => true,
        'ticketsBought' => false, 'notes' => '',
        'catalog' => ['artistIds' => [null], 'tours' => []],
        'setlist' => [['id' => (string) Str::uuid(), 'type' => 'song',
            'title' => 'El fantasma de Canterville', 'artist' => 'Soda Stereo', 'tape' => false]],
    ]];
}

private function withPending(array $body): array
{
    $artistKey = (string) Str::uuid();
    $songKey = (string) Str::uuid();
    $proof = app(MusicBrainzSelection::class);
    $body['pendingCatalog'] = [
        'artists' => [$artistKey => ['name' => 'Soda Stereo', 'type' => 'group',
            'disambiguation' => null,
            'selection' => $proof->issue('artist', '3f8a5e5b-c24b-4068-9f1c-afad8829e06b')]],
        'songs' => [$songKey => ['title' => 'El fantasma de Canterville',
            'disambiguation' => 'Charly García composition',
            'selection' => $proof->issue('work', '8b21973e-836a-441b-9e4d-4e67e7114d75')]],
        'lineup' => ['0' => $artistKey],
        'entries' => [$body['concert']['setlist'][0]['id'] => ['song' => $songKey, 'artist' => $artistKey]],
    ];
    return $body;
}

public function test_one_pending_artist_is_reused_for_lineup_and_performer_and_song_survives_reload(): void
{
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    $body = $this->withPending($this->draft());
    $saved = $this->postJson('/api/v1/attendances', $body)->assertOk()->json('data.concerts.0');
    $this->assertSame($saved['catalog']['artistIds'][0], $saved['setlist'][0]['artistCatalogId']);
    $this->assertNotEmpty($saved['setlist'][0]['songCatalogId']);
    $this->assertDatabaseCount('artists', 1);
    $this->assertDatabaseCount('songs', 1);
    $this->assertDatabaseHas('song_provider_ids', ['provider' => 'musicbrainz',
        'resource_type' => 'work', 'provider_resource_id' => '8b21973e-836a-441b-9e4d-4e67e7114d75']);
    $this->getJson('/api/v1/collection')->assertOk()->assertJsonPath('data.concerts.0', $saved);
    $this->assertArrayNotHasKey('pendingCatalog', $saved);
    Http::assertNothingSent();
}

public function test_existing_provider_identity_is_reused_without_overwriting_shared_facts(): void
{
    $artist = Artist::factory()->create(['name' => 'Saved Artist', 'disambiguation' => 'Keep me']);
    ArtistProviderId::factory()->create(['artist_id' => $artist->id,
        'provider' => 'musicbrainz', 'resource_type' => 'artist',
        'provider_resource_id' => '3f8a5e5b-c24b-4068-9f1c-afad8829e06b']);
    $this->actingAs(User::factory()->create());
    $saved = $this->postJson('/api/v1/attendances', $this->withPending($this->draft()))
        ->assertOk()->json('data.concerts.0');
    $this->assertSame($artist->external_id, $saved['catalog']['artistIds'][0]);
    $this->assertSame('Saved Artist', $artist->fresh()->name);
    $this->assertSame('Keep me', $artist->fresh()->disambiguation);
    $this->assertDatabaseCount('artists', 1);
}

public function test_expired_proof_and_late_duplicate_lineup_leave_no_partial_domain_records(): void
{
    $this->actingAs(User::factory()->create());
    $expired = $this->withPending($this->draft());
    $this->travelTo(now()->addHours(25));
    $this->postJson('/api/v1/attendances', $expired)->assertUnprocessable();
    $body = $this->withPending($this->draft());
    $key = array_key_first($body['pendingCatalog']['artists']);
    $body['concert']['artists'][] = 'Soda Stereo';
    $body['concert']['catalog']['artistIds'][] = null;
    $body['pendingCatalog']['lineup']['1'] = $key;
    $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
    foreach (['artists', 'songs', 'artist_provider_ids', 'song_provider_ids', 'events', 'attendances'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
}
```

Import the concrete classes used above: Artist, ArtistProviderId, User, MusicBrainzSelection, Str, Http. Add a second-user version of the first test, using another Event/Attendance UUID, and assert one shared Artist/Song identity but two account-isolated Attendances. Add a repeated-Song case by giving a second row a new entry UUID and mapping it to the same pending Song key; assert two ordered entries with one Song UUID.

- [ ] **Step 2: Run the new tests before implementing resolution.**

```bash
php artisan test --compact tests/Feature/MusicBrainzSaveTest.php
```

Expected: current writes ignore/reject pending data and the identity assertions fail. Preserve the old `CollectionEndpointsTest` behavior; do not change its expected text-only Song behavior.

- [ ] **Step 3: Add strict pending rules and reference checks.**

`PendingCatalogRules::rules($present)` returns the following rules. Merge its result into `SaveConcertRequest::rules()` using `$this->exists('pendingCatalog')`, so omission and an explicitly supplied empty/null value are distinguished:

```php
$parent = ['pendingCatalog' => ['sometimes', 'array:artists,songs,lineup,entries']];
if (! $present) {
    return $parent;
}
return [...$parent,
    'pendingCatalog.artists' => ['present', 'array', 'max:1100'],
    'pendingCatalog.artists.*' => ['array:name,type,disambiguation,selection'],
    'pendingCatalog.artists.*.name' => ['required', 'string', 'max:255'],
    'pendingCatalog.artists.*.type' => ['present', 'nullable', 'string', 'max:255'],
    'pendingCatalog.artists.*.disambiguation' => ['present', 'nullable', 'string', 'max:255'],
    'pendingCatalog.artists.*.selection' => ['present', 'nullable', 'string', 'max:4096'],
    'pendingCatalog.songs' => ['present', 'array', 'max:1000'],
    'pendingCatalog.songs.*' => ['array:title,disambiguation,selection'],
    'pendingCatalog.songs.*.title' => ['required', 'string', 'max:255'],
    'pendingCatalog.songs.*.disambiguation' => ['present', 'nullable', 'string', 'max:255'],
    'pendingCatalog.songs.*.selection' => ['present', 'nullable', 'string', 'max:4096'],
    'pendingCatalog.lineup' => ['present', 'array', 'max:100'],
    'pendingCatalog.lineup.*' => ['required', 'uuid'],
    'pendingCatalog.entries' => ['present', 'array', 'max:1000'],
    'pendingCatalog.entries.*' => ['array:song,artist'],
    'pendingCatalog.entries.*.song' => ['sometimes', 'required', 'uuid'],
    'pendingCatalog.entries.*.artist' => ['sometimes', 'required', 'uuid'],
];
```

The four child maps use `present`: empty arrays/maps are valid. Omitted `pendingCatalog` remains valid for old clients; a supplied null object is invalid. Test both omission and all-empty maps explicitly. Do not use a required rule that rejects the valid empty maps.

After primitive validation succeeds, `check()` must:

1. Validate every Artist/Song/entry map key as a UUID and each lineup key with `/^(0|[1-9][0-9]*)$/`, within lineup bounds.
2. Require each mapping to point to a defined draft and actual row/position; require mapped name/title equality. Reject simultaneous local UUID and pending identity, a nonempty mapping for a note, an empty entry mapping, duplicate draft key across Artist and Song namespaces, and pending lineup data while `catalog.eventId` is present.
3. Collect referenced draft keys; reject unreferenced entries in either entity map, including Artists abandoned when an existing Event is adopted.
4. Read every non-null proof using Task 1's `read()` with resource kind and its exact field path, adding validation errors without making HTTP calls. Reject invalid/expired/wrong-kind proofs before persistence. Use Laravel validation messages for ordinary rules.

Call this from the existing request `after()` only when existing validation has no errors. Preserve current date/coordinate/note rules.

- [ ] **Step 4: Implement identity resolution with savepoints and no shared-record update.**

Decode proofs and sort provider-backed inputs by `kind:mbid` before resolving. Manual draft keys resolve once in key order. `CatalogDraftResolver` returns maps of draft keys to local UUIDs, applies them to a copy of the Concert, and uses no `updateOrCreate` or name lookup. Use this core algorithm for each selected identity, with full types and imports in the real class:

```php
private function identity(string $kind, array $fields, ?string $mbid): Artist|Song
{
    [$model, $reference, $foreignKey, $relation] = $kind === 'artist'
        ? [Artist::class, ArtistProviderId::class, 'artist_id', 'artist']
        : [Song::class, SongProviderId::class, 'song_id', 'song'];
    if ($mbid === null) {
        return $model::query()->create($fields);
    }
    $tuple = ['provider' => 'musicbrainz', 'resource_type' => $kind, 'provider_resource_id' => $mbid];
    $existing = $reference::query()->where($tuple)->first();
    if ($existing !== null) {
        return $existing->{$relation};
    }
    try {
        return DB::transaction(function () use ($model, $reference, $foreignKey, $tuple, $fields): Artist|Song {
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

For Songs pass `kind='work'`, so the tuple is `resource_type=work`. The conditional's non-artist branch deliberately uses Song/SongProviderId. Nested `DB::transaction` creates a savepoint inside the existing outer transaction, preventing PostgreSQL's failed-transaction state and orphan loser parents. Rethrow unique violations with no winning tuple; do not swallow arbitrary database failures.

In `CollectionController::persist()`, after the existing user/Attendance locks and authorization and before new Event/catalog creation, call:

```php
$data = app(CatalogDraftResolver::class)->resolve(
    $request->validated('concert'), $request->validated('pendingCatalog', [])
);
```

Keep this within the existing outer `DB::transaction`. The resolver does nothing for an absent/empty pending object. Resolved performer and Song UUIDs enter the existing setlist linking path. Existing duplicate Artist lineup checking rejects any same-identity collision after resolution, rolling back the entire operation. Never serialize proofs or modify collection resources. Existing Event choices retain their original shared facts.

- [ ] **Step 5: Add adversarial and compatibility tests with concrete mutations.**

Add this pattern to the test class; each loop starts with a fresh valid body and tests only one violation:

```php
public function test_invalid_pending_references_are_rejected_before_any_domain_write(): void
{
    $this->actingAs(User::factory()->create());
    foreach (['missing-artist', 'bad-position', 'unused-song', 'wrong-kind', 'note-identity'] as $case) {
        $body = $this->withPending($this->draft());
        $artistKey = array_key_first($body['pendingCatalog']['artists']);
        $songKey = array_key_first($body['pendingCatalog']['songs']);
        $entryId = $body['concert']['setlist'][0]['id'];
        if ($case === 'missing-artist') {
            $body['pendingCatalog']['lineup']['0'] = (string) Str::uuid();
        } elseif ($case === 'bad-position') {
            $body['pendingCatalog']['lineup'] = ['01' => $artistKey];
        } elseif ($case === 'unused-song') {
            unset($body['pendingCatalog']['entries'][$entryId]['song']);
        } elseif ($case === 'wrong-kind') {
            $body['pendingCatalog']['songs'][$songKey]['selection'] =
                $body['pendingCatalog']['artists'][$artistKey]['selection'];
        } else {
            $body['concert']['setlist'][0]['type'] = 'note';
        }
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
        $this->assertDatabaseCount('artists', 0);
        $this->assertDatabaseCount('songs', 0);
        $this->assertDatabaseCount('events', 0);
    }
}
```

Add a manual-versus-text-only test: set both selections null in `withPending()`, save and assert one Song/no provider IDs; then submit `draft()` without pending metadata with a different attendance ID and assert the second entry has no `songCatalogId` and total Songs remains one. Add a selected existing Event with no lineup mapping plus a pending setlist Song; assert no Event or lineup Artist facts change. Add a local UUID plus pending mapping case and require 422. Preserve existing `CollectionEndpointsTest` cases for member permissions, seats, notes, flags, duplicates, archive/undo, and legacy clients.

Use the existing provider-reference constraint tests to verify the database still rejects duplicate tuples. Task 6's two-process PostgreSQL test forces both parents to exist before competing reference inserts, proving savepoint rollback and winner reuse through the real resolver. Do not simulate a committed competing winner inside the same transaction: it would roll back with the loser and would not test the intended race.

- [ ] **Step 6: Run focused checks and format.**

```bash
php artisan test --compact tests/Feature/MusicBrainzSaveTest.php tests/Feature/CollectionEndpointsTest.php tests/Feature/ProviderReferencesTest.php
vendor/bin/pint --dirty --format agent
git diff --check
```

Expected: zero HTTP calls on all saves; legacy saves remain valid; failed writes leave no new domain rows; reuse leaves facts untouched. Rerun changed tests after formatting. PostgreSQL verification is required in Task 6 before reporting completion.

- [ ] **Step 7: Commit the atomic save deliverable if authorized.**

```bash
git add backend/app/Http/CatalogDraftResolver.php backend/app/Http/Requests/PendingCatalogRules.php backend/app/Http/Requests/SaveConcertRequest.php backend/app/Http/Controllers/CollectionController.php backend/tests/Feature/MusicBrainzSaveTest.php
git commit -m "feat: resolve confirmed catalog drafts during attendance saves"
```

## Task 3: Typed provider client and pure wizard draft lifecycle

**Files:** Create `frontend/src/musicbrainz.ts`, `frontend/src/musicbrainzApi.ts`, `frontend/src/concertWizard.ts`, `frontend/tests/musicbrainz-api.test.mjs`, `frontend/tests/concert-wizard.test.mjs`.

**Interfaces:**
- Consumes: Task 1 `MusicBrainzPage`, current `CatalogClient`, `ConcertDraft`, `Concert`, `SetlistEntry`, and `Venue`.
- Produces: `createMusicBrainzClient(baseUrl, fetcher?)` with `search(kind: MusicBrainzKind, q: string, signal?: AbortSignal): Promise<MusicBrainzCandidate[]>`; `MusicBrainzApiError.status`; `createWizard(initialDate?: string): WizardState`; `wizardStepError(state, step): string | null`; `moveWizard(state, step): WizardState`; `adoptWizardEvent(state, event, venues): WizardState`; `detachWizardEvent(state): WizardState`; `changeWizardEntry(entry, patch): WizardEntry`; `finishWizard(state): WizardSubmission`; the types below.

- [ ] **Step 1: Add failing client and draft lifecycle tests.**

Use `node:test` and direct `.ts` imports for pure modules, matching `catalog-draft.test.mjs`. Declare these production types in `concertWizard.ts` and import the existing durable types rather than redefining them:

```ts
export type WizardStep = 'artist' | 'tour' | 'event' | 'setlist'
export type PendingArtist = { key: string; input: ArtistDraftInput; mbid?: string }
export type PendingSong = { key: string; input: SongDraftInput }
export type WizardArtistChoice = CatalogChoice & { draft?: PendingArtist }
export type WizardEntry = SetlistEntry & {
  songDraft?: PendingSong; performerDraft?: PendingArtist
}
export type WizardDraft = Omit<ConcertDraft, 'artists' | 'concert'> & {
  artists: WizardArtistChoice[]
  concert: Omit<Concert, 'setlist'> & { setlist: WizardEntry[] }
}
export type WizardState = { step: WizardStep; draft: WizardDraft }
```

Define/export the shared `ArtistDraftInput`, `SongDraftInput`, `PendingCatalog`, and `WizardSubmission` contract types from this module. `musicbrainz.ts` exports the provider types and `decodeMusicBrainzPage(value: unknown, kind: MusicBrainzKind): MusicBrainzCandidate[]`.

Tests in `concert-wizard.test.mjs`:

```js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createWizard, moveWizard, adoptWizardEvent, detachWizardEvent,
  changeWizardEntry, finishWizard } from '../src/concertWizard.ts'
import { decodeCatalogEvent } from '../src/catalog.ts'
import { catalogFixture, id } from './catalog-fixtures.mjs'

test('back navigation preserves typed values and final payload reuses one draft artist', () => {
  const artist = { key: id(80), input: { name: 'Mi artista', type: null,
    disambiguation: null, selection: null } }
  let state = createWizard('2026-10-25')
  state.draft.artists = [{ name: artist.input.name, catalogId: null, draft: artist }]
  state.draft.concert.notes = 'Keep this note'
  state.draft.concert.setlist = [{ id: id(81), type: 'song', title: 'Unknown song',
    artist: artist.input.name, tape: true, performerDraft: artist }]
  state = moveWizard(moveWizard(state, 'tour'), 'artist')
  assert.equal(state.draft.concert.notes, 'Keep this note')
  assert.equal(state.draft.concert.setlist.length, 1)
  const body = finishWizard(state)
  assert.deepEqual(Object.keys(body.pendingCatalog.artists), [id(80)])
  assert.equal(body.pendingCatalog.lineup['0'], id(80))
  assert.equal(body.pendingCatalog.entries[id(81)].artist, id(80))
  assert.equal(body.concert.setlist[0].performerDraft, undefined)
  assert.equal(body.concert.setlist[0].songCatalogId, undefined)
})

test('confirmed Event adoption replaces lineup/tours and prunes abandoned pending entities', () => {
  const state = createWizard()
  state.draft.artists = [{ name: 'Abandoned', catalogId: null,
    draft: { key: id(90), input: { name: 'Abandoned', type: null,
      disambiguation: null, selection: 'unused-proof' } } }]
  state.draft.concert.notes = 'Personal'
  state.draft.concert.setlist = [{ id: id(91), type: 'note', title: 'Encore', artist: '', tape: false }]
  const adopted = adoptWizardEvent(state, decodeCatalogEvent(catalogFixture().detail), [])
  assert.equal(adopted.draft.concert.notes, 'Personal')
  assert.deepEqual(adopted.draft.concert.setlist, state.draft.concert.setlist)
  const body = finishWizard(adopted)
  assert.equal(body.pendingCatalog, undefined)
  assert.deepEqual(body.concert.catalog.artistIds, [id(1), id(2)])
  const detached = detachWizardEvent(adopted)
  assert.equal(detached.draft.eventId, undefined)
  assert.equal(detached.draft.concert.title, adopted.draft.concert.title)
  assert.equal(adopted.draft.eventId, id(7))
})

test('title and performer edits clear only their respective identities; notes clear both', () => {
  const entry = { id: id(92), type: 'song', title: 'Eres', artist: 'Café Tacvba', tape: true,
    songDraft: { key: id(93), input: { title: 'Eres', disambiguation: null, selection: 'work-proof' } },
    performerDraft: { key: id(94), input: { name: 'Café Tacvba', type: 'group', disambiguation: null, selection: null } } }
  const edited = changeWizardEntry(entry, { title: 'My title' })
  assert.equal(edited.songDraft, undefined)
  assert.deepEqual(edited.performerDraft, entry.performerDraft)
  assert.equal(changeWizardEntry(entry, { artist: 'Someone else' }).performerDraft, undefined)
  const note = changeWizardEntry(entry, { type: 'note' })
  assert.equal(note.songDraft, undefined); assert.equal(note.performerDraft, undefined)
  assert.equal(note.tape, false); assert.equal(note.artist, '')
})

test('empty optional steps and unknown date retain null facts; repeated songs retain entry order', () => {
  const state = createWizard()
  state.draft.precision = 'unknown'; state.draft.timezoneText = ''
  state.draft.concert.title = 'Theatre'
  state.draft.concert.eventType = 'theatre'
  state.draft.concert.time = '00:00:07'
  state.draft.concert.setlist = [1, 2].map(n => ({ id: id(n + 100), type: 'song',
    title: 'Repeated', artist: '', tape: false, songCatalogId: id(4) }))
  const body = finishWizard(state)
  assert.deepEqual(body.concert.artists, [])
  assert.deepEqual(body.concert.catalog.tours, [])
  assert.deepEqual(body.concert.date, { year: null, month: null, day: null })
  assert.equal(body.concert.time, '00:00:07'); assert.equal(body.concert.timezone, null)
  assert.deepEqual(body.concert.setlist.map(row => row.id), [id(101), id(102)])
})
```

In `musicbrainz-api.test.mjs`, use fake fetch with a complete candidate object from the provider contract. Assert cookie GETs to the exact first-party route, encoded q, no body/bearer/CSRF request, unknown/private fields discarded, local summary validated, and wrong-kind/non-UUID IDs rejected. Add the following safe-error test with the production class from `musicbrainzApi.ts`:

```js
test('provider failures remain distinguishable from an empty result and never expose debug data', async () => {
  for (const status of [401, 422, 429, 503]) {
    const client = createMusicBrainzClient('https://api.spectacles.test', async () =>
      new Response(JSON.stringify({ message: 'private upstream debug' }), { status,
        headers: { 'Content-Type': 'application/json', 'Retry-After': '2' } }))
    await assert.rejects(client.search('artist', 'Zoé'), error =>
      error instanceof MusicBrainzApiError && error.status === status &&
      !error.message.includes('private upstream debug'))
  }
})
```

- [ ] **Step 2: Run the focused tests to establish the missing modules fail.**

```bash
node --test tests/musicbrainz-api.test.mjs tests/concert-wizard.test.mjs
```

Run from `frontend/`. Expected: module/export-not-found failures before implementing the modules.

- [ ] **Step 3: Implement the strict provider client and decoder.**

Expose this exact client signature:

```ts
export function createMusicBrainzClient(baseUrl: string, fetcher: typeof fetch = fetch): {
  search(kind: MusicBrainzKind, q: string, signal?: AbortSignal): Promise<MusicBrainzCandidate[]>
}
export class MusicBrainzApiError extends Error {
  readonly status: number
  readonly retryAfter: number | null
  constructor(status: number, message: string, retryAfter: number | null = null) {
    super(message); this.status = status; this.retryAfter = retryAfter
  }
}
```

Implement an 8-second total fetch/body deadline, caller abort propagation, safe status-specific messages, `credentials:'include'`, `cache:'no-store'`, `redirect:'error'`, and `Accept:'application/json'`. Clean the timer/listener in `finally`. Never fetch the MusicBrainz origin from the browser. Check kind against `artist`/`work`, map to the two fixed paths, and use `URLSearchParams({q:q.trim()})`. Decode unknown JSON as the explicit contract; require at most ten distinct IDs, expected kind, nonempty bounded label/proof, nullable strings, string writer array, and complete nullable local summary. Copy only allowlisted fields. Never follow provider URL fields or silently return [] on failure. Use existing `createLatestRead()` in consumers; do not extract/rewrite the current catalog transport as part of this task.

- [ ] **Step 4: Implement pure draft transitions and payload construction.**

Initialize the wizard from `createConcertDraft(undefined, initialDate)` and set `step:'artist'`. `moveWizard()` changes only `step` after validating the current step through `wizardStepError`; Back is always permitted. Artist/Tour steps allow empty selections but reject empty names and duplicate known/draft identities. Event validation uses `finishConcertDraft`; Setlist validation checks row IDs, title, note identity/tape rules, and maximum 1,000 rows. Reject more than 100 Artists/Tours and overlong confirmed identity fields before final save. Keep the existing date/time/coordinate validation from `finishConcertDraft`.

`adoptWizardEvent()` uses the existing `adoptCatalogEvent` helper and preserves staged setlist objects; the Event's lineup replaces pending Artist choices. `detachWizardEvent()` removes only `eventId`, leaving the rest of the draft intact. UI callers must obtain the explicit transition choice before calling it. Selecting a new Work sets its pending Song fields but never changes `entry.artist` or performer selection.

Use this identity-detachment implementation, extending the existing helper:

```ts
export function changeWizardEntry(entry: WizardEntry, patch: Partial<WizardEntry>): WizardEntry {
  const next: WizardEntry = { ...changeSetlistEntry(entry, patch) }
  if (patch.title !== undefined && patch.title !== entry.title && patch.songDraft === undefined) {
    delete next.songDraft
  }
  if (patch.artist !== undefined && patch.artist !== entry.artist && patch.performerDraft === undefined) {
    delete next.performerDraft
  }
  if (next.type === 'note') {
    next.artist = ''; next.tape = false
    delete next.songDraft; delete next.performerDraft
    delete next.songCatalogId; delete next.artistCatalogId
  }
  return next
}
```

`finishWizard()` calls the existing finalizer, collects only currently referenced draft inputs, verifies that repeated keys have identical input data, and strips `songDraft`/`performerDraft` from saved rows. Use the following extraction pattern; `addArtist`/`addSong` are local closures that validate a UUID key and reject a same-key/different-input conflict before assigning the input:

```ts
const pending: PendingCatalog = { artists: {}, songs: {}, lineup: {}, entries: {} }
state.draft.artists.forEach((choice, index) => {
  if (choice.draft) {
    if (choice.catalogId) throw new Error('Choose one Artist identity.')
    addArtist(choice.draft); pending.lineup[String(index)] = choice.draft.key
  }
})
const setlist = state.draft.concert.setlist.map(entry => {
  const { songDraft, performerDraft, ...saved } = entry
  const refs: { song?: string; artist?: string } = {}
  if (songDraft) {
    if (saved.songCatalogId || saved.type !== 'song') throw new Error('Choose one Song identity.')
    addSong(songDraft); refs.song = songDraft.key
  }
  if (performerDraft) {
    if (saved.artistCatalogId || saved.type !== 'song') throw new Error('Choose one performer identity.')
    addArtist(performerDraft); refs.artist = performerDraft.key
  }
  if (refs.song || refs.artist) pending.entries[entry.id] = refs
  return saved
})
const result = finishConcertDraft(state.draft)
const hasPending = Object.keys(pending.artists).length > 0 || Object.keys(pending.songs).length > 0
return { ...result, concert: { ...result.concert, setlist },
  ...(hasPending ? { pendingCatalog: pending } : {}) }
```

Implement `addArtist` and `addSong` with `catalogUuid(key)`, equality of `JSON.stringify(input)` for an already registered key, and assignment to the corresponding map; validate required fields before registration. Keep a selected Event incompatible with pending lineup mappings. Do not place any pending map/proof in `domain.ts`, a Workspace, localStorage, sessionStorage, or saved preview data.

- [ ] **Step 5: Prove timeouts, stale-response suppression, and draft purity.**

Add client tests using the existing Node mock-timer pattern in `catalog-api.test.mjs` for an 8,000 ms deadline and an already-aborted signal. Use `createLatestRead()` to resolve B before A and then reject an old 401 after cancel; assert only B is published and no stale unauthorized callback fires. Add deep-frozen Event/source draft inputs to transition tests so accidental mutation fails. Test pending-map collection ignores a removed row and serializes no proof when an existing local summary was selected. Artist/Song label-change helpers are added and tested in Task 4, avoiding a dependency on code from a later task.

- [ ] **Step 6: Run the module tests plus preserved draft/API checks.**

```bash
node --test tests/musicbrainz-api.test.mjs tests/concert-wizard.test.mjs tests/catalog-draft.test.mjs tests/catalog-api.test.mjs
npm run build
git diff --check
```

Expected: all pure/client checks pass; no live network requests, no changes to durable collection decoding.

- [ ] **Step 7: Commit the typed client/draft deliverable if authorized.**

```bash
git add frontend/src/musicbrainz.ts frontend/src/musicbrainzApi.ts frontend/src/concertWizard.ts frontend/tests/musicbrainz-api.test.mjs frontend/tests/concert-wizard.test.mjs
git commit -m "feat: model concert wizard drafts and MusicBrainz reads"
```

## Task 4: Accessible combined autocomplete and explicit entity creation fields

**Files:** Create `frontend/src/autocomplete.ts`, `frontend/src/components/EntityAutocomplete.tsx`, `ArtistDraftFields.tsx`, `SongDraftFields.tsx`, `frontend/tests/autocomplete.test.mjs`, `autocomplete-ui.test.mjs`; modify `frontend/src/catalog.css` and `frontend/src/concertWizard.ts` for the field-change helpers.

**Interfaces:**
- Consumes: existing `CatalogAccess`; Task 3 provider client/types; optional lineup draft Artists.
- Produces: `MusicBrainzAccess = {client: ReturnType<typeof createMusicBrainzClient>; enabled: boolean; sessionKey: string; onUnauthorized: () => void}`; `EntityAutocomplete` props `{kind: CatalogKind; label: string; catalog: CatalogAccess; musicbrainz: MusicBrainzAccess; filters?: CatalogFilters; draftArtists?: WizardArtistChoice[]; onPick: (option: EntityOption) => void}`. Export `MusicBrainzAccess` from `EntityAutocomplete.tsx`; reuse `CatalogFilters` from `catalogApi.ts`. Export `EntityOption` from `autocomplete.ts` with the union below. `ArtistDraftFields`/`SongDraftFields` receive `initial`, `onConfirm(input)`, and `onCancel()`; they contain no form and make no network/domain writes.

```ts
export type LocalEntityOption = { [K in CatalogKind]: {
  key: string; source: 'catalog'; kind: K; row: CatalogByKind[K]
} }[CatalogKind]
export type EntityOption =
  | LocalEntityOption
  | { key: string; source: 'musicbrainz'; candidate: MusicBrainzCandidate }
  | { key: string; source: 'draft'; artist: WizardArtistChoice }
  | { key: string; source: 'create'; query: string }
export type AutocompleteState = { open: boolean; activeKey: string | null }
export type KeyResult = { state: AutocompleteState; pickKey: string | null; preventDefault: boolean }
```

The component normalizes source keys as `catalog:{kind}:{uuid}`, `musicbrainz:{kind}:{mbid}`, `draft:{uuid}`, and `create`. It returns source-tagged choices, not ambiguous label strings.

- [ ] **Step 1: Add failing keyboard/state and identity-field tests.**

```js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { autocompleteKey } from '../src/autocomplete.ts'
import { changeArtistInput, changeSongInput } from '../src/concertWizard.ts'

test('keyboard selects an explicit active option, Escape closes, IME Enter preserves text', () => {
  const keys = ['catalog:artists:a', 'musicbrainz:artist:b', 'create']
  const initial = { open: false, activeKey: null }
  const opened = autocompleteKey(initial, 'ArrowDown', keys, false)
  assert.equal(opened.state.activeKey, keys[0]); assert.equal(opened.state.open, true)
  const next = autocompleteKey(opened.state, 'ArrowDown', keys, false)
  assert.equal(next.state.activeKey, keys[1])
  const chosen = autocompleteKey(next.state, 'Enter', keys, false)
  assert.equal(chosen.pickKey, keys[1]); assert.equal(chosen.preventDefault, true)
  const ime = autocompleteKey(next.state, 'Enter', keys, true)
  assert.equal(ime.pickKey, null); assert.equal(ime.preventDefault, false)
  const closed = autocompleteKey(next.state, 'Escape', keys, false)
  assert.equal(closed.state.open, false); assert.equal(closed.pickKey, null)
  assert.equal(autocompleteKey(initial, 'Enter', keys, false).pickKey, null)
})

test('editing a prefilling label clears proof but changing descriptive metadata preserves it', () => {
  const artist = { name: 'Soda Stereo', type: 'group', disambiguation: null, selection: 'proof' }
  assert.equal(changeArtistInput(artist, { name: 'My Artist' }).selection, null)
  assert.equal(changeArtistInput(artist, { disambiguation: 'Reviewed' }).selection, 'proof')
  const song = { title: 'Eres', disambiguation: 'Wrong composition', selection: 'proof' }
  assert.equal(changeSongInput(song, { title: 'My Song' }).selection, null)
  assert.equal(song.selection, 'proof')
})
```

Add tests for empty option keys, ArrowUp from no active option selecting the last option, clamping/resetting when the active option disappears, and ordinary characters not consuming keyboard events. Use actual source keys and assert only the explicit pick key is emitted; no code should select a first Work automatically when results arrive.

For `autocomplete-ui.test.mjs`, use the existing Vite SSR lifecycle from `catalog-picker.test.mjs`, importing the new component and field components. Render a guest/disabled control and assert a labeled `role="combobox"`, `aria-autocomplete="list"`, `aria-expanded="false"`, a manual Create action, and no nested `<form>`. Render both draft-field components with empty inputs and assert their confirm buttons are `type="button"`, have accessible names, and are disabled for an empty label.

- [ ] **Step 2: Run the new tests before implementing the components.**

```bash
node --test tests/autocomplete.test.mjs tests/autocomplete-ui.test.mjs
```

Expected: missing functions/components fail. No additional testing packages are needed.

- [ ] **Step 3: Implement the keyboard transition and independent search effects.**

Implement `autocompleteKey(state: AutocompleteState, key: string, keys: string[], composing: boolean): KeyResult` as a pure function. Composing input returns unchanged state/no pick. Arrow keys open and move within current keys; Escape closes and clears active key; Enter returns only the currently active key and closes. Consume Enter even when no option is active, so it cannot submit the parent wizard. A removed active key becomes null; never select another candidate without a key/mouse action.

In `EntityAutocomplete`, keep separate local and provider `createLatestRead()` instances and separate keyed results. Open on focus/change; debounce 350 ms; reset local pagination on query/filter changes. Pass `{...filters, q:query}` only to local catalog reads, using the existing per-kind allowlist. Local empty queries may list the first catalog page, while external lookup requires two Unicode characters. Load additional local pages through a labeled Load more local results button; preserve previous rows only for the identical query/filters/session. Never use pagination links returned by the server.

Only `kind==='artists'` maps to provider `artist`, and `kind==='songs'` to `work`. Tours, Events, and Venues never call the provider client. Search failures in one group must not clear valid results from the other. Display known `MusicBrainzApiError` messages with a retry button and retain Create manually. Do not automatically retry busy/upstream failures. A 401 invokes the current access object's unauthorized callback only when the request is still current. Cancel both reads on close, kind/query change, disabled access, session change, and unmount.

Keep input focus while Arrow keys update active-descendant. Use `useId()` to connect label/input/listbox/group headings, `aria-controls`, `aria-expanded`, and `aria-activedescendant`; use options with `aria-selected`. Render group headings and descriptions from allowlisted fields. Local and draft options show their source; provider Artists show type, area/country, and disambiguation together. Provider Works show title, disambiguation, and any composer/lyricist names. For a candidate with `local !== null`, display its saved local label and an Existing record marker; the caller reuses that local UUID and facts. Hide an external duplicate if the same local UUID is already in the visible local results.

Use `onMouseDown` to retain input focus while clicking an option and let the click emit its exact option. Pointer and keyboard paths must use the same `onPick` handler. Escape must stop propagation when it closes results, leaving the drawer open. Handle `event.nativeEvent.isComposing` and composition state so IME Enter cannot pick or submit. The manual creation action remains available as a normal `type="button"` even if both searches fail.

- [ ] **Step 4: Implement the inline new-entity fields with explicit confirmation.**

Add these pure helpers to `concertWizard.ts`:

```ts
export function changeArtistInput(input: ArtistDraftInput, patch: Partial<ArtistDraftInput>): ArtistDraftInput {
  return { ...input, ...patch,
    ...(patch.name !== undefined && patch.name !== input.name ? { selection: null } : {}) }
}
export function changeSongInput(input: SongDraftInput, patch: Partial<SongDraftInput>): SongDraftInput {
  return { ...input, ...patch,
    ...(patch.title !== undefined && patch.title !== input.title ? { selection: null } : {}) }
}
```

`ArtistDraftFields` contains Name (required, 255), Type (optional text, 255), Disambiguation (optional text, 255), a source hint, Use artist, and Cancel. `SongDraftFields` contains Title (required, 255), Disambiguation (optional text, 255), a source hint, Use song, and Cancel. Confirm normalizes whitespace at the edges, converts empty optional fields to null, and calls `onConfirm` only for valid input; the opaque proof remains attached unless the identity label changed. Editing metadata must not mutate the `initial` object. Source hint copy is either MusicBrainz match selected or Manual entry. Do not imply data has already been saved.

Implement the confirm predicate and payload directly:

```ts
const valid = input.name.trim().length > 0 && input.name.length <= 255
const confirm = () => {
  if (!valid) return
  onConfirm({ ...input, name: input.name.trim(), type: input.type?.trim() || null,
    disambiguation: input.disambiguation?.trim() || null })
}
```

The Song variant uses `title` and no `type`. Its Use song button applies a pending Song to the row, not a performer. Create a stable `crypto.randomUUID()` draft key only when the user confirms a new entity; when editing an already pending entity, retain its key.

- [ ] **Step 5: Verify markup, reducer behavior, and preserved catalog controls.**

```bash
node --test tests/autocomplete.test.mjs tests/autocomplete-ui.test.mjs tests/catalog-picker.test.mjs tests/concert-wizard.test.mjs
npm run lint
npm run build
git diff --check
```

SSR verifies rendered contracts only. Keyboard focus, IME, outside-click behavior, screen-reader announcements, stale asynchronous groups, and source-list scrolling require the browser checks in Task 6. Do not describe SSR success as interactive acceptance.

- [ ] **Step 6: Commit the reusable select/creation controls if authorized.**

```bash
git add frontend/src/autocomplete.ts frontend/src/concertWizard.ts frontend/src/components/EntityAutocomplete.tsx frontend/src/components/ArtistDraftFields.tsx frontend/src/components/SongDraftFields.tsx frontend/src/catalog.css frontend/tests/autocomplete.test.mjs frontend/tests/autocomplete-ui.test.mjs
git commit -m "feat: add accessible catalog and MusicBrainz autocomplete"
```

## Task 5: Four-step creation drawer and staged setlist editor

**Files:** Create `frontend/src/components/ConcertWizard.tsx`, `WizardArtistStep.tsx`, `WizardTourStep.tsx`, `WizardEventStep.tsx`, `WizardSetlistStep.tsx`, `SetlistEntryFields.tsx`, `frontend/src/wizard.css`, `frontend/tests/concert-wizard-ui.test.mjs`; modify `frontend/src/components/SetlistEditor.tsx`. Keep `ConcertForm.tsx` available for existing-record editing.

**Interfaces:**
- Consumes: Tasks 3–4 pure draft/types/selects, `Dialog`, `Icon`, existing catalog Event detail loading, and existing draft/Venue helpers.
- Produces: `ConcertWizard` props `{venues: Venue[]; initialDate?: string; catalog: CatalogAccess; musicbrainz: MusicBrainzAccess; busy?: boolean; disabled?: boolean; onSignIn?: () => void; onClose: () => void; onSave: (concert: Concert, venue?: Venue, pendingCatalog?: PendingCatalog) => Promise<void>}`. Each step receives `{state: WizardState; onChange: (state: WizardState) => void; onEditingChange: (editing: boolean) => void; catalog; musicbrainz; venues}` with the existing concrete access/Venue types. `SetlistEntryFields` receives `{entry: SetlistEntry; onChange: (patch: Partial<SetlistEntry>) => void; songLookup?: ReactNode; performerLookup?: ReactNode}` and returns fields only.

- [ ] **Step 1: Add failing markup and staged-state tests.**

Follow the existing Vite SSR setup, importing `ConcertWizard`, the step components, and existing `SetlistEditor`. Use disabled fake clients that throw on accidental SSR fetch. Assert:

```js
test('creation has four named steps, a single form, and no early save', () => {
  const html = render(ConcertWizard, { venues: [], catalog, musicbrainz,
    onClose: noop, onSave: async () => {} })
  for (const name of ['Artist', 'Tour', 'Event', 'Setlist']) assert.ok(html.includes(name))
  assert.match(html, /aria-current="step"/)
  assert.equal((html.match(/<form\b/g) ?? []).length, 1)
  assert.match(html, /Next/)
  assert.doesNotMatch(html, /type="submit"[^>]*>Save concert/)
})

test('existing setlist editor retains one form after extracting shared fields', () => {
  const html = render(SetlistEditor, { catalog, entry: { id: id(1), type: 'song',
    title: 'Canción', artist: '', tape: false }, onSave: async () => true, onCancel: noop })
  assert.equal((html.match(/<form\b/g) ?? []).length, 1)
  assert.match(html, /Song title/); assert.match(html, /Save entry/)
})
```

Define `render` using `renderToStaticMarkup(createElement(component, props))`, `noop`, `catalog`, and `musicbrainz` in this test file; import `id` from the existing catalog fixtures. Add direct SSR tests of each step for Skip tour, Event type, all date precisions, local Venue/manual creation, Add song/Add note, text-only row creation, and repeated-entry move controls. Use a state with `step:'setlist'` when rendering the step directly; the outer wizard starts at Artist.

- [ ] **Step 2: Establish the missing UI fails before implementation.**

```bash
node --test tests/concert-wizard-ui.test.mjs tests/catalog-ui.test.mjs
```

Expected: missing new components fail; existing edit/setlist UI remains green before extraction.

- [ ] **Step 3: Implement the wizard shell and Artist/Tour steps.**

The wizard owns `useState(() => createWizard(initialDate))`, errors, `editingChild`, and step focus. Use one `Dialog` drawer and one outer form; all intermediate controls use `type="button"`. Show a four-item ordered step indicator with `aria-current="step"`; completed earlier steps may be revisited. Move focus to the current step heading (`tabIndex={-1}`) after navigation, and focus the first invalid field on failed Next. Provide Back, Next, Cancel, and only on Setlist Save concert. Each step calls `onEditingChange` while an inline entity/row editor or unapplied Event preview is open and clears it on confirmation/cancel/unmount. Keep step navigation and Save disabled while a child editor has unconfirmed fields, so partially typed Artist/Song data cannot disappear on navigation. Cancel the child first to leave it without applying.

The submit path must follow this exact lifecycle:

```ts
const submit = async (event: FormEvent<HTMLFormElement>) => {
  event.preventDefault()
  if (busy || disabled || editingChild || state.step !== 'setlist') return
  try {
    const result = finishWizard(state)
    await onSave(result.concert, result.venue, result.pendingCatalog)
  } catch (failure) {
    setError(failure instanceof Error ? failure.message : 'Check the draft fields.')
  }
}
```

In Artist, local choices add `{name:row.name,catalogId:row.id}`. Provider candidates with a `local` summary add the saved local label/UUID directly. Other provider candidates open `ArtistDraftFields` with `{name:label,type:artistType,disambiguation,selection}`. Create opens manual fields. Confirm creates `{name:input.name,catalogId:null,draft:{key,input,mbid}}`, where `mbid` is present only for an unchanged selected provider identity. Keep this optional ID only in transient `PendingArtist` for UI duplicate detection; final identity authority is the proof. Use key/UUID/selected MBID for duplicate prevention, never label equality. Show chips with individually named remove controls, including duplicate-name distinct Artists.

If an Event is selected and the user attempts to alter the lineup or Tours, show an inline decision with Create new Event using these details and Keep selected Event. Apply the intended change only after detachment is confirmed; declining preserves the entire selected Event state. This decision is about editing the user's draft, not a new permission workflow.

In Tour, reuse the combined select's local-only mode. A local result adds its UUID/name; Create opens an inline name field and Use tour adds `{name:trimmedName,catalogId:null}`. Keep 0–100 Tours; use the existing `artist_uuid` filter only for a persisted Artist when the user selects that narrowing option. Include an unfiltered search and Skip tour. Do not create Tour/Artist relationships automatically.

- [ ] **Step 4: Implement Event lookup/adoption and manual fields.**

`WizardEventStep` uses the local-only Event select. A selected search result triggers `catalog.client.event(id, signal)` through its own `createLatestRead()` instance and renders a preview with title, lineup, Tours, date, Venue, time, and timezone. Before Use these details, show: This replaces the Artists and Tours chosen above. Your notes and setlist stay in the draft. Do not adopt the shallow search row or apply anything on highlight. Apply only the hydrated preview via `adoptWizardEvent()`.

Cancel preview returns to current Event choices without draft mutation. Stale/failed hydration cannot publish an Event, and a stale 401 cannot expire a newer session. Existing selected Event shared fields are read-only until Create a new Event using these details invokes `detachWizardEvent()`.

In new-Event mode render these existing field behaviors, using `createConcertDraft`, `adoptCatalogVenue`, and `finishConcertDraft`:

| Field | Exact behavior |
|---|---|
| Title | Max 255; optional while typing; final title derives from Artists if empty; required if there are none |
| Event type | `concert`, `festival`, `theatre`, `comedy`, `dance`, `other`; default concert |
| Venue | Combined local select; create fields for name/city and optional paired coordinates; nullable Venue accepted |
| Date precision | Exact, month, year, unknown; changing precision does not invent missing components |
| Local time | Optional `type="time" step="1"`; preserve midnight and seconds |
| Timezone | Optional IANA text; retain selected Venue/Event timezone without converting dates |
| Personal fields | Interested, tickets bought, notes; these remain editable with an existing Event |

Use the current `.concert-form`, `.form-columns`, `.flag-fields`, and Venue field styling. New `wizard.css` supplies the step indicator and narrow-width layout only. Do not introduce another visual theme or copy the entire existing editor into App.

- [ ] **Step 5: Extract shared entry fields and implement a draft-only Setlist step.**

Move only the editable field markup from `SetlistEditor` into `SetlistEntryFields`. Keep its existing submit handler, local catalog picker callbacks, messages, and immediate post-creation save contract unchanged. Pass the old local pickers through `songLookup` and `performerLookup`; render those slots only for songs. The extracted component contains no `<form>`, Save button, async operation, or persistence state.

`WizardSetlistStep` owns only its currently edited `WizardEntry`; the ordered list lives in `state.draft.concert.setlist`. Add note initializes `{id:crypto.randomUUID(),type:'note',title:'',artist:'',tape:false}`. Add song initializes an empty song row; it may default the displayed performer to the first chosen Artist only while retaining that Artist's exact local/draft identity. Never infer the performer from a Work result.

Supply combined Song/performer selects to `SetlistEntryFields`. Performer options include matching lineup draft Artists before local/provider results. A local or provider-matched-local Song sets `songCatalogId` and the saved title. A new provider candidate opens `SongDraftFields`; explicit manual Create Song uses the same fields with null selection. Confirm creates `songDraft:{key,input}`. Use text only clears `songCatalogId` and `songDraft`, retaining the title. Performer creation uses `ArtistDraftFields` and `performerDraft`.

Add/Update row validates the row and updates wizard state only:

```ts
const rows = state.draft.concert.setlist
const nextRows = rows.some(row => row.id === editing.id)
  ? rows.map(row => row.id === editing.id ? editing : row)
  : [...rows, editing]
onChange({ ...state, draft: { ...state.draft,
  concert: { ...state.draft.concert, setlist: nextRows } } })
setEditing(null)
```

Reorder uses existing `moveEntry` without changing entry IDs. Remove only removes the chosen entry. Repeated Song identities are valid. Note conversion clears identities/tape through `changeWizardEntry`. Show Unsaved draft until final confirmation. An empty setlist can be saved. Place a compact review summary and Save concert at the bottom without adding a fifth step.

- [ ] **Step 6: Run preserved edit/UI and draft checks.**

```bash
node --test tests/concert-wizard-ui.test.mjs tests/catalog-ui.test.mjs tests/concert-wizard.test.mjs tests/catalog-draft.test.mjs
npm run lint
npm run build
git diff --check
```

Expected: existing ConcertForm/SetlistEditor behavior remains covered; creation markup contains one form and the staged-state tests pass. Manually check the pending-child-editor navigation guard and that Escape first closes an autocomplete, then follows the drawer close behavior. Browser proof is recorded in Task 6.

- [ ] **Step 7: Commit the complete but not-yet-App-wired wizard UI if authorized.**

```bash
git add frontend/src/components/ConcertWizard.tsx frontend/src/components/WizardArtistStep.tsx frontend/src/components/WizardTourStep.tsx frontend/src/components/WizardEventStep.tsx frontend/src/components/WizardSetlistStep.tsx frontend/src/components/SetlistEntryFields.tsx frontend/src/components/SetlistEditor.tsx frontend/src/wizard.css frontend/tests/concert-wizard-ui.test.mjs
git commit -m "feat: build four-step concert creation wizard"
```

## Task 6: Wire final save, prove the complete flow, and document acceptance

**Files:** Modify `frontend/src/App.tsx`, `frontend/src/collectionApi.ts`, `frontend/tests/collection-api.test.mjs`, `frontend/README.md`, `README.md`; create `backend/tests/Feature/MusicBrainzConcurrencyTest.php`, `backend/tests/Feature/musicbrainz-race-worker.php`, and `docs/verification/2026-10-25-concert-wizard-musicbrainz.md`.

**Interfaces:**
- Consumes: the completed wizard and optional `PendingCatalog`, existing collection/session machinery, all server contracts.
- Produces: creation opens the wizard; existing-record editing retains `ConcertForm`; `collectionClient.save(concert, venue?, exists?, pendingCatalog?)` submits optional pending metadata; a confirmed server Workspace remains the only published saved state. Acceptance evidence records actual checks and material limits.

- [ ] **Step 1: Add a failing collection-client test for the sidecar payload.**

In `frontend/tests/collection-api.test.mjs`, import `emptyCollection` and `createCollectionClient` from `collectionApi.ts`, and add:

```js
test('pending catalog drafts travel once beside the concert, never in the saved workspace', async () => {
  const calls = []
  const concert = { id: '00000000-0000-4000-8000-000000000001', title: 'Draft',
    artists: [], venueId: null, date: { year: null, month: null, day: null },
    time: '', timezone: null, interested: true, ticketsBought: false, notes: '',
    catalog: { artistIds: [], tours: [] }, setlist: [] }
  const pending = { artists: {}, songs: {}, lineup: {}, entries: {} }
  const client = createCollectionClient('https://api.spectacles.test', async (url, init) => {
    calls.push({ url, init })
    if (url.endsWith('/sanctum/csrf-cookie')) return new Response(null, { status: 204 })
    return new Response(JSON.stringify({ data: emptyCollection() }), {
      headers: { 'Content-Type': 'application/json' } })
  }, () => 'XSRF-TOKEN=token')
  const saved = await client.save(concert, undefined, false, pending)
  const body = JSON.parse(calls[1].init.body)
  assert.deepEqual(body.pendingCatalog, pending)
  assert.equal(body.concert.pendingCatalog, undefined)
  assert.deepEqual(saved, emptyCollection())
  assert.equal(calls.length, 2)
})
```

Keep existing protocol tests for CSRF, no automatic mutation replay, timeout, malformed response, 401/419, and delayed ownership changes. Add a second invocation omitting pending metadata and assert the body has no `pendingCatalog` property.

- [ ] **Step 2: Confirm the new sidecar test fails, then wire the API/App changes.**

```bash
node --test tests/collection-api.test.mjs
```

Extend the current client without changing existing callers:

```ts
save: (concert: Concert, venue?: Venue, exists = false, pendingCatalog?: PendingCatalog) =>
  send(exists ? `attendances/${encodeURIComponent(concert.id)}` : 'attendances',
    exists ? 'PATCH' : 'POST', {
      concert, ...(venue ? { venue } : {}),
      ...(pendingCatalog ? { pendingCatalog } : {}),
    }),
```

Make `PendingCatalog` a type-only import from `concertWizard.ts`. In `App.tsx` create the provider client with `useMemo`, using the same first-party API base URL. Its access object must use the same `enabled`, session identity, and `expireCatalogSession` callback as local catalog reads. Do not put proofs into `workspace`, account state, toast/Undo data, or browser storage.

Extend `commit(data, message, undoable=false, pendingCatalog?)` and pass the fourth argument only to its single changed Concert's `collectionClient.save`. Extend `saveConcert(concert, venue?, pendingCatalog?)` and forward the sidecar to `commit` as its fourth argument; preserve current lock, response ownership check, error handling, and close-only-on-success behavior. Existing SetlistsView and ConcertForm callers pass no sidecar and remain valid.

Render creation and editing separately inside the existing form/session boundary:

```tsx
{form && (form.concert
  ? <ConcertForm concert={form.concert} venues={workspace.venues} catalog={catalog}
      busy={saving} disabled={saving || !owner} onClose={closeForm} onSave={saveConcert}
      onSignIn={!owner ? () => changeEntry('login') : undefined} />
  : <ConcertWizard venues={workspace.venues} initialDate={form.date}
      catalog={catalog} musicbrainz={musicbrainz} busy={saving}
      disabled={saving || !owner} onClose={closeForm} onSave={saveConcert}
      onSignIn={!owner ? () => changeEntry('login') : undefined} />)}
```

Declare `closeForm = () => { if (!saving) setForm(null) }` beside the existing handlers. Keep the wizard mounted during same-account reauthentication exactly as the current editor is preserved. A different-account login clears it under the current account-switch policy. Guard final Save against double clicks with the existing synchronous `locked.current` and disabled fieldset. During a save, close/Escape/outside-click must leave the form open. On 422, show the collection client's safe error and retain inputs; do not silently convert an invalid proof to manual. On an ambiguous timeout, preserve the Attendance UUID and require reconciliation, not an automatic POST replay.

- [ ] **Step 3: Add an actual PostgreSQL race regression without a parent test transaction.**

`MusicBrainzConcurrencyTest` must not use `RefreshDatabase`, because its workers need committed shared state. Skip the test on SQLite or missing `proc_open`; on PostgreSQL require the explicitly dedicated `spectacles_testing` database before starting workers. Migrate only that dedicated database before this check. Use a random MBID and scoped unique name; clean only those test records and temporary files in `finally`.

The worker file boots Laravel, installs a bounded file barrier after Artist parent creation, and calls the real resolver inside a real outer transaction:

```php
<?php

use App\Http\CatalogDraftResolver;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDriverName() !== 'pgsql'
    || DB::connection()->getDatabaseName() !== 'spectacles_testing') {
    exit(2);
}
Http::preventStrayRequests();
[$script, $directory, $slot] = $argv;
$input = json_decode(file_get_contents($directory.'/input.json'), true, 512, JSON_THROW_ON_ERROR);
[$model, $labelField] = $input['kind'] === 'artist' ? [Artist::class, 'name'] : [Song::class, 'title'];
$model::created(function (Artist|Song $parent) use ($directory, $slot, $input, $labelField): void {
    if ($parent->{$labelField} !== $input['label']) {
        return;
    }
    touch($directory.'/'.$slot.'.ready');
    $deadline = microtime(true) + 5;
    while (count(glob($directory.'/*.ready')) < 2) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Race barrier timed out.');
        }
        usleep(10000);
    }
});
$saved = DB::transaction(fn (): array => app(CatalogDraftResolver::class)
    ->resolve($input['concert'], $input['pendingCatalog']));
fwrite(STDOUT, $input['kind'] === 'artist'
    ? $saved['catalog']['artistIds'][0] : $saved['setlist'][0]['songCatalogId']);
```

Correct the boot paths for this file's actual depth: from `tests/Feature/`, both vendor and bootstrap are `../../vendor` and `../../bootstrap`, as above. The file is a worker fixture, has no `Test.php` suffix, and is not a PHPUnit test itself.

In the parent test, run the following input construction for each `$kind` in `['artist', 'work']`. Import MusicBrainzSelection, ArtistProviderId, SongProviderId, Str, DB, Artist, and Song. Write the payload to a private directory under `sys_get_temp_dir()`:

```php
$mbid = (string) Str::uuid();
$key = (string) Str::uuid();
$entryId = (string) Str::uuid();
$name = 'Race '.$kind.' '.$mbid;
$proof = app(MusicBrainzSelection::class)->issue($kind, $mbid);
$concert = ['artists' => [], 'catalog' => ['artistIds' => [], 'tours' => []], 'setlist' => []];
$pending = ['artists' => [], 'songs' => [], 'lineup' => [], 'entries' => []];
if ($kind === 'artist') {
    $concert['artists'] = [$name];
    $concert['catalog']['artistIds'] = [null];
    $pending['artists'][$key] = ['name' => $name, 'type' => null,
        'disambiguation' => null, 'selection' => $proof];
    $pending['lineup']['0'] = $key;
} else {
    $concert['setlist'] = [['id' => $entryId, 'type' => 'song', 'title' => $name,
        'artist' => '', 'tape' => false]];
    $pending['songs'][$key] = ['title' => $name, 'disambiguation' => null, 'selection' => $proof];
    $pending['entries'][$entryId] = ['song' => $key];
}
[$referenceModel, $parentModel, $labelField] = $kind === 'artist'
    ? [ArtistProviderId::class, Artist::class, 'name']
    : [SongProviderId::class, Song::class, 'title'];
$initialReferenceCount = $referenceModel::query()->count();
$directory = sys_get_temp_dir().'/spectacles-mb-race-'.Str::uuid();
mkdir($directory, 0700);
file_put_contents($directory.'/input.json', json_encode([
    'kind' => $kind, 'label' => $name, 'concert' => $concert, 'pendingCatalog' => $pending,
], JSON_THROW_ON_ERROR));
```

Launch both workers before reading either output:

```php
$processes = [];
foreach (['a', 'b'] as $slot) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __DIR__.'/musicbrainz-race-worker.php', $directory, $slot],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
    $this->assertIsResource($process);
    fclose($pipes[0]);
    $processes[] = [$process, $pipes];
}
$ids = [];
foreach ($processes as [$process, $pipes]) {
    $ids[] = trim(stream_get_contents($pipes[1]));
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $this->assertSame(0, proc_close($process), $error);
}
$this->assertSame($ids[0], $ids[1]);
$this->assertSame(1, $parentModel::query()->where($labelField, $name)->count());
$this->assertSame($initialReferenceCount + 1, $referenceModel::query()->count());
```

Pass the dedicated test DB connection and APP_KEY to workers through inherited/explicit process environment, using `config('app.key')` only as an environment value, never printing it. Ensure the worker's connection matches the parent; Laravel's PHPUnit-only overrides are not automatically present in a plain PHP subprocess. Bound each stdout/stderr stream with a ten-second read timeout using `stream_set_timeout`, and terminate/close both workers in `finally` on failure. Require both ready markers before accepting the result: this proves both workers reached parent creation, not merely sequential reuse. Clean up only the random-MBID reference, its scoped Artist/Song, and this test directory. Both resource cases use the same mapped worker and assertions. The existing DB unique indexes remain enabled.

- [ ] **Step 4: Run the focused integration and complete project checks.**

From `frontend/`:

```bash
npm run lint
npm run build
npm test
```

From `backend/`, using the current machine's PHP prefix:

```bash
php artisan test --compact tests/Feature/MusicBrainzLookupTest.php tests/Feature/MusicBrainzSaveTest.php tests/Feature/CollectionEndpointsTest.php tests/Feature/ProviderReferencesTest.php
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

Before PostgreSQL checks, confirm `spectacles_testing` is an isolated disposable test database and resolve its host/port/user from the current environment. Do not create accounts, print secrets, or run migrations against the app database. With that connection configured, explicitly override any inherited DB_URL:

```bash
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=spectacles_testing DB_URL= php artisan migrate --no-interaction
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=spectacles_testing DB_URL= php artisan test --compact tests/Feature/MusicBrainzSaveTest.php tests/Feature/MusicBrainzConcurrencyTest.php tests/Feature/CollectionEndpointsTest.php tests/Feature/ProviderReferencesTest.php
```

A skipped concurrency test is not a passing concurrency claim. Report unavailable PostgreSQL/process support as a remaining verification limit. Do not broaden/repeat successful checks unless code changes, failures, or unresolved concerns justify it. Rerun the narrow affected checks after formatting edits.

- [ ] **Step 5: Verify user-visible behavior with isolated fake provider responses.**

Use the documented local Yerd/Vite setup at `https://spectacles.test` and `https://api.spectacles.test`, after confirming the hosts still belong to this checkout. Use disposable QA accounts/records and deterministic fake provider responses through the test/browser harness; assert stray upstream requests are blocked. Do not globally redirect the application to a fake backend for ordinary users. If the available browser tool cannot intercept requests, add test-only fixtures through the existing test HTTP setup or record the limitation; never add a production debug bypass. Verify this exact matrix:

| Scenario | Expected visible and persisted result |
|---|---|
| Manual path through four steps | Create Artists, optional Tour, Event/Venue, explicit manual Song; final save/reload shows the same facts |
| No Artist, no Tour, unknown date, no setlist | Nonmusic Event saves with its supplied title and no invented lineup/date |
| Same-name local Artists, Zoé/Los Bunkers candidates, country absent | User can distinguish candidates; source/type/context visible; wrong first result never auto-selected |
| Eres Work candidates with another composer first | User can decline; performer remains unchanged; text-only fallback saves with no Work reference |
| Same Artist chosen for lineup and several performers | One local Artist reused after final save; every intended row points to it |
| Repeated Song, encore note, tape, edit/remove/reorder | Ordered rows survive save/reload; notes have no Song identity or tape flag |
| Existing Event preview/adopt | Earlier Artist/Tour replacement explained; notes/setlist retained; no orphan pending entities submitted |
| Back-edit Artist after Event adoption | Explicit detach choice; declining keeps selected Event; accepting creates new Event facts on save |
| Latitude/longitude zero, partial date, midnight seconds, unknown timezone | Exact supported values survive final save/reload |
| Add/Update row, Next, Back, cancel a child editor, cancel wizard | No domain writes before final Save; drafts persist across navigation; cancelling wizard leaves counts unchanged |
| Provider disabled, 503, 429, malformed response, local success | Local options and manual creation remain usable; failure is distinguished from no results |
| Query A slow, B fast; close/reopen; account changes during lookup | Only current results appear; no stale choice or stale unauthorized callback |
| Keyboard, IME, narrow viewport, long labels | Arrow/Enter/Escape work, composing text isn't picked, input focus remains usable, no clipped controls |
| Double click, final 422/503/timeout, expired proof | One pending mutation; draft remains; no automatic replay; explicit reselect/manual recovery available |
| Expired session, same account login, other account login | Same-owner draft preserved; another account cannot receive it or its saved collection |
| Existing Edit concert and existing saved setlist editor | Prior editing, member permissions, flags, archive/Undo, and immediate per-entry saves still work |

For cancellation-without-writes and pending-row behavior, inspect the first-party network requests and before/after domain row counts in the disposable QA database. No catalog-create/attendance mutation should occur until final Save. Do not count provider cache writes as domain records.

After deterministic offline acceptance, perform at most two explicitly identified sequential live MusicBrainz searches, at least two seconds apart, using the configured real identifying User-Agent, to verify incremental prefix behavior and response shape. Use one Artist prefix and one Work prefix from the research cases. Do not treat this as a coverage or availability benchmark. If access is busy, retain offline proof and record live prefix verification as unavailable rather than retrying in a loop.

- [ ] **Step 6: Write usage/configuration notes and acceptance evidence, then review scope.**

Update `frontend/README.md` to describe creation versus editing, four draft steps, local/provider source labels, manual/text-only fallbacks, one final save, and the unchanged post-creation setlist editor. Remove its obsolete assertion that no external provider lookup is connected. Update root `README.md` with the two MusicBrainz environment variables, real User-Agent contact requirement, shared durable cache requirement, no API key, and existing local start commands. Do not claim activation, deployment, or live verification unless performed.

Create the verification record with date, revision, environments, commands/results, browser scenarios, concurrency outcome, live prefix outcome, and remaining limits. Record only actual observations. Do not check off skipped scenarios or include credentials, cookies, selection proofs, provider payload dumps, or personal attendance content. Link this plan and its design.

Review the final diff for unrelated edits, duplicate controls/resolvers, missing declarations, schema/package changes, private data exposure, nested forms, automatic saves/replays, and old assertions that no provider is connected:

```bash
git diff --check
git diff --stat
git status --short
rg -n 'MusicBrainz|pendingCatalog|ConcertWizard|no external provider' frontend/README.md README.md frontend/src backend/app backend/routes
```

Do not rename or delete the existing research, plans, unrelated files, test databases, or user data.

- [ ] **Step 7: Commit final integration and verified documentation if authorized.**

```bash
git add frontend/src/App.tsx frontend/src/collectionApi.ts frontend/tests/collection-api.test.mjs backend/tests/Feature/MusicBrainzConcurrencyTest.php backend/tests/Feature/musicbrainz-race-worker.php frontend/README.md README.md docs/verification/2026-10-25-concert-wizard-musicbrainz.md
git commit -m "feat: connect concert creation wizard to atomic account saves"
```

Report the changed paths, actual test/browser/concurrency results, and remaining limits. Distinguish local changes, commits, pushes, PRs, merges, and deployments. Open PRs to both `dev` and `main` only when delivery is authorized; do not merge/rebase `dev` into the feature branch.

## Plan self-review and handoff

| Design requirement | Owning work and proof |
|---|---|
| Four steps and existing visual reference | Tasks 4–5; SSR structure plus Task 6 browser/responsive matrix |
| Local/manual creation in each step; optional data | Tasks 2–5; manual versus text-only test, empty-step/date tests, browser save/reload |
| MusicBrainz Artist/Work identity and disambiguation | Tasks 1, 3–4; normalization/client tests, explicit-selection/performer tests |
| One draft, Back/cancel/failure preservation | Tasks 3, 5–6; transition tests and network/domain-count browser checks |
| Existing Event adoption and explicit detachment | Tasks 3 and 5; pending pruning test, preview and Back-edit browser scenarios |
| Stable local UUIDs and atomic provider reuse | Task 2 and Task 6; save/rollback tests, both PostgreSQL race cases |
| Pacing/cache/no-stray-HTTP/failure fallback | Tasks 1 and 4; shared-gate/cache/error tests plus offline browser cases |
| Existing edit, setlist behavior, auth/privacy | Tasks 2, 5–6; existing suites and same/different-account browser checks |
| No provider expansion, dependencies, migration, or sync | Global constraints and Task 6 final diff review |

Planning self-review completed: the shared types/routes/fields match their defining tasks; child-map rules accept empty maps while preserving old clients; savepoints cover parent and reference creation; the provider response carries authoritative local summaries; and pending proofs travel outside the durable Workspace. The file map includes all six tasks' files. Relative document links, matching global constraints, Markdown fences, unresolved-marker scans, and documentation whitespace were checked. Every Review Focus item maps to automated tests and browser checks where automation cannot prove acceptance. No product tests or browser acceptance were run during planning; implementation is not complete until Task 6 reports its actual results.

No product decision remains open. Runtime connection/contact configuration and optional isolated-worktree choice are execution prerequisites, not reasons to change this design. Stop after delivering this plan unless the user separately authorizes implementation.
