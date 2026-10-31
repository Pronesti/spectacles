# Shared Catalog Read Endpoints Implementation Plan

> **Execution:** Follow the user-selected execution method and repository requirements. This plan does not require any additional skill. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add six authenticated read endpoints for selecting existing Events, Artists, Venues, Songs, and Tours, without exposing Attendance or companion data.

**Architecture:** Reuse the existing catalog models, public UUID binding, factories, and declarative Laravel JSON:API resources. One catalog controller handles five small index actions and Event detail, while one FormRequest validates the bounded query contract and safe include paths before the resources can traverse relationships. No schema, dependency, provider integration, or frontend change is required.

**Tech Stack:** Laravel 13.38.0, Sanctum 4.3.3, PHP 8.5 project runtime, PHPUnit 12.5.40, Pint 1.32.1; existing SQLite and PostgreSQL support. Versions were checked in `backend/composer.lock` during planning.

**Spec:** `docs/superpowers/specs/2026-10-22-live-events-database-design.md`; the user's six endpoint descriptions and explicit decision that all six require a signed-in Sanctum session. `frontend/design/visual-spec.md` and `frontend/src/components/ConcertForm.tsx` explain the existing selection use cases; frontend integration is outside this plan.

## Global Constraints

- “The model separates shared Event facts from an Attendance containing personal or collaboratively maintained details.” These endpoints return shared catalog facts only, including when the current user owns an Attendance.
- “Attendance is private by default.” No Attendance identifiers, counts, notes, members, invitations, seats, setlists, links, or companion relationships appear in these responses.
- “Used to identify the record in application-facing URLs and API references.” Use `external_id` UUIDs through existing `HasPublicId` and `PublicJsonApiResource`; never return internal foreign keys.
- “Names are nonempty display values, not unique keys.” Do not merge or deduplicate records by title/name, venue/date, or lineup.
- “Catalog names and user content retain their original language and spelling; interface localization does not translate them.” Preserve Unicode content; do not transliterate, rename, or normalize stored text while searching.
- “No substitute January 1 date is generated for a year-only entry.” Preserve null date components, local midnight, and unknown time/timezone. No guessed instant or UTC conversion.
- “Tours are optional.” Default Event results include Venue and ordered lineup. Tours are explicitly includable. Tour search includes participating Artists, independently of Event lineup.
- “Every field and setlist entry remains manually enterable.” Catalog reads search existing local records; they do not require provider identities, fetch suggestions, import, synchronize, or create missing records.
- “This selection does not assert a new provider permission.” Stored provider tuples may narrow local lookup. Do not expose optional provider/playable relationships in this release; no established redisplay policy or permission metadata exists.
- The user chose **require sign-in** for all six routes. Use existing `auth:sanctum`, cookie sessions, `/api/v1`, and JSON responses. Do not change authentication or the public `/api/health` endpoint.
- Keep existing resources declarative. Enforce this endpoint's traversal boundary before serialization, with exact include validation and explicit eager loading. Do not restore generic resource access/cloning infrastructure.
- Use the existing PHPUnit/factory conventions. Before any PHP/Composer execution, reread `~/.config/agent-skills/runtime.md` and apply that machine's prefixes. During planning it specifies plain `php` and `composer` commands.
- This document is the only authorized product artifact for this request. Do not implement it, change environment/runtime configuration, migrate shared data, commit, push, create a PR/worktree, or deploy as part of planning. Worktree isolation requires the user's approval when execution begins.

## Review Focus

- Nested `include` paths and GET bodies must never reach Attendance, members, owners, private setlist occurrences, or companion relationships. Pin with Task 1's `test_unsafe_requests_are_rejected_before_private_queries` and `test_get_json_bodies_cannot_bypass_the_include_boundary`.
- Nullable/partial dates, leap days, unknown venue/time, midnight, and zero coordinates must remain distinguishable. Pin with Task 1's date-filter and shared-document tests.
- Duplicate names, literal `%`/`_`/`!`, Unicode spelling, combined search filters, and stable page boundaries must not create false identities or lose matches. Pin with Task 1's literal-search, duplicate-name, combined-filter, and pagination tests.
- Incomplete or mismatched provider tuples must not match a different reference row or trigger network calls; a shared multi-Event reference can return multiple Events. Pin with Task 1's provider-filter tests.
- Large result pages and optional nested Tour rosters must not create per-result queries, including through serialization. Pin with Task 1's bounded-query test; guest access remains 401 across every route.

---

## Verified Starting Point and File Map

Planning snapshot: branch `feat/live-event-data-layer`, HEAD `6539281`. `backend/routes/api.php` exposes health and account routes but none of the six catalog reads. Existing models/resources/factories and `CatalogTest`, `CatalogRelationshipsTest`, `ProviderReferencesTest`, `PublicIdentityTest`, and `JsonApiResourcesTest` already cover the data layer. `HasPublicId::getRouteKeyName()` returns `external_id`. `Event::artistLinks()` orders numbered positions first, null positions last, then internal ID for ties.

Preserve existing modifications to `.gitignore`, `README.md`, `frontend/.env.example`, `frontend/vite.config.ts`, and untracked `.env.example`, `docs/`, `frontend/design/`, `qa-output/`. Recheck live Git state before execution; other authorized chats can change this shared checkout. Read applicable global/backend AGENTS/CLAUDE instructions and `.ai/rules/index.md` if it exists. No root/backend `.ai/rules` directory was found during planning.

| File | Responsibility |
| --- | --- |
| Create `backend/app/Http/Requests/CatalogReadRequest.php` | Per-route filter validation, query-shape restrictions, default/safe includes, GET-body rejection. |
| Create `backend/app/Http/Controllers/CatalogController.php` | Five catalog index handlers through one index method, Event show, scoped search/filter queries, safe eager loads, native pagination. |
| Modify `backend/routes/api.php` | Six named GET routes in the existing authenticated v1 group. |
| Create `backend/tests/Feature/CatalogReadEndpointsTest.php` | Real HTTP feature coverage, UUIDs, all filters, privacy, pagination, bounded queries. |
| Modify `README.md` during implementation | Exact query/output/access contract and current exclusions. |

Existing model/resource files remain unchanged. `app/Http/Requests` is Laravel's conventional FormRequest location; generate it with Artisan. No new architectural base folder, query-builder dependency, custom paginator, or generic access layer is needed.

## Endpoint Contract

