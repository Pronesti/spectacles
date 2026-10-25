# Laravel Event Data Layer Implementation Plan

> **Execution:** Follow the user-selected execution method and repository requirements. This plan does not require any additional skill. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build and test the Laravel migrations, Eloquent models, and factories for the approved live-event database design on SQLite and PostgreSQL.

**Architecture:** Use internal integer primary keys and separate public UUIDs. Represent every join and provider reference as a first-class Eloquent model, with real foreign keys and database uniqueness constraints. Use ordinary Laravel models with no general save-time validation. Keep input validation and user-facing workflows outside this data-layer delivery.

**Tech Stack:** PHP 8.5; installed Laravel 13.38.0, PHPUnit 12.5.40, Faker 1.24.1, Pint 1.32.1; SQLite and PostgreSQL. No new packages.

**Spec:** [Approved database design](../specs/2026-10-22-live-events-database-design.md). Read both documents before execution. The user authorized this plan on 2026-10-22 and selected SQLite and PostgreSQL. This does not authorize implementation or commits.

## Global Constraints

- "Application code, database identifiers, and system-defined stored values use English."
- "Catalog names and user content retain their original language and spelling; interface localization does not translate them."
- "Setlists are not stored in JSON."
- "Tours are optional."
- "Names are nonempty display values, not unique keys."
- "A user can belong to only one Attendance for a given Event."
- "The owner must always be a member."
- "Provider IDs remain strings even when a particular service uses UUIDs or numbers."
- "Each reference table has a real foreign key to its parent."
- "For overlapping edits to the same shared value, the last successful save wins."
- "No database enum or separate type table is required."
- User addition: support SQLite and PostgreSQL. Keep the current SQLite default; do not change deployment or local data.
- Follow `/Users/pronestivm/Projects/spectacles/backend/AGENTS.md`. Read any newly added applicable instructions before execution. There is currently no `.ai/rules` directory. The referenced `testing-best-practices` skill was not found in the active skill locations; this plan uses the repository's PHPUnit conventions directly.
- Before execution, read `/Users/pronestivm/.config/agent-skills/runtime.md` again. Its current command prefixes are `php` and `composer`, from `backend/`. PHP 8.5.10 is currently installed.
- Run Artisan generators with `--no-interaction`. Use `php artisan make:test --phpunit Name`; do not include `Feature/` in the name. Format changed PHP with `vendor/bin/pint --dirty --format agent`.
- Preserve existing changes to `.gitignore`, `README.md`, frontend files, `.env.example`, and untracked research/spec documents. Never display `.env` secrets.
- Do not execute `migrate`, `migrate:fresh`, seeds, or rollback against the existing development database. All schema tests use an isolated test database.
- Do not create controllers, routes, policies, provider HTTP clients, background jobs, translations, synchronization, duplicate merging, or frontend changes.
- At execution, use one `feat/` feature branch created from `main`, including only release dependencies. Do not merge or rebase `dev` into it. The current branch is `feat/initial-setup`; recheck its relationship to `main` before selecting an execution checkout. Do not switch or move unrelated dirty files. Worktree isolation is optional and requires the user’s choice at execution.
- Planning is the present scope. No commit, push, PR, deployment, dependency change, or paid resource is authorized. Commit checkpoints below are conditional on separate commit authorization.

## Review Focus

1. A join or provider row must get a distinct public UUID while all foreign keys stay integer. Test every model in Task 7, including replication and immutable UUIDs.
2. Conflicting memberships and provider claims must fail through raw SQL too. Test composite foreign keys and conditional indexes in Tasks 4 and 6 on both engines.
3. Sparse dates, zero coordinates, Unicode names, and text house numbers must retain their meaning. Test preservation of these values in Task 2. Input rejection belongs to future request validation.
4. Deleting an Attendance must remove its children without deleting catalog records. Referenced Songs and Events must survive a rejected delete. Test both paths in Task 7.
5. A factory must reuse an assigned parent, keep owner membership valid, and avoid recursive graphs. Test default, overridden-parent, and rollback cases in Tasks 4 and 7.

---

## Scope, decisions, and file map

### Decisions accepted after review

These decisions supersede conflicting details in the earlier design spec for this implementation phase. The source spec remains unchanged by this document-only update.

- Store stable setlist.fm IDs only. Do not store source revision IDs, check for upstream changes, or update saved records from a source. Users edit their saved setlist manually.
- Do not add a general save-time validation layer or a custom validation base model. Use ordinary Laravel models, structural database constraints, UUID generation, and a small UUID immutability guard.
- Reuse Laravel’s validation messages and language resources. Do not create custom validation language files. Future input validation will use Laravel Form Requests.
- Owner membership and ownership changes remain production workflow requirements. Factories construct valid sample graphs; they are not production creation APIs.
- Do not add the extra regression cases proposed in the review. Retain the original storage, identity, factory, migration, and constraint tests. Remove test expectations for save-time validators that are no longer part of this phase. No tests are executed during this planning update.

This is one coherent data-layer plan. Catalog rows, joins, membership constraints, and provider identities depend on each other. Separate API/workflow and provider-integration plans can follow; they are not prerequisites for testing this data layer.

The source spec also describes permissions, invitation acceptance, ownership transfer, atomic list reordering, browser-language fallback, and confirmed autocomplete. Those behaviors need application workflows. This plan supplies their storage and relationships, but does not claim to deliver those workflows. In particular, direct model writes are internal operations, not authorization checks. Owner membership is guaranteed by the Attendance factory; a future production creation workflow must create the Attendance and owner membership in one transaction before exposing it. A future ownership/removal workflow must enforce the owner invariant. Do not hide those gaps with an observer that silently accepts invitations or removes owners.

The following are implementation proposals for this plan, not previously settled product choices:

- Use Laravel's current UUIDv7 generator for public UUIDs, without changing integer primary keys.
- Use nullable decimal `(10, 7)` for both coordinates. Keep time as a nullable `time` column with no timezone conversion cast.
- Initial application type codes: Artist `person`, `group`, `orchestra`, `choir`, `character`, `other`; Event `concert`, `festival`, `theatre`, `comedy`, `dance`, `other`; link `photo_album`, `social_post`, `source`, `other`. Configurable English strings, not database enums. Artist type may be null.
- Permit year 1 through 9999, month 1 through 12, and valid Gregorian full dates. Unknown components remain null.
- Use database foreign keys, required columns, defaults, and unique constraints for structural integrity. Do not add general create/update validation hooks. Keep only UUID generation and the small model guard that prevents changing an existing public UUID. Future input validation belongs in Laravel Form Requests and must reuse Laravel validation messages and language resources; do not create custom language files for this phase.
- Use explicit join models and `hasMany`/`belongsTo`, rather than plain `attach`/`sync`, so UUID creation is not bypassed. Traversal is one extra relationship, not a second set of join tables.
- Domain models use normal timestamps. Installer-owned framework tables such as sessions, cache, jobs, and password resets are not application-domain records and are unchanged.
- Provider references store original ID strings. Retain only the stable setlist.fm setlist ID. Do not store source revision IDs or support source change checks, update comparison, refresh, or synchronization. User-confirmed values become local editable records; later changes are manual.

Each file-write checkbox is a separate small action. When a code block defines several test methods, add and run one method at a time before moving to the next; retain the final file contents shown.

All implementation paths below are relative to `/Users/pronestivm/Projects/spectacles`. Shell commands run from `/Users/pronestivm/Projects/spectacles/backend` unless a command explicitly says otherwise. The fixed migration filenames establish order; run the named Artisan generator and rename its generated file to the listed filename before editing. No installed migration is rewritten.

| Files | Responsibility |
| --- | --- |
| `backend/app/Models/Concerns/HasPublicId.php` | Public UUID generation, route key, and immutability; native replication excludes unique IDs |
| `backend/app/Models/User.php`, `backend/database/factories/UserFactory.php` | Extend existing user identity and nullable locale |
| `backend/database/migrations/2026_10_22_000001_add_public_identity_to_users.php` | Preserve and backfill existing Users |
| `backend/database/migrations/2026_10_22_000002_create_event_catalog.php` | Artists, Venues, Tours, Songs, Events |
| `backend/database/migrations/2026_10_22_000003_create_event_catalog_relationships.php` | EventArtist, TourArtist, EventTour |
| `backend/database/migrations/2026_10_22_000004_create_attendance_records.php` | Attendance, membership, seats, invitations, links |
| `backend/database/migrations/2026_10_22_000005_create_setlist_records.php` | Flat list entries and performer joins |
| `backend/database/migrations/2026_10_22_000006_create_provider_references.php` | Four catalog identity tables, Event references, Song links |
| `backend/app/Models/{Model}.php`, `backend/database/factories/{Model}Factory.php` | One explicit model and factory for each of the 21 new tables; exact names and contents appear in their owning task |
| `backend/tests/Feature/PublicIdentityTest.php`, `UserIdentityUpgradeTest.php` | Identity and upgrade checks |
| `backend/tests/Feature/CatalogTest.php`, `CatalogRelationshipsTest.php` | Catalog content, date precision, and joins |
| `backend/tests/Feature/AttendanceRecordsTest.php`, `SetlistRecordsTest.php` | Collaborative storage and flat entries |
| `backend/tests/Feature/ProviderReferencesTest.php`, `DomainGraphTest.php`, `DomainMigrationTest.php` | Provider uniqueness, graph, deletion, all factories, upgrade and rollback |
| `backend/tests/TestCase.php` | Safe test database selection and one transaction-safe database rejection assertion |
| `backend/database/seeders/LiveEventSeeder.php` | Optional local sample graph, never called automatically |

## Common implementation contracts

All new concrete models extend Laravel’s `Model` and use `HasFactory` and `HasPublicId`. Each has an explicit table name, fillable domain fields (never `id` or `external_id`), casts where specified, and typed relationships. The code blocks below are file contents, not sketches. Normal PSR-12 formatting is delegated to Pint.

Factory defaults do not call providers. A default `create()` must persist a valid graph with UUIDs. A factory's `make()` need not assign a public UUID before the model is saved. Do not call a model factory from a model constructor or a production model hook.

Each task follows red → minimal implementation → green. Tests expected to fail due to missing classes/tables are still useful red checks. When a task adds a test to an existing file, run that test again after every change.

### Task 1: Public identity and existing Users