All paths below start with `/api/v1`. Requests send `Accept: application/json` and the existing Sanctum session cookie. GET requests need no CSRF initialization or token mutation. Success uses native JSON:API `data`, public UUID `id`, plural resource `type`, explicit attributes, and `application/vnd.api+json`. Validation retains Laravel's existing 422 `message`/`errors` structure. Guests receive 401; valid empty searches return 200 with `data: []`. An unknown filter UUID returns an empty collection; invalid UUID syntax is 422. Event detail uses UUID route binding and a UUID route constraint, returning 404 for numeric, malformed, or missing Event identifiers.

| Endpoint | Allowed `filter[...]` keys | Response and relationships |
| --- | --- | --- |
| `GET /events` | `q`, `venue_uuid`, `year`, `month`, `day`, `provider`, `resource_type`, `provider_resource_id` | Event attributes currently declared by `EventResource`: title, type, nullable year/month/day, local_start_time, timezone, timestamps. Default includes `venue,artistLinks.artist`. Optional safe Tour paths below. |
| `GET /events/{event}` | None | Same shared Event document and include options, without collection pagination. |
| `GET /artists` | `q`, `type`, `provider`, `resource_type`, `provider_resource_id` | UUID, name, nullable type/disambiguation, existing timestamps. No relationships. |
| `GET /venues` | `q`, `city`, `country_code` | UUID, name, independently nullable street, house_number, postal_code, city, region, country_code, lat/lng, timezone, timestamps. No relationships. |
| `GET /songs` | `q`, `disambiguation` | UUID, title, nullable disambiguation, timestamps. No relationships. |
| `GET /tours` | `q`, `artist_uuid` | UUID, name, timestamps; default includes `artistLinks.artist`. |

### Search, identity, and dates

- `filter[q]` is optional trimmed literal substring text, at most 200 characters. Event search matches title **or** lineup Artist name. Artist search matches name **or** disambiguation. Venue search matches name. Song search matches title; optional `filter[disambiguation]` searches the available composition description. Tour search matches name. Empty/whitespace-only text is an unfiltered paginated browse.
- `%`, `_`, and `!` are literal text, not caller-controlled SQL wildcards. Use parameter binding and explicit `ESCAPE '!'`. Case behavior uses the database's `LOWER` function: ASCII case differences match; original non-ASCII text is preserved, with no promise of accent-insensitive search, transliteration, or identical Unicode case-folding across engines.
- Different filter keys combine with AND. Text alternatives within one `q` combine with OR inside a grouped predicate, so an Artist match cannot bypass Venue/date/provider filters.
- Provider tuple components and Artist `type` are nonempty strings of at most 255 characters. Include text is at most 500 characters; disambiguation/city text follows the 200-character search bound. UUID references use the canonical values returned by the API.
- Artist `type` is a bounded exact stored string, not a newly invented enum. Venue city is a literal substring; country_code is exactly two ASCII letters, normalized uppercase, then matched exactly. Venue/Artist filters use UUID lookup through their public `external_id`.
- Date filters match the supplied stored components exactly. `year=2024` can return year-only, year-month, and exact-date records in 2024. Month requires year; day requires year/month. Validate ranges and full calendar dates, including leap years. Do not interpret partial dates as date ranges or fill missing components. An undated Event appears in unfiltered results but cannot match a non-null year filter.
- Provider filtering is optional on Events/Artists. All three keys must be present together and match **one same row**. The tuple is matched exactly as stored, including case. Events may share a broad source reference, so provider lookup remains a collection; do not choose or merge the first result. A selected known tuple should be looked up alone if the caller wants identity resolution despite conflicting text/date input; this read contract combines supplied filters with AND.
- Songs have no catalog composer/Artist association. Return existing disambiguation as available composition context; do not add an `artist_uuid` filter or derive credits from Attendance setlist performers. Reject unsupported filters instead of pretending they work.
- Stored associations have no permission/confirmation field. Their use as a local lookup key is not new confirmation or permission verification. Exclude Event providerReferences, catalog providerIds, and Song links from all responses/includes in this release. No permission flag, provider HTTP call, redirect, or playable URL is added.

### Pagination and includes

Collection query keys are `filter`, `include`, `page`, and `per_page` only. `page` is an integer 1–100000; `per_page` is 1–100, default 20. Sort by the primary display title/name ascending, then `external_id` ascending for a stable duplicate-name tie break. Preserve filters in native paginator links; no client-defined sort or sparse fieldsets are supported in this initial selector API. Unknown top-level or nested filter keys, arrays in scalar positions, unsupported includes, and nonempty GET bodies return 422 before resource traversal.

Default includes cannot be removed, because selection responses need Venue/lineup or Tour artists. Optional `include` paths are added to the defaults after validation:

| Resource | Allowed caller paths |
| --- | --- |
| Events (list/detail) | `venue`, `artistLinks`, `artistLinks.artist`, `tourLinks`, `tourLinks.tour`, `tourLinks.tour.artistLinks`, `tourLinks.tour.artistLinks.artist` |
| Tours | `artistLinks`, `artistLinks.artist` |
| Artists, Venues, Songs | None; empty/absent include only |

Native resources provide `relationships` linkage plus a top-level `included` array, not recursively nested attribute objects. An Event lineup uses ordered `event_artists` resource objects with position and Artist UUID linkage. Null Venue is `data: null`; no lineup/tours is an empty relationship collection when included. Tour roster order has no semantic meaning. Date precision is represented by the nullable components already in the schema; no redundant precision column or fabricated timestamp is added. Venue coordinate values retain the existing decimal-string resource representation, including `"0.0000000"`.

The privacy boundary is the complete request/include allowlist plus explicit serialization using that validated FormRequest. Default resource relationships remain broad for other endpoints; do not call `toResource()->response()` with an unchecked global request. Call `toResponse($request)` with the validated request, and eager-load its exact safe include paths before serialization. No private relation names, counts, or IDs are accepted as filters.

### Task 1: Implement and Prove the Six Read Routes

**Files:** Create the FormRequest, controller, feature test listed above; modify `backend/routes/api.php` within its existing authenticated v1 group. No model/resource/migration changes.

**Interfaces:** `CatalogReadRequest::catalog(): string` returns the named route's catalog key; `includePaths(): array` returns validated safe/default include paths. `CatalogController::index(CatalogReadRequest): JsonResponse` returns a native paginated catalog collection; `show(CatalogReadRequest, Event): JsonResponse` returns one shared Event. The private `text(Builder,array,string): Builder` groups bound literal substring predicates. Existing model relationships and resource classes supply all data.