**Files:** Create `HasPublicId.php`, migration `2026_10_22_000001_add_public_identity_to_users.php`, and the two identity tests listed above. Modify the existing `User.php`, `UserFactory.php`, and `backend/tests/TestCase.php`.

**Interfaces:** Consumes Laravel `HasUuids`. Produces `uniqueIds(): array`, `getRouteKeyName(): string`, Laravel’s inherited UUID-aware `replicate(?array $except = null)`, and a small updating guard for public UUID immutability. All later models use the public identity trait directly. The test base also rejects unsafe database configuration before schema setup.

- [ ] **Step 1: Generate the test files and write the identity tests.**

```sh
php artisan make:test --phpunit PublicIdentityTest --no-interaction
php artisan make:test --phpunit UserIdentityUpgradeTest --no-interaction
```

Before running any database tests, add this method to the existing `backend/tests/TestCase.php`. It checks the resolved configuration before Laravel applies the `RefreshDatabase` trait. It prevents a cached development configuration or a connection URL from silently selecting a personal database.

```php
public function createApplication(): \Illuminate\Foundation\Application
{
    $app = parent::createApplication();
    $driver = $app['config']->get('database.default');
    $connection = $app['config']->get('database.connections.'.$driver, []);
    $database = $connection['database'] ?? null;
    $safe = ($driver === 'sqlite' && $database === ':memory:')
        || ($driver === 'pgsql' && $database === 'spectacles_testing');
    if (! $app->environment('testing') || ! $safe || ! empty($connection['url'])) {
        throw new \LogicException('Tests require in-memory SQLite or the dedicated spectacles_testing PostgreSQL database, without DB_URL.');
    }
    return $app;
}
```

`backend/tests/Feature/PublicIdentityTest.php`:

```php
<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class PublicIdentityTest extends TestCase
{
    use RefreshDatabase;
    public function test_user_keeps_integer_key_and_stable_public_uuid(): void
    {
        $user = User::factory()->create(['locale' => 'es-AR']);
        $uuid = $user->external_id;
        $this->assertIsInt($user->id);
        $this->assertTrue(Str::isUuid($uuid));
        $this->assertTrue($user->getIncrementing());
        $this->assertSame('external_id', $user->getRouteKeyName());
        $this->assertTrue($user->resolveRouteBinding($uuid)->is($user));
        $user->update(['name' => 'Updated', 'locale' => 'en']);
        $this->assertSame($uuid, $user->fresh()->external_id);
    }
    public function test_public_uuid_cannot_be_changed_on_an_existing_model(): void
    {
        $user = User::factory()->create();
        $user->external_id = (string) Str::uuid7();
        $this->expectException(ValidationException::class);
        $user->save();
    }
    public function test_replicating_a_user_generates_a_new_public_uuid(): void
    {
        $user = User::factory()->create();
        $copy = $user->replicate();
        $copy->email = 'copy@example.com';
        $copy->save();
        $this->assertNotSame($user->external_id, $copy->external_id);
    }
}
```

`backend/tests/Feature/UserIdentityUpgradeTest.php` deliberately uses a separate connection, not `RefreshDatabase`, to simulate an installed database with existing Users:

```php
<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
class UserIdentityUpgradeTest extends TestCase
{
    public function test_existing_users_are_preserved_and_backfilled(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.identity_upgrade' => [
            'driver' => 'sqlite', 'database' => ':memory:',
            'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('identity_upgrade');
        try {
            $installer = require database_path('migrations/0001_01_01_000000_create_users_table.php');
            $installer->up();
            DB::table('users')->insert([
                'name' => 'Existing User', 'email' => 'existing@example.com',
                'password' => 'retained-hash',
            ]);
            $upgrade = require database_path('migrations/2026_10_22_000001_add_public_identity_to_users.php');
            $upgrade->up();
            $row = DB::table('users')->first();
            $this->assertSame('retained-hash', $row->password);
            $this->assertSame('existing@example.com', $row->email);
            $this->assertTrue(Str::isUuid($row->external_id));
            $this->assertNull($row->locale);
            $upgrade->down();
            $this->assertFalse(Schema::hasColumn('users', 'external_id'));
            $this->assertSame(1, DB::table('users')->count());
        } finally {
            DB::purge('identity_upgrade');
            DB::setDefaultConnection($original);
        }
    }
}
```

This upgrade test covers SQLite's table rebuild path. Task 7 also tests the upgrade on PostgreSQL with an isolated schema in `DomainMigrationTest.php`.

- [ ] **Step 2: Run the red check.**

```sh
php artisan test --compact tests/Feature/PublicIdentityTest.php tests/Feature/UserIdentityUpgradeTest.php
```

Expected: missing public column / missing migration failures.

- [ ] **Step 3: Generate the migration and implement identity support.**

```sh
php artisan make:migration add_public_identity_to_users --table=users --no-interaction
```

Rename the generated migration to the fixed Task 1 filename. Its contents:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
return new class extends Migration {
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            throw new LogicException('The domain schema supports SQLite and PostgreSQL.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('external_id')->nullable();
            $table->string('locale', 35)->nullable();
        });
        DB::table('users')->orderBy('id')->chunkById(100, function (\Illuminate\Support\Collection $users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)
                    ->update(['external_id' => (string) Str::uuid7()]);
            }
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('external_id')->nullable(false)->change();
            $table->unique('external_id');
        });
    }
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['external_id']);
            $table->dropColumn(['external_id', 'locale']);
        });
    }
};
```

- [ ] Write `backend/app/Models/Concerns/HasPublicId.php`:

```php
<?php
namespace App\Models\Concerns;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
trait HasPublicId
{
    use HasUuids;
    /** @return list<string> */
    public function uniqueIds(): array { return ['external_id']; }
    public function getRouteKeyName(): string { return 'external_id'; }
    public static function bootHasPublicId(): void
    {
        static::updating(function (Model $model): void {
            if ($model->isDirty('external_id')) {
                throw ValidationException::withMessages(['external_id' => __('validation.prohibited', ['attribute' => 'external_id'])]);
            }
        });
    }
}
```


- [ ] Update `backend/app/Models/User.php`: add `use App\Models\Concerns\HasPublicId;`; include `HasPublicId` in the existing trait use; append `'locale'` to `#[Fillable]`. Preserve password hashing, hidden fields, notifications, and `HasFactory`. Do not add a locale validation hook.


- [ ] Append `'locale' => null` to `backend/database/factories/UserFactory.php` in `definition()`. Do not put a provider ID into the public UUID. Laravel generates the UUID at insertion even when seeders disable model events.

- [ ] **Step 4: Run the identity tests and format.**

```sh
php artisan test --compact tests/Feature/PublicIdentityTest.php tests/Feature/UserIdentityUpgradeTest.php
vendor/bin/pint --dirty --format agent
```

Expected: both test files pass; existing User behavior remains intact.

- [ ] **Step 5: Review the task diff.** Run `git diff --check` from the repository root. If commit authorization is later given, stage only the files listed in this task and use `feat: add public record identifiers`. Do not stage the whole working tree.

### Task 2: Shared catalog and date precision

**Files:** Create the catalog migration, `Artist.php`, `Venue.php`, `Tour.php`, `Song.php`, `Event.php`, each matching factory, and `CatalogTest.php`.

**Interfaces:** Consumes Laravel `Model`, `HasFactory`, and `HasPublicId`. Produces `Artist`, `Venue`, `Tour`, `Song`, and `Event`, with nullable dates, coordinates, and disambiguation.

- [ ] **Step 1: Generate and write the failing tests.**

```sh
php artisan make:test --phpunit CatalogTest --no-interaction
```

`backend/tests/Feature/CatalogTest.php`:

```php
<?php
namespace Tests\Feature;
use App\Models\Artist;
use App\Models\Venue;
use App\Models\Tour;
use App\Models\Song;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CatalogTest extends TestCase
{
    use RefreshDatabase;
    public function test_catalog_is_manual_unicode_and_not_unique_by_name(): void
    {
        $artist = Artist::factory()->create(['name' => 'Björk', 'disambiguation' => null]);
        $other = Artist::factory()->create(['name' => 'Björk', 'disambiguation' => 'Another artist']);
        $this->assertNotSame($artist->id, $other->id);
        $this->assertSame('Björk', $artist->fresh()->name);
        $song = Song::factory()->create(['title' => 'Canción', 'disambiguation' => null]);
        $this->assertSame('Canción', $song->fresh()->title);
        $this->assertNull(Venue::factory()->create()->lat);
        $this->assertNotNull(Tour::factory()->create()->external_id);
    }
    public function test_sparse_dates_and_unknown_time_are_not_invented(): void
    {
        $unknown = Event::factory()->create();
        $year = Event::factory()->create(['year' => 2024]);
        $month = Event::factory()->create(['year' => 2024, 'month' => 2]);
        $leap = Event::factory()->create(['year' => 2024, 'month' => 2, 'day' => 29, 'local_start_time' => '00:00:00']);
        $this->assertNull($unknown->year);
        $this->assertNull($year->month);
        $this->assertNull($month->day);
        $this->assertNull($year->local_start_time);
        $this->assertSame('00:00:00', $leap->fresh()->local_start_time);
    }
    public function test_venue_preserves_text_addresses_zero_coordinates_and_timezone(): void
    {
        $venue = Venue::factory()->create([
            'house_number' => '26-35', 'postal_code' => 'C1414BBC',
            'country_code' => 'AR', 'lat' => 0, 'lng' => 0,
            'timezone' => 'America/Argentina/Buenos_Aires',
        ])->fresh();
        $this->assertSame('26-35', $venue->house_number);
        $this->assertSame('C1414BBC', $venue->postal_code);
        $this->assertSame('0.0000000', $venue->lat);
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->assertNull($event->timezone);
        $this->assertSame($venue->timezone, $event->venue->timezone);
    }
}
```

- [ ] **Step 2: Run the red check.**

```sh
php artisan test --compact tests/Feature/CatalogTest.php
```

Expected: missing model or table failure.

- [ ] **Step 3: Generate and implement the migration, models, and factories.**


```sh
php artisan make:migration create_event_catalog --no-interaction
php artisan make:model Artist --factory --no-interaction
php artisan make:model Venue --factory --no-interaction
php artisan make:model Tour --factory --no-interaction
php artisan make:model Song --factory --no-interaction
php artisan make:model Event --factory --no-interaction
```

- [ ] Write migration `backend/database/migrations/2026_10_22_000002_create_event_catalog.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            $table->string('type')->nullable();
            $table->text('disambiguation')->nullable();
            $table->timestamps();
        });
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            foreach (['street', 'house_number', 'postal_code', 'city', 'region'] as $field) {
                $table->string($field)->nullable();
            }
            $table->string('country_code', 2)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();
        });
        Schema::create('tours', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('songs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('title');
            $table->text('disambiguation')->nullable();
            $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('title');
            $table->string('type');
            $table->foreignId('venue_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->smallInteger('year')->nullable();
            $table->smallInteger('month')->nullable();
            $table->smallInteger('day')->nullable();
            $table->time('local_start_time')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('songs');
        Schema::dropIfExists('tours');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('artists');
    }
};
```

- [ ] Write `backend/app/Models/Artist.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['name', 'type', 'disambiguation'])]
class Artist extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'artists';
    public function eventLinks(): HasMany { return $this->hasMany(EventArtist::class); }
    public function tourLinks(): HasMany { return $this->hasMany(TourArtist::class); }
    public function entryLinks(): HasMany { return $this->hasMany(SetlistEntryArtist::class); }
    public function providerIds(): HasMany { return $this->hasMany(ArtistProviderId::class); }
}
```

- [ ] Write `backend/app/Models/Venue.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['name', 'street', 'house_number', 'postal_code', 'city', 'region', 'country_code', 'lat', 'lng', 'timezone'])]
class Venue extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'venues';
    protected function casts(): array { return ['lat' => 'decimal:7', 'lng' => 'decimal:7']; }
    public function events(): HasMany { return $this->hasMany(Event::class); }
    public function providerIds(): HasMany { return $this->hasMany(VenueProviderId::class); }
}
```

- [ ] Write `backend/app/Models/Tour.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['name'])]
class Tour extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'tours';
    public function artistLinks(): HasMany { return $this->hasMany(TourArtist::class); }
    public function eventLinks(): HasMany { return $this->hasMany(EventTour::class); }
    public function providerIds(): HasMany { return $this->hasMany(TourProviderId::class); }
}
```

- [ ] Write `backend/app/Models/Song.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['title', 'disambiguation'])]
class Song extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'songs';
    public function entries(): HasMany { return $this->hasMany(SetlistEntry::class); }
    public function providerIds(): HasMany { return $this->hasMany(SongProviderId::class); }
    public function links(): HasMany { return $this->hasMany(SongLink::class); }
}
```

- [ ] Write `backend/app/Models/Event.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['title', 'type', 'venue_id', 'year', 'month', 'day', 'local_start_time', 'timezone'])]
class Event extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'events';
    protected function casts(): array { return ['year' => 'integer', 'month' => 'integer', 'day' => 'integer']; }
    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function artistLinks(): HasMany { return $this->hasMany(EventArtist::class); }
    public function tourLinks(): HasMany { return $this->hasMany(EventTour::class); }
    public function attendances(): HasMany { return $this->hasMany(Attendance::class); }
    public function providerReferences(): HasMany { return $this->hasMany(EventProviderReference::class); }
}
```

- [ ] Write `backend/database/factories/ArtistFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<Artist> */
class ArtistFactory extends Factory
{
    protected $model = Artist::class;
    public function definition(): array
    {
        return [
            'name' => fake()->name(), 'type' => null, 'disambiguation' => null,
        ];
    }
}
```

- [ ] Write `backend/database/factories/VenueFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<Venue> */
class VenueFactory extends Factory
{
    protected $model = Venue::class;
    public function definition(): array
    {
        return [
            'name' => fake()->company(), 'street' => null, 'house_number' => null,
            'postal_code' => null, 'city' => null, 'region' => null, 'country_code' => null,
            'lat' => null, 'lng' => null, 'timezone' => null,
        ];
    }
}
```

- [ ] Write `backend/database/factories/TourFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<Tour> */
class TourFactory extends Factory
{
    protected $model = Tour::class;
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
        ];
    }
}
```

- [ ] Write `backend/database/factories/SongFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<Song> */
class SongFactory extends Factory
{
    protected $model = Song::class;
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true), 'disambiguation' => null,
        ];
    }
}
```

- [ ] Write `backend/database/factories/EventFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;
    public function definition(): array
    {
        return [
            'title' => fake()->words(4, true), 'type' => 'concert', 'venue_id' => null,
            'year' => null, 'month' => null, 'day' => null,
            'local_start_time' => null, 'timezone' => null,
        ];
    }
}
```

Country code and address columns preserve the confirmed values. ISO code, coordinate-pair, range, time-zone, and date validation belongs in future Laravel Form Requests. Do not add a country catalog, unused code-list configuration, or a save-time validator in this phase.


- [ ] **Step 4: Verify the task.**

```sh
php artisan test --compact tests/Feature/CatalogTest.php
vendor/bin/pint --dirty --format agent
```

Expected: all tests in this file pass. The final gate repeats them on PostgreSQL.

- [ ] **Step 5: Review this task’s listed files and run `git diff --check`.** If commit authorization is later given, stage only these files and use `feat: add event catalog models and factories`.


### Task 3: Catalog relationships as identified rows

**Files:** Create the catalog-relationship migration, `EventArtist.php`, `TourArtist.php`, `EventTour.php`, their factories, and `CatalogRelationshipsTest.php`. Modify `backend/tests/TestCase.php`.

**Interfaces:** Consumes Task 2 catalog models. Produces typed join models with their own identity and unique relationship pairs.

- [ ] **Step 1: Generate and write the failing tests.**

```sh
php artisan make:test --phpunit CatalogRelationshipsTest --no-interaction
```

`backend/tests/Feature/CatalogRelationshipsTest.php`:

```php
<?php
namespace Tests\Feature;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Tour;
use App\Models\EventArtist;
use App\Models\TourArtist;
use App\Models\EventTour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CatalogRelationshipsTest extends TestCase
{
    use RefreshDatabase;
    public function test_multiple_artists_and_optional_multiple_tours(): void
    {
        $event = Event::factory()->create();
        $this->assertSame(0, $event->tourLinks()->count());
        $artist = Artist::factory()->create();
        EventArtist::factory()->create(['event_id' => $event->id, 'artist_id' => $artist->id]);
        EventArtist::factory()->create(['event_id' => $event->id]);
        $tour = Tour::factory()->create();
        TourArtist::factory()->create(['tour_id' => $tour->id, 'artist_id' => $artist->id]);
        EventTour::factory()->create(['event_id' => $event->id, 'tour_id' => $tour->id]);
        EventTour::factory()->create(['event_id' => $event->id]);
        $this->assertSame(2, $event->artistLinks()->count());
        $this->assertSame(2, $event->tourLinks()->count());
        $this->assertSame(1, $tour->artistLinks()->count());
        $this->assertTrue($event->artistLinks()->first()->artist->is($artist));
    }
    public function test_relationship_pairs_are_unique(): void
    {
        foreach ([EventArtist::class, TourArtist::class, EventTour::class] as $class) {
            $row = $class::factory()->create();
            $copy = $row->getAttributes();
            unset($copy['id']);
            $copy['external_id'] = (string) \Illuminate\Support\Str::uuid7();
            $this->assertDatabaseRejects(fn () => \Illuminate\Support\Facades\DB::table($row->getTable())->insert($copy));
        }
    }
}
```

- [ ] **Step 2: Run the red check.**

```sh
php artisan test --compact tests/Feature/CatalogRelationshipsTest.php
```

Expected: missing model or table failure.

- [ ] **Step 3: Generate and implement the migration, models, and factories.**

Add this helper to the existing `Tests\TestCase`. A nested transaction/savepoint keeps a PostgreSQL constraint error from poisoning the enclosing test transaction. Only integrity SQLSTATE codes qualify.

```php
protected function assertDatabaseRejects(\Closure $write): void
{
    $error = null;
    try {
        \Illuminate\Support\Facades\DB::transaction($write);
    } catch (\Illuminate\Database\QueryException $exception) {
        $error = $exception;
    }
    $this->assertNotNull($error, 'The database accepted an invalid write.');
    $this->assertContains((string) $error->getCode(), ['23000', '23502', '23503', '23505', '23514']);
}
```


```sh
php artisan make:migration create_event_catalog_relationships --no-interaction
php artisan make:model EventArtist --factory --no-interaction
php artisan make:model TourArtist --factory --no-interaction
php artisan make:model EventTour --factory --no-interaction
```

- [ ] Write migration `backend/database/migrations/2026_10_22_000003_create_event_catalog_relationships.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_artists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('event_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->index()->constrained()->restrictOnDelete();
            $table->unique(['event_id', 'artist_id']);
            $table->integer('position')->nullable();
            $table->timestamps();
        });
        Schema::create('tour_artists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('tour_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->index()->constrained()->restrictOnDelete();
            $table->unique(['tour_id', 'artist_id']);
            $table->timestamps();
        });
        Schema::create('event_tours', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('event_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->index()->constrained()->restrictOnDelete();
            $table->unique(['event_id', 'tour_id']);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('event_tours');
        Schema::dropIfExists('tour_artists');
        Schema::dropIfExists('event_artists');
    }
};
```

- [ ] Write `backend/app/Models/EventArtist.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['event_id', 'artist_id', 'position'])]
class EventArtist extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'event_artists';
    protected function casts(): array { return ['position' => 'integer']; }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function artist(): BelongsTo { return $this->belongsTo(Artist::class); }
}
```

- [ ] Write `backend/database/factories/EventArtistFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\EventArtist;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<EventArtist> */
class EventArtistFactory extends Factory
{
    protected $model = EventArtist::class;
    public function definition(): array
    {
        return [
            'event_id' => \App\Models\Event::factory(),
            'artist_id' => \App\Models\Artist::factory(),
            'position' => null,
        ];
    }
}
```

- [ ] Write `backend/app/Models/TourArtist.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['tour_id', 'artist_id'])]
class TourArtist extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'tour_artists';
    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }
    public function artist(): BelongsTo { return $this->belongsTo(Artist::class); }
}
```

- [ ] Write `backend/database/factories/TourArtistFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\TourArtist;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<TourArtist> */
class TourArtistFactory extends Factory
{
    protected $model = TourArtist::class;
    public function definition(): array
    {
        return [
            'tour_id' => \App\Models\Tour::factory(),
            'artist_id' => \App\Models\Artist::factory(),
        ];
    }
}
```