- [ ] **Step 1: Generate the conventional files after runtime and branch preflight.**

Read the current runtime file and instructions first. Run from `backend/` only when implementation is authorized:

```sh
php artisan make:request CatalogReadRequest --no-interaction
php artisan make:controller CatalogController --no-interaction
php artisan make:test CatalogReadEndpointsTest --phpunit --no-interaction
```

If any destination exists, inspect/reuse it rather than overwrite it. The existing feature branch already contains direct backend dependencies; verify its ancestry/release scope before following the one-feature-branch-from-main workflow. Do not reset unrelated work or merge/rebase `dev`.

- [ ] **Step 2: Write the complete HTTP regression suite in `backend/tests/Feature/CatalogReadEndpointsTest.php`.**

Use the test file in the next section verbatim as the initial regression contract. These are feature tests through the real route/middleware/request/resource path, not serializer-only tests.

- [ ] **Step 3: Run the new test file and confirm the missing-route failure.**

```sh
php artisan test --compact tests/Feature/CatalogReadEndpointsTest.php
```

Expected before implementation: missing catalog routes produce 404 instead of the required 200/401/422 results. Existing fixtures and DB guards must work; investigate a runtime/database failure before treating it as a valid red test. No existing suite was run during this planning request.

- [ ] **Step 4: Put this complete FormRequest in `backend/app/Http/Requests/CatalogReadRequest.php`.**

```php
<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class CatalogReadRequest extends FormRequest
{
    private const DEFAULT_INCLUDES = [
        'events' => ['venue', 'artistLinks.artist'],
        'tours' => ['artistLinks.artist'],
    ];

    private const ALLOWED_INCLUDES = [
        'events' => ['venue', 'artistLinks', 'artistLinks.artist', 'tourLinks', 'tourLinks.tour', 'tourLinks.tour.artistLinks', 'tourLinks.tour.artistLinks.artist'],
        'tours' => ['artistLinks', 'artistLinks.artist'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function catalog(): string
    {
        return explode('.', (string) $this->route()->getName())[1];
    }

    protected function prepareForValidation(): void
    {
        if ($this->getContent() !== '') {
            throw ValidationException::withMessages(['body' => __('validation.prohibited', ['attribute' => 'body'])]);
        }
        $filters = $this->query('filter');
        if (is_array($filters) && isset($filters['country_code']) && is_string($filters['country_code'])) {
            $filters['country_code'] = strtoupper($filters['country_code']);
            $this->merge(['filter' => $filters]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['include' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }
            $allowed = self::ALLOWED_INCLUDES[$this->catalog()] ?? [];
            if (array_diff(explode(',', $value), $allowed) !== []) {
                $fail(__('validation.in', ['attribute' => $attribute]));
            }
        }]];
        if ($this->routeIs('catalog.events.show')) {
            return $rules;
        }
        $text = ['nullable', 'string', 'max:200'];
        $filters = match ($this->catalog()) {
            'events' => [
                'q' => $text,
                'venue_uuid' => ['sometimes', 'required', 'uuid'],
                'year' => ['sometimes', 'required', 'integer', 'between:1,9999'],
                'month' => ['sometimes', 'required', 'integer', 'between:1,12'],
                'day' => ['sometimes', 'required', 'integer', 'between:1,31'],
            ],
            'artists' => ['q' => $text, 'type' => ['sometimes', 'required', 'string', 'max:255']],
            'venues' => ['q' => $text, 'city' => $text, 'country_code' => ['sometimes', 'required', 'string', 'regex:/^[A-Z]{2}$/']],
            'songs' => ['q' => $text, 'disambiguation' => $text],
            'tours' => ['q' => $text, 'artist_uuid' => ['sometimes', 'required', 'uuid']],
        };
        if (in_array($this->catalog(), ['events', 'artists'], true)) {
            foreach (['provider', 'resource_type', 'provider_resource_id'] as $key) {
                $others = array_diff(['provider', 'resource_type', 'provider_resource_id'], [$key]);
                $filters[$key] = ['required_with:'.implode(',', array_map(fn (string $other): string => 'filter.'.$other, $others)), 'string', 'max:255'];
            }
        }
        $rules['filter'] = ['sometimes', 'array:'.implode(',', array_keys($filters))];
        foreach ($filters as $key => $validation) {
            $rules['filter.'.$key] = $validation;
        }
        $rules['page'] = ['sometimes', 'required', 'integer', 'between:1,100000'];
        $rules['per_page'] = ['sometimes', 'required', 'integer', 'between:1,100'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = $this->routeIs('catalog.events.show') ? ['include'] : ['filter', 'include', 'page', 'per_page'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $key]));
            }
            if ($validator->errors()->isNotEmpty() || $this->catalog() !== 'events') {
                return;
            }
            $filter = $this->input('filter', []);
            if (isset($filter['month']) && ! isset($filter['year'])) {
                $validator->errors()->add('filter.year', __('validation.required', ['attribute' => 'filter.year']));
            }
            if (isset($filter['day']) && (! isset($filter['year']) || ! isset($filter['month']))) {
                $validator->errors()->add('filter.month', __('validation.required', ['attribute' => 'filter.month']));
            }
            if (isset($filter['year'], $filter['month'], $filter['day']) && ! checkdate((int) $filter['month'], (int) $filter['day'], (int) $filter['year'])) {
                $validator->errors()->add('filter.day', __('validation.date', ['attribute' => 'filter.day']));
            }
        });
    }

    /** @return list<string> */
    public function includePaths(): array
    {
        $requested = $this->filled('include') ? explode(',', $this->string('include')->toString()) : [];

        return array_values(array_unique([...(self::DEFAULT_INCLUDES[$this->catalog()] ?? []), ...$requested]));
    }

    protected function passedValidation(): void
    {
        $this->merge(['include' => implode(',', $this->includePaths())]);
    }
}
```

`authorize()` delegates authentication to route middleware; it does not make these routes public. The existing trimming/empty-string middleware handles blank text. Rejecting GET bodies closes an input-source bypass before native JSON:API parsing; unknown `fields`, sort, and private filters are also rejected.