- [ ] Write `backend/app/Models/EventTour.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['event_id', 'tour_id'])]
class EventTour extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'event_tours';
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }
}
```

- [ ] Write `backend/database/factories/EventTourFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\EventTour;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<EventTour> */
class EventTourFactory extends Factory
{
    protected $model = EventTour::class;
    public function definition(): array
    {
        return [
            'event_id' => \App\Models\Event::factory(),
            'tour_id' => \App\Models\Tour::factory(),
        ];
    }
}
```


- [ ] **Step 4: Verify the task.**

```sh
php artisan test --compact tests/Feature/CatalogRelationshipsTest.php
vendor/bin/pint --dirty --format agent
```

Expected: all tests in this file pass. The final gate repeats them on PostgreSQL.

- [ ] **Step 5: Review this task’s listed files and run `git diff --check`.** If commit authorization is later given, stage only these files and use `feat: add identified catalog relationships`.

### Task 4: Attendance storage and membership integrity

**Files:** Create the attendance migration, five named models and matching factories below, and `AttendanceRecordsTest.php`. Modify `User.php` to add its relationships.

**Interfaces:** Consumes `Event`, `User`, and Task 3’s database rejection assertion. Produces `Attendance::members(): HasMany`, `Attendance::owner(): BelongsTo`, and independent member flags/seats.

- [ ] **Step 1: Generate and write the failing tests.**

```sh
php artisan make:test --phpunit AttendanceRecordsTest --no-interaction
```

`backend/tests/Feature/AttendanceRecordsTest.php`:

```php
<?php
namespace Tests\Feature;
use App\Models\Attendance;
use App\Models\AttendanceMember;
use App\Models\AttendanceMemberSeat;
use App\Models\AttendanceInvitation;
use App\Models\AttendanceLink;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class AttendanceRecordsTest extends TestCase
{
    use RefreshDatabase;
    public function test_factory_reuses_event_and_creates_exactly_one_owner_membership(): void
    {
        $event = Event::factory()->create();
        $owner = User::factory()->create();
        $attendance = Attendance::factory()->create(['event_id' => $event->id, 'owner_user_id' => $owner->id]);
        $this->assertSame(1, Event::count());
        $this->assertSame(1, User::count());
        $member = $attendance->members()->sole();
        $this->assertTrue($member->user->is($owner));
        $this->assertSame($event->id, $member->event_id);
        $this->assertFalse($member->interested);
        $this->assertFalse($member->tickets_bought);
    }
    public function test_membership_foreign_key_and_event_uniqueness_are_database_rules(): void
    {
        $a = Attendance::factory()->create();
        $b = Attendance::factory()->create(['event_id' => $a->event_id]);
        $row = $a->members()->sole()->getAttributes();
        unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $row['attendance_id'] = $b->id;
        $this->assertDatabaseRejects(fn () => DB::table('attendance_members')->insert($row));
        $row['user_id'] = User::factory()->create()->id;
        $row['event_id'] = Event::factory()->create()->id;
        $this->assertDatabaseRejects(fn () => DB::table('attendance_members')->insert($row));
    }
    public function test_flags_seats_and_pending_invites_are_independent(): void
    {
        $a = Attendance::factory()->create();
        $owner = $a->members()->sole();
        $guest = AttendanceMember::factory()->create(['attendance_id' => $a->id, 'interested' => true]);
        AttendanceMemberSeat::factory()->count(2)->create(['attendance_member_id' => $guest->id]);
        $owner->update(['tickets_bought' => true]);
        $this->assertFalse($owner->fresh()->interested);
        $this->assertFalse($guest->fresh()->tickets_bought);
        $this->assertSame(2, $guest->seats()->count());
        $before = $a->members()->count();
        $invite = AttendanceInvitation::factory()->create(['attendance_id' => $a->id]);
        $this->assertSame($before, $a->members()->count());
        $row = $invite->getAttributes(); unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('attendance_invitations')->insert($row));
        $invite->update(['status' => 'declined']);
        AttendanceInvitation::factory()->create([
            'attendance_id' => $a->id, 'recipient_user_id' => $invite->recipient_user_id,
        ]);
        $this->assertSame('other', AttendanceLink::factory()->create(['attendance_id' => $a->id])->type);
    }
    public function test_failed_owner_membership_rolls_back_attendance_factory(): void
    {
        $event = Event::factory()->create();
        $owner = User::factory()->create();
        Attendance::factory()->create(['event_id' => $event->id, 'owner_user_id' => $owner->id]);
        $this->assertDatabaseRejects(fn () => Attendance::factory()->create([
            'event_id' => $event->id, 'owner_user_id' => $owner->id,
        ]));
        $this->assertSame(1, Attendance::count());
    }
}
```

- [ ] **Step 2: Run the red check.**

```sh
php artisan test --compact tests/Feature/AttendanceRecordsTest.php
```

Expected: missing model or table failure.

- [ ] **Step 3: Generate and implement the files below.**


```sh
php artisan make:migration create_attendance_records --no-interaction
php artisan make:model Attendance --factory --no-interaction
php artisan make:model AttendanceMember --factory --no-interaction
php artisan make:model AttendanceMemberSeat --factory --no-interaction
php artisan make:model AttendanceInvitation --factory --no-interaction
php artisan make:model AttendanceLink --factory --no-interaction
```

- [ ] Write migration `backend/database/migrations/2026_10_22_000004_create_attendance_records.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('event_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->unique(['id', 'event_id']);
            $table->timestamps();
        });
        Schema::create('attendance_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_id')->index();
            $table->foreignId('event_id')->index();
            $table->foreignId('user_id')->index()->constrained()->restrictOnDelete();
            $table->boolean('interested')->default(false);
            $table->boolean('tickets_bought')->default(false);
            $table->unique(['user_id', 'event_id']);
            $table->foreign(['attendance_id', 'event_id'], 'attendance_members_attendance_event_fk')
                ->references(['id', 'event_id'])->on('attendances')->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('attendance_member_seats', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_member_id')->index()->constrained()->cascadeOnDelete();
            $table->string('section_label')->nullable();
            $table->string('row_label')->nullable();
            $table->string('seat_label')->nullable();
            $table->text('label')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('inviter_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('attendance_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_id')->index()->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->text('label')->nullable();
            $table->string('type')->default('other');
            $table->timestamps();
        });
        DB::statement("CREATE UNIQUE INDEX attendance_invitations_pending_unique ON attendance_invitations (attendance_id, recipient_user_id) WHERE status = 'pending'");
    }
    public function down(): void
    {
        Schema::dropIfExists('attendance_links');
        Schema::dropIfExists('attendance_invitations');
        Schema::dropIfExists('attendance_member_seats');
        Schema::dropIfExists('attendance_members');
        Schema::dropIfExists('attendances');
    }
};
```

- [ ] Write `backend/app/Models/Attendance.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['event_id', 'owner_user_id', 'notes'])]
class Attendance extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'attendances';
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function members(): HasMany { return $this->hasMany(AttendanceMember::class); }
    public function invitations(): HasMany { return $this->hasMany(AttendanceInvitation::class); }
    public function links(): HasMany { return $this->hasMany(AttendanceLink::class); }
    public function entries(): HasMany { return $this->hasMany(SetlistEntry::class)->orderBy('position'); }
}
```

- [ ] Write `backend/app/Models/AttendanceMember.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['attendance_id', 'event_id', 'user_id', 'interested', 'tickets_bought'])]
class AttendanceMember extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'attendance_members';
    protected $attributes = ['interested' => false, 'tickets_bought' => false];
    protected function casts(): array { return ['interested' => 'boolean', 'tickets_bought' => 'boolean']; }
    public function attendance(): BelongsTo { return $this->belongsTo(Attendance::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function seats(): HasMany { return $this->hasMany(AttendanceMemberSeat::class); }
}
```

- [ ] Write `backend/app/Models/AttendanceMemberSeat.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['attendance_member_id', 'section_label', 'row_label', 'seat_label', 'label'])]
class AttendanceMemberSeat extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'attendance_member_seats';
    public function member(): BelongsTo { return $this->belongsTo(AttendanceMember::class, 'attendance_member_id'); }
}
```

- [ ] Write `backend/app/Models/AttendanceInvitation.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['attendance_id', 'inviter_user_id', 'recipient_user_id', 'status'])]
class AttendanceInvitation extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'attendance_invitations';
    protected $attributes = ['status' => 'pending'];
    public function attendance(): BelongsTo { return $this->belongsTo(Attendance::class); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'inviter_user_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_user_id'); }
}
```

- [ ] Write `backend/app/Models/AttendanceLink.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['attendance_id', 'url', 'label', 'type'])]
class AttendanceLink extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'attendance_links';
    protected $attributes = ['type' => 'other'];
    public function attendance(): BelongsTo { return $this->belongsTo(Attendance::class); }
}
```

- [ ] Add these typed relationships inside `backend/app/Models/User.php`. They expose data traversal, not permission to view another User’s Attendance.

```php
public function attendanceMemberships(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(AttendanceMember::class);
}
public function ownedAttendances(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Attendance::class, 'owner_user_id');
}
public function sentAttendanceInvitations(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(AttendanceInvitation::class, 'inviter_user_id');
}
public function receivedAttendanceInvitations(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(AttendanceInvitation::class, 'recipient_user_id');
}
```

- [ ] Write `backend/database/factories/AttendanceFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<Attendance> */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;
    public function definition(): array
    {
        return [
            'event_id' => \App\Models\Event::factory(),
            'owner_user_id' => \App\Models\User::factory(),
            'notes' => null,
        ];
    }
    public function configure(): static
    {
        return $this->afterCreating(function (Attendance $attendance): void {
            \App\Models\AttendanceMember::factory()->create([
                'attendance_id' => $attendance->id, 'event_id' => $attendance->event_id,
                'user_id' => $attendance->owner_user_id,
            ]);
        });
    }
    public function create(mixed $attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null): \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => parent::create($attributes, $parent));
    }
}
```

- [ ] Write `backend/database/factories/AttendanceMemberFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\AttendanceMember;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<AttendanceMember> */
class AttendanceMemberFactory extends Factory
{
    protected $model = AttendanceMember::class;
    public function definition(): array
    {
        return [
            'attendance_id' => \App\Models\Attendance::factory(),
            'event_id' => fn (array $attributes): int => \App\Models\Attendance::findOrFail($attributes['attendance_id'])->event_id,
            'user_id' => \App\Models\User::factory(),
            'interested' => false, 'tickets_bought' => false,
        ];
    }
}
```

- [ ] Write `backend/database/factories/AttendanceMemberSeatFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\AttendanceMemberSeat;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<AttendanceMemberSeat> */
class AttendanceMemberSeatFactory extends Factory
{
    protected $model = AttendanceMemberSeat::class;
    public function definition(): array
    {
        return [
            'attendance_member_id' => \App\Models\AttendanceMember::factory(),
            'section_label' => null, 'row_label' => null, 'seat_label' => null,
            'label' => 'General admission',
        ];
    }
}
```

- [ ] Write `backend/database/factories/AttendanceInvitationFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\AttendanceInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<AttendanceInvitation> */
class AttendanceInvitationFactory extends Factory
{
    protected $model = AttendanceInvitation::class;
    public function definition(): array
    {
        return [
            'attendance_id' => \App\Models\Attendance::factory(),
            'inviter_user_id' => fn (array $attributes): int => \App\Models\Attendance::findOrFail($attributes['attendance_id'])->owner_user_id,
            'recipient_user_id' => \App\Models\User::factory(), 'status' => 'pending',
        ];
    }
}
```

- [ ] Write `backend/database/factories/AttendanceLinkFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\AttendanceLink;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<AttendanceLink> */
class AttendanceLinkFactory extends Factory
{
    protected $model = AttendanceLink::class;
    public function definition(): array
    {
        return [
            'attendance_id' => \App\Models\Attendance::factory(),
            'url' => 'https://example.com/album', 'label' => null, 'type' => 'other',
        ];
    }
}
```

The factory transaction is test-data construction, not a production Attendance-creation API. Invitation status is stored but never changes membership automatically. Owner transfer, membership removal eligibility, and invitation acceptance remain separate workflow work.


- [ ] **Step 4: Run this file and format the changed PHP.**

```sh
php artisan test --compact tests/Feature/AttendanceRecordsTest.php
vendor/bin/pint --dirty --format agent
```

Expected: this test file passes.

- [ ] **Step 5: Review the task diff and run `git diff --check`.** Commit only if separately authorized; stage only the task’s files and use `feat: add collaborative attendance data model`.


### Task 5: Flat relational setlists

**Files:** Create the setlist migration, `SetlistEntry.php`, `SetlistEntryArtist.php`, their factories, and `SetlistRecordsTest.php`.

**Interfaces:** Consumes Attendance and catalog models. Produces ordered `Attendance::entries()` and `SetlistEntry::artistLinks()` with repeatable Song references.

- [ ] **Step 1: Generate and write the failing tests.**

```sh
php artisan make:test --phpunit SetlistRecordsTest --no-interaction
```

`backend/tests/Feature/SetlistRecordsTest.php`:

```php
<?php
namespace Tests\Feature;
use App\Models\Artist;
use App\Models\Attendance;
use App\Models\Song;
use App\Models\SetlistEntry;
use App\Models\SetlistEntryArtist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class SetlistRecordsTest extends TestCase
{
    use RefreshDatabase;
    public function test_repeated_song_occurrences_live_tape_and_multiple_performers(): void
    {
        $a = Attendance::factory()->create();
        $song = Song::factory()->create();
        $live = SetlistEntry::factory()->create(['attendance_id' => $a->id, 'song_id' => $song->id, 'position' => 1]);
        SetlistEntry::factory()->tape()->create(['attendance_id' => $a->id, 'song_id' => $song->id, 'position' => 2]);
        SetlistEntry::factory()->note('Encore')->create(['attendance_id' => $a->id, 'position' => 5]);
        SetlistEntry::factory()->unidentified('Unknown instrumental')->create(['attendance_id' => $a->id, 'position' => 8]);
        SetlistEntryArtist::factory()->count(2)->create(['setlist_entry_id' => $live->id]);
        $this->assertSame([1, 2, 5, 8], $a->entries()->pluck('position')->all());
        $this->assertSame(2, $live->artistLinks()->count());
        $this->assertSame(1, $song->entries()->where('type', 'song')->where('is_tape', false)->count());
        $this->assertSame(2, $song->entries()->count());
    }
    public function test_song_queries_find_covers_across_events_by_identity(): void
    {
        $song = Song::factory()->create();
        foreach ([Attendance::factory()->create(), Attendance::factory()->create()] as $a) {
            $entry = SetlistEntry::factory()->create(['attendance_id' => $a->id, 'song_id' => $song->id]);
            SetlistEntryArtist::factory()->create(['setlist_entry_id' => $entry->id]);
        }
        $events = DB::table('setlist_entries')->join('attendances', 'attendances.id', '=', 'setlist_entries.attendance_id')
            ->where('setlist_entries.song_id', $song->id)->where('is_tape', false)
            ->distinct()->count('attendances.event_id');
        $this->assertSame(2, $events);
    }
    public function test_position_and_performer_pairs_have_database_uniqueness(): void
    {
        $entry = SetlistEntry::factory()->create();
        $row = $entry->getAttributes(); unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('setlist_entries')->insert($row));
        $link = SetlistEntryArtist::factory()->create(['setlist_entry_id' => $entry->id]);
        $row = $link->getAttributes(); unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('setlist_entry_artists')->insert($row));
    }
}
```

- [ ] **Step 2: Run the red check.**

```sh
php artisan test --compact tests/Feature/SetlistRecordsTest.php
```

Expected: missing model or table failure.

- [ ] **Step 3: Generate and implement the files below.**


```sh
php artisan make:migration create_setlist_records --no-interaction
php artisan make:model SetlistEntry --factory --no-interaction
php artisan make:model SetlistEntryArtist --factory --no-interaction
```

- [ ] Write migration `backend/database/migrations/2026_10_22_000005_create_setlist_records.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('setlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->integer('position');
            $table->string('type');
            $table->boolean('is_tape')->default(false);
            $table->text('display_text')->nullable();
            $table->text('notes')->nullable();
            $table->unique(['attendance_id', 'position']);
            $table->timestamps();
        });
        Schema::create('setlist_entry_artists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('setlist_entry_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->index()->constrained()->restrictOnDelete();
            $table->unique(['setlist_entry_id', 'artist_id']);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('setlist_entry_artists');
        Schema::dropIfExists('setlist_entries');
    }
};
```

- [ ] Write `backend/app/Models/SetlistEntry.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['attendance_id', 'song_id', 'position', 'type', 'is_tape', 'display_text', 'notes'])]
class SetlistEntry extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'setlist_entries';
    protected $attributes = ['type' => 'song', 'is_tape' => false];
    protected function casts(): array { return ['position' => 'integer', 'is_tape' => 'boolean']; }
    public function attendance(): BelongsTo { return $this->belongsTo(Attendance::class); }
    public function song(): BelongsTo { return $this->belongsTo(Song::class); }
    public function artistLinks(): HasMany { return $this->hasMany(SetlistEntryArtist::class); }
}
```

- [ ] Write `backend/app/Models/SetlistEntryArtist.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['setlist_entry_id', 'artist_id'])]
class SetlistEntryArtist extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'setlist_entry_artists';
    public function entry(): BelongsTo { return $this->belongsTo(SetlistEntry::class, 'setlist_entry_id'); }
    public function artist(): BelongsTo { return $this->belongsTo(Artist::class); }
}
```

- [ ] Write `backend/database/factories/SetlistEntryFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\SetlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<SetlistEntry> */
class SetlistEntryFactory extends Factory
{
    protected $model = SetlistEntry::class;
    public function definition(): array
    {
        return [
            'attendance_id' => \App\Models\Attendance::factory(),
            'song_id' => \App\Models\Song::factory(),
            'position' => fake()->unique()->numberBetween(1, 2000000000),
             'type' => 'song', 'is_tape' => false, 'display_text' => null, 'notes' => null,
        ];
    }
    public function note(string $text = 'Intermission'): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'note', 'song_id' => null, 'is_tape' => false, 'display_text' => $text,
        ]);
    }
    public function unidentified(string $text): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'song', 'song_id' => null, 'display_text' => $text]);
    }
    public function tape(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'song', 'is_tape' => true]);
    }
}
```

- [ ] Write `backend/database/factories/SetlistEntryArtistFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\SetlistEntryArtist;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<SetlistEntryArtist> */
class SetlistEntryArtistFactory extends Factory
{
    protected $model = SetlistEntryArtist::class;
    public function definition(): array
    {
        return [
            'setlist_entry_id' => \App\Models\SetlistEntry::factory(),
            'artist_id' => \App\Models\Artist::factory(),
        ];
    }
}
```

No production reorder method is added here. Future reorder workflows must use one transaction and collision-free temporary positive positions before assigning final positions. Positions must be positive when the workflow commits. The request/workflow layer will enforce position and entry-content rules; the current database schema enforces position uniqueness. Last-save-wins requires no locking/version column in this delivery.


- [ ] **Step 4: Run this file and format the changed PHP.**

```sh
php artisan test --compact tests/Feature/SetlistRecordsTest.php
vendor/bin/pint --dirty --format agent
```

Expected: this test file passes.

- [ ] **Step 5: Review the task diff and run `git diff --check`.** Commit only if separately authorized; stage only the task’s files and use `feat: add normalized attendance setlist records`.

### Task 6: Provider identities and source associations

**Files:** Create `backend/database/migrations/2026_10_22_000006_create_provider_references.php`, `ArtistProviderId.php`, `VenueProviderId.php`, `TourProviderId.php`, `SongProviderId.php`, `EventProviderReference.php`, `SongLink.php` under `backend/app/Models/`, each matching factory, and `backend/tests/Feature/ProviderReferencesTest.php`.

**Interfaces:** Consumes catalog models and their `providerIds()`, `Event::providerReferences()`, and `Song::links()` relationships. Produces string-based provider references, database uniqueness for single-performance claims, and optional playable Song links. No HTTP integration is consumed or produced.

- [ ] **Step 1: Generate and write the failing test.**

```sh
php artisan make:test --phpunit ProviderReferencesTest --no-interaction
```