- [ ] **Step 5: Put this controller in `backend/app/Http/Controllers/CatalogController.php`.**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogReadRequest;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\SongResource;
use App\Http\Resources\TourResource;
use App\Http\Resources\VenueResource;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Song;
use App\Models\Tour;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function index(CatalogReadRequest $request): JsonResponse
    {
        [$model, $resource, $display] = match ($request->catalog()) {
            'events' => [Event::class, EventResource::class, 'title'],
            'artists' => [Artist::class, ArtistResource::class, 'name'],
            'venues' => [Venue::class, VenueResource::class, 'name'],
            'songs' => [Song::class, SongResource::class, 'title'],
            'tours' => [Tour::class, TourResource::class, 'name'],
        };
        $query = $model::query();
        $filter = $request->validated('filter', []);
        $term = $filter['q'] ?? null;
        if (is_string($term) && $term !== '') {
            if ($request->catalog() === 'events') {
                $query->where(function (Builder $group) use ($term): void {
                    $this->text($group, ['events.title'], $term);
                    $group->orWhereHas('artistLinks.artist', fn (Builder $artist): Builder => $this->text($artist, ['artists.name'], $term));
                });
            } else {
                $columns = $request->catalog() === 'artists' ? ['artists.name', 'artists.disambiguation'] : [$query->getModel()->getTable().'.'.$display];
                $this->text($query, $columns, $term);
            }
        }
        if ($request->catalog() === 'events') {
            foreach (['year', 'month', 'day'] as $component) {
                if (isset($filter[$component])) {
                    $query->where($component, (int) $filter[$component]);
                }
            }
            if (isset($filter['venue_uuid'])) {
                $query->whereHas('venue', fn (Builder $venue): Builder => $venue->where('external_id', $filter['venue_uuid']));
            }
        }
        if ($request->catalog() === 'artists' && isset($filter['type'])) {
            $query->where('type', $filter['type']);
        }
        if ($request->catalog() === 'venues') {
            if (is_string($filter['city'] ?? null) && $filter['city'] !== '') {
                $this->text($query, ['venues.city'], $filter['city']);
            }
            if (isset($filter['country_code'])) {
                $query->where('country_code', $filter['country_code']);
            }
        }
        if ($request->catalog() === 'songs' && is_string($filter['disambiguation'] ?? null) && $filter['disambiguation'] !== '') {
            $this->text($query, ['songs.disambiguation'], $filter['disambiguation']);
        }
        if ($request->catalog() === 'tours' && isset($filter['artist_uuid'])) {
            $query->whereHas('artistLinks.artist', fn (Builder $artist): Builder => $artist->where('external_id', $filter['artist_uuid']));
        }
        if (in_array($request->catalog(), ['events', 'artists'], true) && isset($filter['provider'])) {
            $relation = $request->catalog() === 'events' ? 'providerReferences' : 'providerIds';
            $query->whereHas($relation, function (Builder $reference) use ($filter): void {
                foreach (['provider', 'resource_type', 'provider_resource_id'] as $component) {
                    $reference->where($component, $filter[$component]);
                }
            });
        }
        $page = $query->with($request->includePaths())
            ->orderBy($display)->orderBy('external_id')
            ->paginate($request->integer('per_page', 20), ['*'], 'page', $request->integer('page', 1))
            ->appends($request->query());

        return $resource::collection($page)->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function show(CatalogReadRequest $request, Event $event): JsonResponse
    {
        $event->load($request->includePaths());

        return (new EventResource($event))->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    /** @param list<string> $columns */
    private function text(Builder $query, array $columns, string $term): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(function (Builder $group) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $group->{$method}("LOWER({$column}) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
            }
        });
    }
}
```

The raw SQL column names come exclusively from controller constants/model tables; request input is bound as a value. `whereHas` uses EXISTS, avoiding duplicate Events when several lineup Artists match. Provider tuple predicates apply to one reference subquery. Pagination caps the root collection, and `.with()` batches every allowed nested path before native serialization. Counts/links refer only to the shared catalog query.

- [ ] **Step 6: Add the controller import and these routes inside the existing `auth:sanctum` v1 group in `backend/routes/api.php`.**

Import:

```php
use App\Http\Controllers\CatalogController;
```

Route additions, after the existing `/me` routes:

```php
Route::get('/events', [CatalogController::class, 'index'])->name('catalog.events.index');
Route::get('/events/{event}', [CatalogController::class, 'show'])->whereUuid('event')->name('catalog.events.show');
Route::get('/artists', [CatalogController::class, 'index'])->name('catalog.artists.index');
Route::get('/venues', [CatalogController::class, 'index'])->name('catalog.venues.index');
Route::get('/songs', [CatalogController::class, 'index'])->name('catalog.songs.index');
Route::get('/tours', [CatalogController::class, 'index'])->name('catalog.tours.index');
```

The names are part of the FormRequest interface. Retain the outer `v1` prefix and existing account middleware/rate limits. Do not expose a parallel unversioned route or a catalog write action.

- [ ] **Step 7: Run focused tests and repair only contract failures.**

```sh
php artisan test --compact tests/Feature/CatalogReadEndpointsTest.php
php artisan test --compact tests/Feature/JsonApiResourcesTest.php
```

Expected: all tests pass and the existing declarative resource behavior remains unchanged. If a serializer still queries a private table or a nested relation per result, fix validated include propagation/eager loading in the endpoint; do not remove the privacy/query assertions or insert generic access policy into resources.

### Complete Test File for Task 1

```php
<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventArtist;
use App\Models\EventProviderReference;
use App\Models\EventTour;
use App\Models\SetlistEntry;
use App\Models\Song;
use App\Models\SongLink;
use App\Models\Tour;
use App\Models\TourArtist;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogReadEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
    }

    /** @return array<string, list<string>> */
    public static function catalogs(): array
    {
        return ['events' => ['events'], 'artists' => ['artists'], 'venues' => ['venues'], 'songs' => ['songs'], 'tours' => ['tours']];
    }

    #[DataProvider('catalogs')]
    public function test_each_collection_requires_a_session_and_has_an_empty_document(string $catalog): void
    {
        $this->read($catalog)->assertOk()->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonPath('data', [])->assertJsonPath('meta.per_page', 20);
        auth()->forgetGuards();
        $this->getJson('/api/v1/'.$catalog)->assertUnauthorized();
    }

    public function test_detail_requires_a_session_and_resolves_only_public_uuid(): void
    {
        $event = Event::factory()->create();
        $this->read('events/'.$event->external_id)->assertOk()->assertJsonPath('data.id', $event->external_id);
        $this->read('events/'.$event->id)->assertNotFound();
        $this->read('events/not-a-uuid')->assertNotFound();
        $this->read('events/'.Str::uuid())->assertNotFound();
        auth()->forgetGuards();
        $this->read('events/'.$event->external_id)->assertUnauthorized();
    }

    public function test_shared_documents_preserve_nulls_midnight_coordinates_and_lineup_order(): void
    {
        $venue = Venue::factory()->create(['name' => 'Teatro Ñ', 'street' => null, 'house_number' => '26-35', 'postal_code' => 'C001', 'lat' => 0, 'lng' => 0, 'timezone' => 'America/Argentina/Buenos_Aires']);
        $event = Event::factory()->create(['title' => 'Canción de invierno', 'venue_id' => $venue->id, 'year' => 2024, 'month' => null, 'day' => null, 'local_start_time' => '00:00:00', 'timezone' => null]);
        $last = EventArtist::factory()->create(['event_id' => $event->id, 'position' => null]);
        $second = EventArtist::factory()->create(['event_id' => $event->id, 'position' => 2]);
        $first = EventArtist::factory()->create(['event_id' => $event->id, 'position' => 1]);
        $tour = Tour::factory()->create(['name' => 'Gira del sur']);
        EventTour::factory()->create(['event_id' => $event->id, 'tour_id' => $tour->id]);
        $roster = TourArtist::factory()->create(['tour_id' => $tour->id]);
        $attendance = Attendance::factory()->create(['event_id' => $event->id, 'notes' => 'private-attendance-marker']);
        SetlistEntry::factory()->create(['attendance_id' => $attendance->id, 'notes' => 'private-setlist-marker']);
        Attendance::factory()->create(['event_id' => $event->id, 'owner_user_id' => auth()->id(), 'notes' => 'private-own-marker']);
        $response = $this->read('events/'.$event->external_id, ['include' => 'tourLinks.tour.artistLinks.artist'])->assertOk();
        $response->assertJsonPath('data.attributes.title', 'Canción de invierno')->assertJsonPath('data.attributes.year', 2024)
            ->assertJsonPath('data.attributes.month', null)->assertJsonPath('data.attributes.day', null)
            ->assertJsonPath('data.attributes.local_start_time', '00:00:00')->assertJsonPath('data.attributes.timezone', null);
        $document = $response->json();
        $this->assertSame([$first->external_id, $second->external_id, $last->external_id], array_column($document['data']['relationships']['artistLinks']['data'], 'id'));
        $included = collect($document['included']);
        $publicVenue = $included->firstWhere('type', 'venues');
        $this->assertSame($venue->external_id, $publicVenue['id']);
        $this->assertNull($publicVenue['attributes']['street']);
        $this->assertSame('26-35', $publicVenue['attributes']['house_number']);
        $this->assertSame('0.0000000', $publicVenue['attributes']['lat']);
        $this->assertSame($tour->external_id, $included->firstWhere('type', 'tours')['id']);
        $this->assertTrue($included->contains('id', $roster->artist->external_id));
        $this->assertSharedOnly($document);
        $this->assertStringNotContainsString('private-attendance-marker', $response->getContent());
        $this->assertStringNotContainsString('private-setlist-marker', $response->getContent());
        $this->assertStringNotContainsString('private-own-marker', $response->getContent());
        $unknown = Event::factory()->create(['venue_id' => null]);
        $this->read('events/'.$unknown->external_id)->assertOk()->assertJsonPath('data.relationships.venue.data', null)
            ->assertJsonPath('data.relationships.artistLinks.data', [])->assertJsonPath('data.attributes.year', null);
        $this->read('events', ['filter' => ['q' => 'Canción']])->assertOk()->assertJsonPath('data.0.id', $event->external_id);
    }

    public function test_event_search_groups_artist_matches_with_venue_and_date_filters(): void
    {
        $venue = Venue::factory()->create();
        $artist = Artist::factory()->create(['name' => 'Björk']);
        $wanted = Event::factory()->create(['title' => 'Live night', 'venue_id' => $venue->id, 'year' => 2024, 'month' => 2, 'day' => 29]);
        $other = Event::factory()->create(['title' => 'Other night', 'year' => 2023]);
        foreach ([$wanted, $other] as $event) {
            EventArtist::factory()->create(['event_id' => $event->id, 'artist_id' => $artist->id]);
        }
        $filter = ['q' => 'björk', 'venue_uuid' => $venue->external_id, 'year' => 2024, 'month' => 2, 'day' => 29];
        $this->read('events', ['filter' => $filter])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $wanted->external_id);
        $this->read('events', ['filter' => ['q' => 'Live night']])->assertJsonPath('data.0.id', $wanted->external_id);
        $this->read('events', ['filter' => ['venue_uuid' => (string) Str::uuid()]])->assertOk()->assertJsonPath('data', []);
    }

    public function test_date_filters_preserve_known_precision_and_validate_calendar_dates(): void
    {
        Event::factory()->create(['year' => null]);
        Event::factory()->create(['year' => 2024]);
        Event::factory()->create(['year' => 2024, 'month' => 2]);
        Event::factory()->create(['year' => 2024, 'month' => 2, 'day' => 29]);
        $this->read('events', ['filter' => ['year' => 2024]])->assertJsonCount(3, 'data');
        $this->read('events', ['filter' => ['year' => 2024, 'month' => 2]])->assertJsonCount(2, 'data');
        $this->read('events', ['filter' => ['year' => 2024, 'month' => 2, 'day' => 29]])->assertJsonCount(1, 'data');
        foreach ([['month' => 2], ['day' => 1], ['year' => 2023, 'month' => 2, 'day' => 29], ['year' => 2024, 'month' => 13], ['year' => 0]] as $filter) {
            $this->read('events', ['filter' => $filter])->assertUnprocessable();
        }
    }

    public function test_artist_venue_song_and_tour_selection_uses_available_context(): void
    {
        $artist = Artist::factory()->create(['name' => 'Soda Stereo', 'type' => 'group', 'disambiguation' => 'Banda argentina']);
        Artist::factory()->create(['name' => 'Soda Stereo', 'type' => 'person']);
        $this->read('artists', ['filter' => ['q' => 'argentina', 'type' => 'group']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $artist->external_id);
        $venue = Venue::factory()->create(['name' => 'Teatro Colón', 'city' => 'Buenos Aires', 'country_code' => 'AR']);
        Venue::factory()->create(['name' => 'Teatro Colón', 'city' => 'Other city', 'country_code' => 'UY']);
        $this->read('venues', ['filter' => ['q' => 'Colón', 'city' => 'buenos', 'country_code' => 'ar']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $venue->external_id);
        $song = Song::factory()->create(['title' => 'De música ligera', 'disambiguation' => 'Composición de Cerati y Bosio']);
        Song::factory()->create(['title' => 'De música ligera', 'disambiguation' => 'Different work']);
        SongLink::factory()->create(['song_id' => $song->id]);
        $response = $this->read('songs', ['filter' => ['q' => 'música', 'disambiguation' => 'Cerati']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $song->external_id);
        $this->assertArrayNotHasKey('relationships', $response->json('data.0'));
        $this->assertArrayNotHasKey('included', $response->json());
        $tour = Tour::factory()->create(['name' => 'Gira continental']);
        TourArtist::factory()->create(['tour_id' => $tour->id, 'artist_id' => $artist->id]);
        Tour::factory()->create(['name' => 'Gira continental']);
        $response = $this->read('tours', ['filter' => ['q' => 'continental', 'artist_uuid' => $artist->external_id]])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tour->external_id);
        $this->assertTrue(collect($response->json('included'))->contains('id', $artist->external_id));
        $this->read('tours', ['filter' => ['artist_uuid' => (string) Str::uuid()]])->assertJsonPath('data', []);
    }

    public function test_literal_text_and_duplicate_names_keep_distinct_public_identities(): void
    {
        $literal = Artist::factory()->create(['name' => '100%_! Live']);
        Artist::factory()->create(['name' => '100anything Live']);
        $this->read('artists', ['filter' => ['q' => '100%_!']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $literal->external_id);
        $a = Artist::factory()->create(['name' => 'Sigur Rós']);
        $b = Artist::factory()->create(['name' => 'Sigur Rós']);
        $response = $this->read('artists', ['filter' => ['q' => 'Sigur Rós']])->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing([$a->external_id, $b->external_id], array_column($response->json('data'), 'id'));
        $this->read('artists', ['filter' => ['q' => "' OR 1=1 --"]])->assertOk()->assertJsonPath('data', []);
    }

    public function test_provider_tuples_match_one_row_and_can_find_multiple_shared_events(): void
    {
        $artist = Artist::factory()->create();
        ArtistProviderId::factory()->create(['artist_id' => $artist->id, 'provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => 'known-artist']);
        $tuple = ['provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => 'known-artist'];
        $response = $this->read('artists', ['filter' => $tuple])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $artist->external_id);
        $this->assertArrayNotHasKey('relationships', $response->json('data.0'));
        $this->read('artists', ['filter' => ['provider' => 'musicbrainz']])->assertUnprocessable();
        $this->read('artists', ['filter' => [...$tuple, 'resource_type' => 'work']])->assertOk()->assertJsonPath('data', []);
        $events = Event::factory()->count(2)->create();
        foreach ($events as $event) {
            EventProviderReference::factory()->create(['event_id' => $event->id, 'provider' => 'manual_source', 'resource_type' => 'festival', 'provider_resource_id' => 'shared-source']);
            EventProviderReference::factory()->create(['event_id' => $event->id, 'provider' => 'other_source', 'resource_type' => 'day', 'provider_resource_id' => 'other-id']);
        }
        $tuple = ['provider' => 'manual_source', 'resource_type' => 'festival', 'provider_resource_id' => 'shared-source'];
        $response = $this->read('events', ['filter' => $tuple])->assertOk()->assertJsonCount(2, 'data');
        $this->assertSharedOnly($response->json());
        $this->read('events', ['filter' => [...$tuple, 'resource_type' => 'day', 'provider_resource_id' => 'other-id']])->assertJsonPath('data', []);
        $this->read('events', ['filter' => ['provider' => 'manual_source', 'resource_type' => 'festival']])->assertUnprocessable();
    }

    #[DataProvider('catalogs')]
    public function test_pagination_is_bounded_stable_and_retains_filters(string $catalog): void
    {
        $class = match ($catalog) { 'events' => Event::class, 'artists' => Artist::class, 'venues' => Venue::class, 'songs' => Song::class, 'tours' => Tour::class };
        $field = in_array($catalog, ['events', 'songs'], true) ? 'title' : 'name';
        $items = $class::factory()->count(3)->create([$field => 'Repeated name']);
        $expected = $items->sortBy('external_id')->pluck('external_id')->all();
        $first = $this->read($catalog, ['filter' => ['q' => 'Repeated'], 'per_page' => 2])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $second = $this->read($catalog, ['filter' => ['q' => 'Repeated'], 'per_page' => 2, 'page' => 2])->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($expected, [...array_column($first->json('data'), 'id'), ...array_column($second->json('data'), 'id')]);
        parse_str(parse_url($first->json('links.next'), PHP_URL_QUERY), $next);
        $this->assertSame('Repeated', $next['filter']['q']);
        $this->assertSame('2', $next['page']);
        $this->read($catalog, ['page' => 99])->assertOk()->assertJsonPath('data', []);
        $this->read($catalog, ['per_page' => 101])->assertUnprocessable();
        $this->read($catalog, ['page' => 0])->assertUnprocessable();
        $this->read($catalog, ['filter' => ['q' => 'no-such-catalog-term']])->assertJsonPath('data', []);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function unsafeQueries(): array
    {
        return [
            'attendance' => ['events', ['include' => 'attendances.members.user']],
            'lineup to private entries' => ['events', ['include' => 'artistLinks.artist.entryLinks.entry.attendance']],
            'venue back edge' => ['events', ['include' => 'venue.events.attendances']],
            'tour back edge' => ['events', ['include' => 'tourLinks.tour.eventLinks.event.attendances']],
            'artist entries' => ['artists', ['include' => 'entryLinks.entry.attendance.members']],
            'venue events' => ['venues', ['include' => 'events.attendances']],
            'song entries' => ['songs', ['include' => 'entries.attendance']],
            'song playable links' => ['songs', ['include' => 'links']],
            'event references' => ['events', ['include' => 'providerReferences']],
            'tour event back edge' => ['tours', ['include' => 'eventLinks.event.attendances']],
            'include array' => ['events', ['include' => ['attendances']]],
            'private filter' => ['events', ['filter' => ['owner_user_id' => 1]]],
            'fake song artist context' => ['songs', ['filter' => ['artist_uuid' => '00000000-0000-4000-8000-000000000001']]],
            'unknown sparse fields' => ['events', ['fields' => ['events' => 'attendances']]],
            'unknown top key' => ['artists', ['sort' => 'name']],
            'filter scalar' => ['songs', ['filter' => 'private']],
            'long query' => ['artists', ['filter' => ['q' => str_repeat('x', 201)]]],
            'page array' => ['events', ['page' => [1]]],
            'page ceiling' => ['venues', ['page' => 100001]],
            'query array' => ['artists', ['filter' => ['q' => ['bad']]]],
            'invalid UUID' => ['events', ['filter' => ['venue_uuid' => '1']]],
            'invalid country' => ['venues', ['filter' => ['country_code' => 'ARG']]],
            'invalid artist UUID' => ['tours', ['filter' => ['artist_uuid' => '1']]],
        ];
    }

    #[DataProvider('unsafeQueries')]
    public function test_unsafe_requests_are_rejected_before_private_queries(string $catalog, array $query): void
    {
        $event = Event::factory()->create();
        $attendance = Attendance::factory()->create(['event_id' => $event->id]);
        SetlistEntry::factory()->create(['attendance_id' => $attendance->id]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->read($catalog, $query)->assertUnprocessable();
            $this->assertNoPrivateQueries(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        if ($catalog === 'events' && array_key_exists('include', $query)) {
            $this->read('events/'.$event->external_id, $query)->assertUnprocessable();
        }
    }

    public function test_get_json_bodies_cannot_bypass_the_include_boundary(): void
    {
        $event = Event::factory()->create();
        foreach (['events', 'events/'.$event->external_id, 'artists', 'venues', 'songs', 'tours'] as $path) {
            $this->json('GET', '/api/v1/'.$path, ['include' => 'attendances.members.user'])->assertUnprocessable()->assertJsonValidationErrors('body');
        }
    }

    public function test_event_default_and_optional_includes_do_not_query_per_result(): void
    {
        $this->eventGraph();
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $response = $this->read('events', ['include' => 'tourLinks.tour.artistLinks.artist', 'per_page' => 100])->assertOk();
                $this->assertSharedOnly($response->json());
                $queries = DB::getQueryLog();
                $this->assertNoPrivateQueries($queries);

                return count($queries);
            } finally {
                DB::disableQueryLog();
            }
        };
        $single = $count();
        for ($index = 0; $index < 20; $index++) {
            $this->eventGraph();
        }
        $this->assertLessThanOrEqual($single + 1, $count());
    }

    private function eventGraph(): void
    {
        $event = Event::factory()->create(['venue_id' => Venue::factory()->create()->id]);
        EventArtist::factory()->create(['event_id' => $event->id]);
        $link = EventTour::factory()->create(['event_id' => $event->id]);
        TourArtist::factory()->create(['tour_id' => $link->tour_id]);
    }

    private function read(string $path, array $query = []): TestResponse
    {
        $suffix = $query === [] ? '' : '?'.http_build_query($query);

        return $this->get('/api/v1/'.$path.$suffix, ['Accept' => 'application/json']);
    }

    private function assertSharedOnly(array $document): void
    {
        $data = isset($document['data']['id']) ? [$document['data']] : $document['data'];
        foreach ([...$data, ...($document['included'] ?? [])] as $object) {
            $this->assertContains($object['type'], ['events', 'artists', 'venues', 'songs', 'tours', 'event_artists', 'event_tours', 'tour_artists']);
            $this->assertTrue(Str::isUuid($object['id']));
            foreach (array_keys($object['attributes'] ?? []) as $key) {
                $this->assertFalse(in_array($key, ['id', 'external_id', 'notes', 'email', 'password'], true) || str_ends_with($key, '_id'));
            }
            foreach (array_keys($object['relationships'] ?? []) as $name) {
                $this->assertContains($name, ['venue', 'artistLinks', 'artist', 'tourLinks', 'tour']);
            }
        }
    }

    private function assertNoPrivateQueries(array $queries): void
    {
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(attendances|attendance_members|attendance_member_seats|attendance_invitations|attendance_links|setlist_entries|setlist_entry_artists)\b/i', $query['query']);
        }
    }
}
```

The `read()` helper uses a body-free `get()` with an Accept header: Laravel's `getJson()` helper encodes an empty JSON array as a GET body, which this contract intentionally rejects. The separate `json('GET', ...)` test exercises that rejection.

These tests intentionally exercise real HTTP serialization, unsafe nested paths, and a larger page. They do not make provider HTTP requests; all references are local factory rows. Provider tuples are tested as lookup associations, not as permission metadata.

### Task 2: Document the Contract and Run the Acceptance Gates

**Files:** Modify only the relevant API documentation in `README.md`; inspect the Task 1 files and existing tests. Do not alter historical design/spec files or the frontend account implementation.

**Interfaces:** Consumes the six named authenticated routes and their validated request/JSON:API contracts. Produces accurate API usage documentation and verified local results, with SQLite/PostgreSQL limitations reported explicitly.

- [ ] **Step 1: Add this API section to the root README, next to the existing account endpoint documentation.**

If an equivalent section exists at execution time, update it in place and preserve unrelated README edits.

```markdown
### Shared catalog reads

All catalog reads require a signed-in Sanctum session and `Accept: application/json`. They use the existing `/api/v1` prefix. These GET requests do not mutate data or require CSRF initialization. Success is JSON:API; guests receive 401 and invalid queries receive Laravel JSON validation errors with status 422.

- `GET /api/v1/events`: `filter[q]` matches title or lineup Artist name. Optional `filter[venue_uuid]`, `filter[year]`, `filter[month]`, `filter[day]`, or the complete `filter[provider]`, `filter[resource_type]`, `filter[provider_resource_id]` tuple narrow results. Venue and ordered lineup are included by default; `include=tourLinks.tour` adds Tours, and `include=tourLinks.tour.artistLinks.artist` adds their participating Artists.
- `GET /api/v1/events/{event}`: a Spectacles Event UUID returns the same shared facts. Numeric IDs, malformed UUIDs, and missing Events return 404.
- `GET /api/v1/artists`: `filter[q]` searches name/disambiguation; optional exact `filter[type]` and a complete provider tuple identify existing Artists.
- `GET /api/v1/venues`: `filter[q]` searches name; optional `filter[city]` and two-letter `filter[country_code]` narrow location. Address parts, decimal-string coordinates, and timezone remain nullable.
- `GET /api/v1/songs`: `filter[q]` searches title and `filter[disambiguation]` searches composition context. The schema has no composer/Artist association, and this route does not infer credits from private setlists.
- `GET /api/v1/tours`: `filter[q]` searches name and optional `filter[artist_uuid]` matches participating Artists, which are included by default.

Collection queries accept `page` (1–100000) and `per_page` (1–100, default 20), sort by display name/title then UUID, and return native paginator links/meta. Supplied filters combine with AND. Empty text means a paginated browse; text is literal substring search with database LOWER case behavior, not fuzzy matching or accent removal. Month requires year and day requires year/month; date filters match stored components without guessing missing precision.

Attendances, owners, members, companion relationships, and private setlists are excluded, including through nested includes. Unsupported query/include/filter keys and nonempty GET bodies are rejected. Provider tuples filter existing local references only; optional provider identity/playable output, provider calls, imports, and synchronization are outside this release. No create/update/delete catalog endpoints or frontend Tour control are included.
```

- [ ] **Step 2: Check the actual routes and execute relevant SQLite coverage.**

From `backend/`, after the current machine runtime preflight:

```sh
php artisan route:list --path=api/v1 --method=GET --except-vendor
php artisan test --compact tests/Feature/CatalogReadEndpointsTest.php
php artisan test --compact tests/Feature/CatalogTest.php tests/Feature/CatalogRelationshipsTest.php tests/Feature/ProviderReferencesTest.php tests/Feature/PublicIdentityTest.php tests/Feature/JsonApiResourcesTest.php tests/Feature/ApiHealthTest.php tests/Feature/AuthSessionTest.php tests/Feature/ProfileTest.php
```

Expected: the six GET catalog routes retain `auth:sanctum`, private UUIDs resolve correctly, prior serialization/data/account/health behavior stays intact, and no provider credentials or live requests are needed. The PHPUnit defaults are `APP_ENV=testing`, in-memory SQLite, empty DB_URL, array session/cache, and guarded by `backend/tests/TestCase.php`; do not bypass that guard or migrate a developer database.

- [ ] **Step 3: Verify the new SQL predicates against the dedicated PostgreSQL test database.**

The existing data-layer plan uses the local `spectacles_testing` database. Verify that dedicated test target and local account before running; do not create or switch a production database or install a service. The command below uses its existing TCP/current-user convention; apply the current local connection configuration if different, without recording secrets.

```sh
DB_CONNECTION=pgsql DB_DATABASE=spectacles_testing DB_URL= DB_HOST=127.0.0.1 DB_PORT=5432 DB_USERNAME="$USER" php artisan test --compact tests/Feature/CatalogReadEndpointsTest.php
```

Require the same filters, escaped literal matching, dates, pagination, and privacy results on PostgreSQL. If this dedicated service/database is unavailable, report that check as blocked/unverified rather than claiming cross-engine validation. Do not run a migration command against the shared app database to make this gate pass.

- [ ] **Step 4: Format changed PHP and repeat focused verification after formatting.**

```sh
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/CatalogReadEndpointsTest.php tests/Feature/JsonApiResourcesTest.php
```

The project's required dirty formatter can include pre-existing PHP changes. Inspect live status first. If unrelated dirty PHP files are present, preserve them and use this explicit-path command instead, reporting why its scope was narrowed:

```sh
vendor/bin/pint --format agent app/Http/Requests/CatalogReadRequest.php app/Http/Controllers/CatalogController.php routes/api.php tests/Feature/CatalogReadEndpointsTest.php
```

Do not silently reformat or discard unrelated work.

- [ ] **Step 5: Inspect the complete intended diff and stop at verified local implementation.**

From the repository root:

```sh
git diff --check
git status --short
git diff -- backend/routes/api.php README.md
git diff --no-index -- /dev/null backend/app/Http/Requests/CatalogReadRequest.php
git diff --no-index -- /dev/null backend/app/Http/Controllers/CatalogController.php
git diff --no-index -- /dev/null backend/tests/Feature/CatalogReadEndpointsTest.php
```

For newly created files, `git diff --no-index` exits 1 when it successfully displays a difference; that is expected. If the files are tracked by then, use `git diff` on their paths instead. Confirm no dependency/lockfile, model/resource, migration, frontend, private dotenv, or unrelated change is included. All six reads must remain authenticated and free of write/provider-call paths. Report actual commands/results and any PostgreSQL limitation. Commit/push/PR/merge/deployment remain separate user authorization; this plan does not grant it.

## Source References and Planning Self-Review

Repository sources inspected: `backend/routes/api.php`, the five catalog models and relationship/provider models, `HasPublicId`, the existing JSON:API resource classes, relevant factories/tests, `backend/phpunit.xml`, `backend/tests/TestCase.php`, schema migrations, and the approved database design. The resource traversal behavior was checked in the installed framework's `JsonApiResource`, `AnonymousResourceCollection`, and `Concerns/ResolvesJsonApiElements`.

Framework references: [Laravel 13 JSON:API resources](https://laravel.com/docs/13.x/eloquent-resources#jsonapi-resources) for native response/include behavior, [validation](https://laravel.com/docs/13.x/validation#rule-array) for permitted nested keys, and [pagination](https://laravel.com/docs/13.x/pagination#appending-query-string-values) for query-preserving links. Installed source is the authority for the pinned version.

- Coverage: each requested route has a named route, request contract, controller path, and HTTP test. Tour search remains API-only; no frontend control is implied.
- Privacy: exact include paths and all input sources are validated before serialization; both own and other users' Attendance graphs are excluded. Native serialization receives the validated FormRequest explicitly.
- Reuse: UUID binding and existing declarative resources remain unchanged. The controller performs the concrete query/privacy work for these actual endpoints.
- Identity: duplicate names remain distinct; provider lookup uses one full tuple row and can return multiple Events where association scope permits.
- Song/reference limits: available composition context is existing disambiguation. No composer relation is invented. Optional provider/playable output is deliberately excluded pending an established permitted scope; it does not block the six core reads.
- Review Focus: every listed risk is pinned by a named Task 1 test, including query growth and GET-body bypass.
- Delivery: this is a plan only. No application implementation, migrations, database writes, or commits were performed while writing it. There are no unresolved access decisions; sign-in was explicitly selected by the user.

Planning verification: all five PHP example blocks passed `php -l` with PHP 8.5.10; code/API names were checked against installed framework source, Markdown fences and placeholder references were checked, and the plan was self-reviewed against the specification. Endpoint tests, migrations, formatter, and implementation have not been run.