```php
<?php
namespace Tests\Feature;
use App\Models\ArtistProviderId;
use App\Models\VenueProviderId;
use App\Models\TourProviderId;
use App\Models\SongProviderId;
use App\Models\EventProviderReference;
use App\Models\SongLink;
use App\Models\Event;
use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class ProviderReferencesTest extends TestCase
{
    use RefreshDatabase;
    public function test_identity_resources_cannot_claim_two_parents(): void
    {
        foreach ([ArtistProviderId::class, VenueProviderId::class, TourProviderId::class, SongProviderId::class] as $class) {
            $one = $class::factory()->create();
            $two = $class::factory()->make()->getAttributes();
            $two['external_id'] = (string) Str::uuid7();
            $two['provider'] = $one->provider;
            $two['resource_type'] = $one->resource_type;
            $two['provider_resource_id'] = $one->provider_resource_id;
            $this->assertDatabaseRejects(fn () => DB::table($one->getTable())->insert($two));
        }
    }
    public function test_provider_ids_are_strings_and_multiple_handles_can_share_a_parent(): void
    {
        $one = VenueProviderId::factory()->create(['provider_resource_id' => '0000123']);
        $two = VenueProviderId::factory()->create([
            'venue_id' => $one->venue_id, 'provider_resource_id' => str_repeat('a', 512),
        ]);
        $this->assertSame('0000123', $one->fresh()->provider_resource_id);
        $this->assertSame(str_repeat('a', 512), $two->fresh()->provider_resource_id);
        $this->assertSame(2, $one->venue->providerIds()->count());
        $this->assertNotSame($one->external_id, $one->provider_resource_id);
    }
    public function test_single_performance_reference_cannot_be_reused_on_another_event(): void
    {
        $one = EventProviderReference::factory()->create();
        $row = $one->getAttributes(); unset($row['id']);
        $row['event_id'] = Event::factory()->create()->id;
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('event_provider_references')->insert($row));
    }
    public function test_multiday_reference_and_playable_link_are_parent_scoped(): void
    {
        $one = EventProviderReference::factory()->create(['provider' => 'musicbrainz', 'resource_type' => 'event']);
        EventProviderReference::factory()->create([
            'provider' => $one->provider, 'resource_type' => $one->resource_type,
            'provider_resource_id' => $one->provider_resource_id,
        ]);
        $link = SongLink::factory()->create();
        SongLink::factory()->create(['song_id' => $link->song_id]);
        SongLink::factory()->create(['provider_resource_id' => $link->provider_resource_id]);
        $this->assertSame(2, $link->song->links()->count());
        $row = $link->getAttributes(); unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('song_links')->insert($row));
    }
    public function test_openstreetmap_resource_type_is_part_of_identity(): void
    {
        $one = VenueProviderId::factory()->create(['provider' => 'openstreetmap', 'resource_type' => 'node', 'provider_resource_id' => '123']);
        VenueProviderId::factory()->create(['provider' => 'openstreetmap', 'resource_type' => 'way', 'provider_resource_id' => '123']);
        $this->assertSame(2, VenueProviderId::where('provider', 'openstreetmap')->count());
    }
}
```
- [ ] **Step 2: Run the red check.**

```sh
php artisan test --compact tests/Feature/ProviderReferencesTest.php
```
Expected: missing provider model/table failure.

- [ ] **Step 3: Generate the files.**

```sh
php artisan make:migration create_provider_references --no-interaction
php artisan make:model ArtistProviderId --factory --no-interaction
php artisan make:model VenueProviderId --factory --no-interaction
php artisan make:model TourProviderId --factory --no-interaction
php artisan make:model SongProviderId --factory --no-interaction
php artisan make:model EventProviderReference --factory --no-interaction
php artisan make:model SongLink --factory --no-interaction
```

- [ ] Write `backend/database/migrations/2026_10_22_000006_create_provider_references.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('artist_provider_ids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('artist_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['provider', 'resource_type', 'provider_resource_id'], 'artist_provider_ids_resource_unique');
            $table->timestamps();
        });
        Schema::create('venue_provider_ids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('venue_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['provider', 'resource_type', 'provider_resource_id'], 'venue_provider_ids_resource_unique');
            $table->timestamps();
        });
        Schema::create('tour_provider_ids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('tour_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['provider', 'resource_type', 'provider_resource_id'], 'tour_provider_ids_resource_unique');
            $table->timestamps();
        });
        Schema::create('song_provider_ids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('song_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['provider', 'resource_type', 'provider_resource_id'], 'song_provider_ids_resource_unique');
            $table->timestamps();
        });
        Schema::create('event_provider_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('event_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['event_id', 'provider', 'resource_type', 'provider_resource_id'], 'event_provider_references_resource_unique');
            $table->timestamps();
        });
        Schema::create('song_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('song_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->text('url')->nullable();
            $table->text('label')->nullable();
            $table->unique(['song_id', 'provider', 'resource_type', 'provider_resource_id'], 'song_links_resource_unique');
            $table->timestamps();
        });
        DB::statement("CREATE UNIQUE INDEX event_performance_resource_unique ON event_provider_references (provider, resource_type, provider_resource_id) WHERE provider = 'setlist_fm' AND resource_type = 'setlist'");
    }
    public function down(): void
    {
        Schema::dropIfExists('song_links');
        Schema::dropIfExists('event_provider_references');
        Schema::dropIfExists('song_provider_ids');
        Schema::dropIfExists('tour_provider_ids');
        Schema::dropIfExists('venue_provider_ids');
        Schema::dropIfExists('artist_provider_ids');
    }
};
```

- [ ] Write `backend/app/Models/ArtistProviderId.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['artist_id', 'provider', 'resource_type', 'provider_resource_id'])]
class ArtistProviderId extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'artist_provider_ids';
    public function artist(): BelongsTo { return $this->belongsTo(Artist::class); }
}
```

- [ ] Write `backend/database/factories/ArtistProviderIdFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\ArtistProviderId;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<ArtistProviderId> */
class ArtistProviderIdFactory extends Factory
{
    protected $model = ArtistProviderId::class;
    public function definition(): array
    {
        return [
            'artist_id' => \App\Models\Artist::factory(),
            'provider' => 'musicbrainz', 'resource_type' => 'artist',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
```

- [ ] Write `backend/app/Models/VenueProviderId.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['venue_id', 'provider', 'resource_type', 'provider_resource_id'])]
class VenueProviderId extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'venue_provider_ids';
    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
}
```

- [ ] Write `backend/database/factories/VenueProviderIdFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\VenueProviderId;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<VenueProviderId> */
class VenueProviderIdFactory extends Factory
{
    protected $model = VenueProviderId::class;
    public function definition(): array
    {
        return [
            'venue_id' => \App\Models\Venue::factory(),
            'provider' => 'geoapify', 'resource_type' => 'place',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
```

- [ ] Write `backend/app/Models/TourProviderId.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['tour_id', 'provider', 'resource_type', 'provider_resource_id'])]
class TourProviderId extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'tour_provider_ids';
    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }
}
```

- [ ] Write `backend/database/factories/TourProviderIdFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\TourProviderId;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<TourProviderId> */
class TourProviderIdFactory extends Factory
{
    protected $model = TourProviderId::class;
    public function definition(): array
    {
        return [
            'tour_id' => \App\Models\Tour::factory(),
            'provider' => 'musicbrainz', 'resource_type' => 'series',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
```

- [ ] Write `backend/app/Models/SongProviderId.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['song_id', 'provider', 'resource_type', 'provider_resource_id'])]
class SongProviderId extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'song_provider_ids';
    public function song(): BelongsTo { return $this->belongsTo(Song::class); }
}
```

- [ ] Write `backend/database/factories/SongProviderIdFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\SongProviderId;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<SongProviderId> */
class SongProviderIdFactory extends Factory
{
    protected $model = SongProviderId::class;
    public function definition(): array
    {
        return [
            'song_id' => \App\Models\Song::factory(),
            'provider' => 'musicbrainz', 'resource_type' => 'work',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
```

- [ ] Write `backend/app/Models/EventProviderReference.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['event_id', 'provider', 'resource_type', 'provider_resource_id'])]
class EventProviderReference extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'event_provider_references';
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
}
```

- [ ] Write `backend/database/factories/EventProviderReferenceFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\EventProviderReference;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<EventProviderReference> */
class EventProviderReferenceFactory extends Factory
{
    protected $model = EventProviderReference::class;
    public function definition(): array
    {
        return [
            'event_id' => \App\Models\Event::factory(),
            'provider' => 'setlist_fm', 'resource_type' => 'setlist',
            'provider_resource_id' => (string) fake()->uuid(),
        ];
    }
}
```

- [ ] Write `backend/app/Models/SongLink.php`:

```php
<?php
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['song_id', 'provider', 'resource_type', 'provider_resource_id', 'url', 'label'])]
class SongLink extends Model
{
    use HasFactory, HasPublicId;
    protected $table = 'song_links';
    public function song(): BelongsTo { return $this->belongsTo(Song::class); }
}
```

- [ ] Write `backend/database/factories/SongLinkFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\SongLink;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<SongLink> */
class SongLinkFactory extends Factory
{
    protected $model = SongLink::class;
    public function definition(): array
    {
        return [
            'song_id' => \App\Models\Song::factory(),
            'provider' => 'spotify', 'resource_type' => 'track',
            'provider_resource_id' => (string) fake()->uuid(),
            'url' => null, 'label' => null,
        ];
    }
}
```

The Tour factory's MusicBrainz series ID is synthetic test data. This does not make MusicBrainz a tour-discovery provider or invent a real tour ID from a label.

The conditional index is valid on both engines. Do not replace it with an Eloquent preflight query; such a query cannot protect concurrent claims. Do not make all Event references globally unique, because multi-day references must remain shareable.

The future autocomplete workflow stores only the stable setlist.fm ID with `provider = setlist_fm` and `resource_type = setlist`. It never stores source revision IDs or performs refresh, update comparison, or synchronization. The Event-reference model remains an ordinary persistence model; supported provider/resource combinations will be checked by future request/provider workflows, not by a save hook.

- [ ] **Step 4: Run the tests and format.**

```sh
php artisan test --compact tests/Feature/ProviderReferencesTest.php
vendor/bin/pint --dirty --format agent
```

Expected: tests pass. Task 7 proves the same indexes on PostgreSQL and checks duplicate foreign-key association rows.

- [ ] **Step 5: Review the task diff and run `git diff --check`.** Commit only with separate authorization; stage only this task's files and use `feat: add provider identities and source references`.

### Task 7: Complete graph, rollback, seed example, and both database gates

**Files:** Create `backend/tests/Feature/DomainGraphTest.php`, `backend/tests/Feature/DomainMigrationTest.php`, and `backend/database/seeders/LiveEventSeeder.php`. Do not change `DatabaseSeeder` or the database default.

**Interfaces:** Consumes all 22 concrete models and factories. Produces proof that the complete graph can be created, queried, constrained, and deleted on both engines. Produces optional `LiveEventSeeder::run(): void`; it is not invoked by default setup or default seeds.

- [ ] **Step 1: Generate the test and seeder files.**

```sh
php artisan make:test --phpunit DomainGraphTest --no-interaction
php artisan make:seeder LiveEventSeeder --no-interaction
```

- [ ] **Step 2: Write these graph tests.**

`backend/tests/Feature/DomainGraphTest.php`:

```php
<?php
namespace Tests\Feature;
use App\Models\Attendance;
use App\Models\AttendanceMember;
use App\Models\AttendanceMemberSeat;
use App\Models\AttendanceInvitation;
use App\Models\AttendanceLink;
use App\Models\SetlistEntry;
use App\Models\SetlistEntryArtist;
use App\Models\Song;
use App\Models\SongLink;
use App\Models\SongProviderId;
use App\Models\EventArtist;
use App\Models\EventTour;
use App\Models\TourArtist;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
class DomainGraphTest extends TestCase
{
    use RefreshDatabase;
    #[DataProvider('models')]
    public function test_every_model_factory_has_independent_immutable_public_identity(string $class): void
    {
        $row = $class::factory()->create();
        $this->assertIsInt($row->id);
        $this->assertTrue(Str::isUuid($row->external_id));
        $this->assertSame('external_id', $row->getRouteKeyName());
        $this->assertTrue($row->resolveRouteBinding($row->external_id)->is($row));
        $uuid = $row->external_id;
        $row->save();
        $this->assertSame($uuid, $row->fresh()->external_id);
        $this->assertNull($row->replicate()->external_id);
        $this->assertDatabaseRejects(fn () => $class::factory()->create(['external_id' => $uuid]));
        $row->external_id = (string) Str::uuid7();
        try {
            $row->save();
            $this->fail('Changing public identity was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('external_id', $exception->errors());
        }
    }
    public static function models(): array
    {
        return [
            'User' => [\App\Models\User::class],
            'Artist' => [\App\Models\Artist::class],
            'Venue' => [\App\Models\Venue::class],
            'Tour' => [\App\Models\Tour::class],
            'Song' => [\App\Models\Song::class],
            'Event' => [\App\Models\Event::class],
            'EventArtist' => [\App\Models\EventArtist::class],
            'TourArtist' => [\App\Models\TourArtist::class],
            'EventTour' => [\App\Models\EventTour::class],
            'Attendance' => [\App\Models\Attendance::class],
            'AttendanceMember' => [\App\Models\AttendanceMember::class],
            'AttendanceMemberSeat' => [\App\Models\AttendanceMemberSeat::class],
            'AttendanceInvitation' => [\App\Models\AttendanceInvitation::class],
            'AttendanceLink' => [\App\Models\AttendanceLink::class],
            'SetlistEntry' => [\App\Models\SetlistEntry::class],
            'SetlistEntryArtist' => [\App\Models\SetlistEntryArtist::class],
            'ArtistProviderId' => [\App\Models\ArtistProviderId::class],
            'VenueProviderId' => [\App\Models\VenueProviderId::class],
            'TourProviderId' => [\App\Models\TourProviderId::class],
            'SongProviderId' => [\App\Models\SongProviderId::class],
            'EventProviderReference' => [\App\Models\EventProviderReference::class],
            'SongLink' => [\App\Models\SongLink::class],
        ];
    }
    public function test_attendance_deletion_removes_owned_rows_and_preserves_other_attendance(): void
    {
        $a = Attendance::factory()->create();
        $other = Attendance::factory()->create(['event_id' => $a->event_id]);
        $member = $a->members()->sole();
        $seat = AttendanceMemberSeat::factory()->create(['attendance_member_id' => $member->id]);
        $invite = AttendanceInvitation::factory()->create(['attendance_id' => $a->id]);
        $link = AttendanceLink::factory()->create(['attendance_id' => $a->id]);
        $entry = SetlistEntry::factory()->create(['attendance_id' => $a->id]);
        $credit = SetlistEntryArtist::factory()->create(['setlist_entry_id' => $entry->id]);
        $song = $entry->song;
        $artist = $credit->artist;
        $event = $a->event;
        $a->delete();
        foreach ([$a, $member, $seat, $invite, $link, $entry, $credit] as $deleted) {
            $this->assertDatabaseMissing($deleted->getTable(), ['id' => $deleted->id]);
        }
        foreach ([$other, $song, $artist, $event] as $kept) {
            $this->assertDatabaseHas($kept->getTable(), ['id' => $kept->id]);
        }
    }
    public function test_usage_restricts_catalog_event_and_account_deletion(): void
    {
        $venue = Venue::factory()->create();
        $a = Attendance::factory()->create();
        $a->event->update(['venue_id' => $venue->id]);
        $entry = SetlistEntry::factory()->create(['attendance_id' => $a->id]);
        $credit = SetlistEntryArtist::factory()->create(['setlist_entry_id' => $entry->id]);
        $tourLink = EventTour::factory()->create(['event_id' => $a->event_id]);
        foreach ([$venue, $a->event, $entry->song, $credit->artist, $tourLink->tour, $a->owner] as $kept) {
            $this->assertDatabaseRejects(fn () => $kept->delete());
            $this->assertDatabaseHas($kept->getTable(), ['id' => $kept->id]);
        }
    }
    public function test_provider_metadata_and_owned_catalog_joins_do_not_block_deletion(): void
    {
        foreach ([
            [\App\Models\ArtistProviderId::class, 'artist'],
            [\App\Models\VenueProviderId::class, 'venue'],
            [\App\Models\TourProviderId::class, 'tour'],
            [\App\Models\SongProviderId::class, 'song'],
            [\App\Models\EventProviderReference::class, 'event'],
            [\App\Models\SongLink::class, 'song'],
        ] as [$class, $relation]) {
            $reference = $class::factory()->create();
            $reference->{$relation}->delete();
            $this->assertDatabaseMissing($reference->getTable(), ['id' => $reference->id]);
        }
        $eventArtist = EventArtist::factory()->create();
        $eventTour = EventTour::factory()->create(['event_id' => $eventArtist->event_id]);
        $eventArtist->event->delete();
        $this->assertDatabaseMissing('event_artists', ['id' => $eventArtist->id]);
        $this->assertDatabaseMissing('event_tours', ['id' => $eventTour->id]);
        $this->assertDatabaseHas('artists', ['id' => $eventArtist->artist_id]);
        $tourArtist = TourArtist::factory()->create();
        $tourArtist->tour->delete();
        $this->assertDatabaseMissing('tour_artists', ['id' => $tourArtist->id]);
        $this->assertDatabaseHas('artists', ['id' => $tourArtist->artist_id]);
    }
    public function test_removing_an_ordinary_member_removes_only_their_seats(): void
    {
        $a = Attendance::factory()->create();
        $guest = AttendanceMember::factory()->create(['attendance_id' => $a->id]);
        $seat = AttendanceMemberSeat::factory()->create(['attendance_member_id' => $guest->id]);
        $entry = SetlistEntry::factory()->create(['attendance_id' => $a->id]);
        $guest->delete();
        $this->assertDatabaseMissing('attendance_member_seats', ['id' => $seat->id]);
        $this->assertDatabaseHas('setlist_entries', ['id' => $entry->id]);
        $this->assertSame(1, $a->members()->count());
    }
    public function test_declared_foreign_keys_are_real_database_constraints(): void
    {
        $expected = [
            'events' => [['venue_id']],
            'event_artists' => [['event_id'], ['artist_id']],
            'tour_artists' => [['tour_id'], ['artist_id']],
            'event_tours' => [['event_id'], ['tour_id']],
            'attendances' => [['event_id'], ['owner_user_id']],
            'attendance_members' => [['attendance_id', 'event_id'], ['user_id']],
            'attendance_member_seats' => [['attendance_member_id']],
            'attendance_invitations' => [['attendance_id'], ['inviter_user_id'], ['recipient_user_id']],
            'attendance_links' => [['attendance_id']],
            'setlist_entries' => [['attendance_id'], ['song_id']],
            'setlist_entry_artists' => [['setlist_entry_id'], ['artist_id']],
            'artist_provider_ids' => [['artist_id']], 'venue_provider_ids' => [['venue_id']],
            'tour_provider_ids' => [['tour_id']], 'song_provider_ids' => [['song_id']],
            'event_provider_references' => [['event_id']], 'song_links' => [['song_id']],
        ];
        foreach ($expected as $table => $columnSets) {
            $actual = array_column(Schema::getForeignKeys($table), 'columns');
            foreach ($columnSets as $columns) {
                $this->assertContains($columns, $actual, $table);
            }
        }
        $this->assertDatabaseRejects(fn () => SongProviderId::factory()->create(['song_id' => 2147483647]));
    }
    public function test_optional_seeder_creates_a_connected_graph(): void
    {
        $this->seed(\Database\Seeders\LiveEventSeeder::class);
        $this->assertSame(1, Attendance::count());
        $a = Attendance::sole();
        $this->assertSame(2, $a->members()->count());
        $this->assertSame(3, $a->entries()->count());
        $this->assertSame(1, $a->entries()->where('is_tape', true)->count());
        $this->assertSame(1, $a->event->providerReferences()->count());
    }
}
```

- [ ] **Step 3: Run the new tests before adding the seeder.**

```sh
php artisan test --compact tests/Feature/DomainGraphTest.php
```

Expected: the optional seeder test fails until its contents are supplied. Other failures identify integration defects in Tasks 1–6; fix only their responsible files.

- [ ] **Step 4: Write the optional sample seeder.**

`backend/database/seeders/LiveEventSeeder.php`:

```php
<?php
namespace Database\Seeders;
use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Attendance;
use App\Models\AttendanceInvitation;
use App\Models\AttendanceLink;
use App\Models\AttendanceMember;
use App\Models\AttendanceMemberSeat;
use App\Models\Event;
use App\Models\EventArtist;
use App\Models\EventProviderReference;
use App\Models\EventTour;
use App\Models\SetlistEntry;
use App\Models\SetlistEntryArtist;
use App\Models\Song;
use App\Models\SongLink;
use App\Models\SongProviderId;
use App\Models\Tour;
use App\Models\TourArtist;
use App\Models\TourProviderId;
use App\Models\Venue;
use App\Models\VenueProviderId;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class LiveEventSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $artist = Artist::factory()->create(['name' => 'Sample Artist', 'type' => 'group']);
            $venue = Venue::factory()->create(['name' => 'Sample Venue', 'country_code' => 'AR']);
            $tour = Tour::factory()->create(['name' => 'Sample Tour']);
            $song = Song::factory()->create(['title' => 'Sample Song']);
            $event = Event::factory()->create(['title' => 'Sample Concert', 'venue_id' => $venue->id, 'year' => 2026]);
            EventArtist::factory()->create(['event_id' => $event->id, 'artist_id' => $artist->id, 'position' => 1]);
            TourArtist::factory()->create(['tour_id' => $tour->id, 'artist_id' => $artist->id]);
            EventTour::factory()->create(['event_id' => $event->id, 'tour_id' => $tour->id]);
            $a = Attendance::factory()->create(['event_id' => $event->id, 'notes' => 'Sample personal notes']);
            $guest = AttendanceMember::factory()->create(['attendance_id' => $a->id, 'interested' => true]);
            AttendanceMemberSeat::factory()->create(['attendance_member_id' => $guest->id, 'seat_label' => '12']);
            AttendanceInvitation::factory()->create(['attendance_id' => $a->id]);
            AttendanceLink::factory()->create(['attendance_id' => $a->id, 'type' => 'photo_album']);
            $live = SetlistEntry::factory()->create(['attendance_id' => $a->id, 'song_id' => $song->id, 'position' => 1]);
            SetlistEntryArtist::factory()->create(['setlist_entry_id' => $live->id, 'artist_id' => $artist->id]);
            SetlistEntry::factory()->tape()->create(['attendance_id' => $a->id, 'song_id' => $song->id, 'position' => 2]);
            SetlistEntry::factory()->note('Encore')->create(['attendance_id' => $a->id, 'position' => 3]);
            ArtistProviderId::factory()->create(['artist_id' => $artist->id]);
            VenueProviderId::factory()->create(['venue_id' => $venue->id]);
            TourProviderId::factory()->create(['tour_id' => $tour->id]);
            SongProviderId::factory()->create(['song_id' => $song->id]);
            EventProviderReference::factory()->create(['event_id' => $event->id]);
            SongLink::factory()->create(['song_id' => $song->id]);
        });
    }
}
```

This seeder creates synthetic provider IDs. It performs no external calls. It appends a sample graph each time; it is not a duplicate-merging or production-data tool. Do not run it against the existing local database during implementation verification. Tests call it in isolated databases.

- [ ] **Step 5: Add an isolated migration cycle test.** Create `backend/tests/Feature/DomainMigrationTest.php` using the generator below and the complete contents that follow.

```sh
php artisan make:test --phpunit DomainMigrationTest --no-interaction
```

```php
<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
class DomainMigrationTest extends TestCase
{
    public function test_domain_migrations_can_rollback_and_reapply_without_losing_existing_user(): void
    {
        $original = DB::getDefaultConnection();
        $configuration = config('database.connections.'.$original);
        $schema = 'domain_cycle_'.strtolower(Str::random(10));
        $postgres = $configuration['driver'] === 'pgsql';
        if ($postgres) {
            DB::statement('CREATE SCHEMA "'.$schema.'"');
            $configuration['search_path'] = $schema;
        } else {
            $configuration['database'] = ':memory:';
        }
        config(['database.connections.domain_cycle' => $configuration]);
        DB::setDefaultConnection('domain_cycle');
        try {
            Artisan::call('migrate', ['--database' => 'domain_cycle', '--force' => true, '--no-interaction' => true]);
            $id = DB::table('users')->insertGetId([
                'name' => 'Retained', 'email' => 'retained@example.com',
                'password' => 'retained', 'external_id' => (string) Str::uuid7(),
            ]);
            Artisan::call('migrate:rollback', ['--database' => 'domain_cycle', '--step' => 6, '--force' => true, '--no-interaction' => true]);
            $this->assertFalse(Schema::hasTable('attendances'));
            $this->assertFalse(Schema::hasTable('event_provider_references'));
            $this->assertFalse(Schema::hasColumn('users', 'external_id'));
            $this->assertSame('retained', DB::table('users')->find($id)->password);
            Artisan::call('migrate', ['--database' => 'domain_cycle', '--force' => true, '--no-interaction' => true]);
            $this->assertTrue(Schema::hasTable('setlist_entries'));
            $this->assertTrue(Schema::hasTable('event_provider_references'));
            $this->assertTrue(Str::isUuid(DB::table('users')->find($id)->external_id));
            $this->assertSame('retained@example.com', DB::table('users')->find($id)->email);
        } finally {
            DB::purge('domain_cycle');
            DB::setDefaultConnection($original);
            if ($postgres) {
                DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
            }
        }
    }
}
```

This same rollback/reapply test exercises the existing-User upgrade on PostgreSQL. The Task 1 SQLite-only test covers a minimal upgrade; `DomainMigrationTest.php` covers the second-engine upgrade and full migration cycle.

- [ ] **Step 6: Run the complete backend suite on SQLite.**

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test --compact
vendor/bin/pint --dirty --format agent
composer validate --strict
```

Expected: all backend tests pass, including the existing health endpoint and CORS tests. No migration command targets `backend/database/database.sqlite`.

- [ ] **Step 7: Prepare or select a dedicated local PostgreSQL test database.** Use an existing PostgreSQL service; do not provision a paid service, modify a production database, or install a database server as part of this plan. A locally owned empty database named `spectacles_testing` is the test target. The local test account needs schema creation permission. With a configured local PostgreSQL CLI account, this command creates only that named database:

```sh
createdb spectacles_testing
```

If it already exists, confirm it is a disposable test database. If PostgreSQL or credentials are unavailable, stop the PostgreSQL gate and report it as unverified; do not mark both-engine acceptance complete. Do not print or copy passwords into the plan, test output, or chat.

- [ ] **Step 8: Run the same complete suite on PostgreSQL.** Supply local connection credentials through environment variables, not committed files. `DB_CONNECTION`, `DB_DATABASE`, and `DB_URL` below override the current non-forced PHPUnit XML defaults. For a socket-authenticated local account, set `DB_HOST` to that socket directory; the example uses TCP and the current local username.

```sh
DB_CONNECTION=pgsql DB_DATABASE=spectacles_testing DB_URL= DB_HOST=127.0.0.1 DB_PORT=5432 DB_USERNAME="$USER" php artisan test --compact
```

Expected: the same test suite passes against PostgreSQL. This run is destructive only to the dedicated test database because `RefreshDatabase` creates/refreshes its schema. Keep the database name explicit in every invocation. Do not change `phpunit.xml` to target a shared database.

- [ ] **Step 9: Review files, schema results, and gate evidence.**

```sh
git diff --check
git status --short
```

Run these commands from the repository root. Review all intended PHP files, including untracked files, before staging. No test run should alter routes, runtime defaults, frontend files, application data, or `.env`. Do not broaden the test run after both full suites pass unless a new change needs verification.

- [ ] **Step 10: Conditional delivery checkpoint.** If commits are separately authorized, stage only the Task 7 PHP files and any specific corrections to earlier tasks, inspect `git diff --cached`, and use `test: verify event data layer on sqlite and postgres`. Do not stage this plan, research, or the spec unless separately requested. Do not push or create a PR without authorization.

## Coverage and handoff

| Spec requirements | Data-layer coverage | Separate application work |
| --- | --- | --- |
| Record IDs and string provider IDs | Tasks 1, 6, 7 | Public API serialization and endpoint authorization |
| Multilingual content and locale storage | Tasks 1, 2, 7; UUID guard uses Laravel’s existing validation message | Supported UI locales, browser fallback, formatting, and Form Requests using Laravel validation language resources |
| Catalog, disambiguation, address, dates, tours, multiple artists | Tasks 2, 3: columns, relationships, and casts | Request validation of content, date precision, coordinates, types, and admin permissions |
| One membership per Event, composite consistency, independent flags/seats | Task 4 | Fixed Event on retained Attendance, seat-description validation, owner-only changes, ownership transfer, owner membership on production creation/removal |
| Pending invitation uniqueness and status storage | Task 4 | Recipient acceptance, automatic discard, access before acceptance, concurrent acceptance |
| Flat setlist, tape, repeated songs, performers, notes, manual data | Task 5: storage, casts, and unique positions | Entry-content and performer eligibility validation, atomic reorder, permissions, whole-list last-save-wins save transaction |
| Source identity and conditional single-performance uniqueness | Task 6 | Stable setlist ID mapping, reuse existing Event, grouping/rejection flow; no source updates |
| Autocomplete remains optional and user-confirmed | No network dependency in any model or factory | Provider adapters, prefill once per Attendance, suggestion confirmation and failure UI |
| Cascade owned data, restrict used catalog/Event rows | Tasks 3–7 | Authorized deletion, owner removal/account-deletion workflows |
| Both supported engines and upgrade/rollback | Task 7 | Production database/deployment selection |

The full spec is not complete application behavior after this plan. This delivery is the tested persistence foundation requested by the user. Do not add speculative APIs to close the separately listed workflow gaps.

Self-review before handoff: confirm all 21 new table models and factories plus the existing User are listed; all six migrations have reverse-order rollback; all Review Focus items have executable tests; all methods referenced by tests exist in this plan; no code block contains an omitted implementation. Do not call a separate review agent.

No unresolved database-engine choice remains. The initial type code lists for future request validation and UUIDv7 choice are explicit proposals in this plan and can be reviewed before execution. Authentication, application workflows, provider access, and production deployment remain outside this delivery rather than hidden implementation tasks.

Reference material used while planning: [Laravel 13 Eloquent](https://laravel.com/framework/docs/13.x/eloquent), [SQLite partial indexes](https://www.sqlite.org/partialindex.html), and [PostgreSQL partial indexes](https://www.postgresql.org/docs/current/indexes-partial.html). Installed Laravel source was also checked for `HasUuids`, non-primary unique IDs, factory callbacks, and model insertion order. Laravel’s `HasUuids` generates public UUIDs during insertion. No additional UUID-format validation hook is planned.
