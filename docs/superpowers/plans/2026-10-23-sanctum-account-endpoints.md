# Sanctum Account Endpoints Implementation Plan

> **Execution:** Follow the user-selected execution method and repository requirements. This plan does not require any additional skill. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build registration, login, logout, and private profile read/update endpoints using Sanctum SPA sessions.

**Architecture:** Use Laravel's web session guard for login and Sanctum for protected API routes. Keep the existing public User resource unchanged; a separate CurrentUserResource exposes only the authenticated user's public UUID, name, email, and locale. Use native validation, session rotation, CSRF protection, persistent sessions/cache, and rate limiting without tokens, additional profile columns, services, or repositories.

**Tech Stack:** PHP 8.5; installed Laravel 13.38.0; Sanctum 4.3.3; PHPUnit 12.5.40; SQLite for isolated tests, existing PostgreSQL support retained.

**Spec:** The user's five-endpoint request and confirmed choices in this chat: cookie/session SPA authentication; only name, email, and locale; no city, bio, avatar color, or photo persistence. Also read `docs/superpowers/specs/2026-10-22-live-events-database-design.md` (public identity/localization rules) and `frontend/design/account/visual-spec.md` (existing field bounds; preview UI remains outside this backend task).

## Global Constraints

- “Public UUIDs resolve to internal records; existing access and collaboration rules still apply.”
- “API error codes and field identifiers remain stable English identifiers; displayed messages can be localized.”
- “Changing interface language must not change stored content, external identifiers, or entity identity.”
- Read `~/.config/agent-skills/runtime.md` before PHP/Composer commands; this machine uses `php` and `composer` directly.
- Run PHP/Composer commands from `/Users/pronestivm/Projects/spectacles/backend`. File lists use paths relative to the repository root. No command in this document authorizes overwriting an existing implementation.
- Preserve unrelated working-tree changes and planning/design artifacts. The current feature branch contains direct domain dependencies from main; do not merge/rebase dev, create commits, push, or deploy without separate authorization.
- No `.ai/rules` directory exists at the repository root or backend. Recheck at execution time and read any newly present applicable rules before edits.
- No new User columns, token tables, frontend integration, verification/reset emails, photos, or account deletion.
- New product routes use `/api/v1`; keep `/api/health` public and session-free. Sanctum additionally provides `/sanctum/csrf-cookie`.
- JSON requests use ordinary named fields; successful identity responses use the existing JSON:API resource convention. Invalid input returns 422, missing authentication 401, invalid CSRF 419, and throttling 429.
- Open registration; auto-login after registration; registration passwords require 12 characters and at most 72 bytes with no NUL characters for the existing bcrypt hasher. Existing valid accounts may log in with shorter passwords. Do not call an external password service.
- Normalize supplied emails to lowercase after native request trimming; validate format and uniqueness. Email changes take effect immediately and clear `email_verified_at`; do not send mail or implement verification.
- PATCH accepts only validated name/email/locale. Name: 1–80 characters; email: valid and at most 254 characters; locale: null or the configured primary/fallback application locales, at most 35 characters. Other fields are ignored and never persisted.
- SPA and API share a parent domain in deployed cookie setups. Keep the session cookie HTTP-only, SameSite=Lax, HTTPS-only on HTTPS deployments; use the root parent cookie domain for the Yerd subdomain setup.

## Review Focus

- A browser must obtain CSRF cookies and carry an actual session across separate requests, then lose access after logout; pin in Task 1's cookie lifecycle test.
- Missing/foreign Origin requests and arbitrary Bearer credentials must fail cleanly without creating accounts or querying an absent token table; pin in Task 1's non-SPA/Bearer tests.
- Malformed input, oversized UTF-8 passwords, and duplicate normalized emails must return validation responses without partial writes; pin in Task 1's validation tests.
- Updating one's profile must neither change another user nor allow changing credentials, UUIDs, or unsupported preview fields; pin in Task 2's isolation/allowlist tests.
- Cross-subdomain CORS and actual CSRF enforcement must work while hostile origins receive no permission and the health route stays session-free; pin in Task 3's boundary tests and isolated HTTP smoke check.

---

## File Structure

| Path | Responsibility |
| --- | --- |
| `backend/composer.json`, `backend/composer.lock` | Install the explicitly requested Sanctum dependency only. |
| `backend/bootstrap/app.php` | Enable Sanctum's stateful API middleware. |
| `backend/routes/api.php` | Five versioned account endpoints and the existing stateless health route. |
| `backend/app/Http/Controllers/AuthController.php` | Native registration/login validation and session lifecycle. |
| `backend/app/Http/Controllers/ProfileController.php` | Read/update the current authenticated user only. |
| `backend/app/Http/Resources/CurrentUserResource.php` | Explicit private self attributes; no relationship graph. |
| `backend/app/Providers/AppServiceProvider.php` | Disable unused bearer-token fallback and define native rate limits. |
| `backend/config/cors.php`, `backend/.env.example` | Exact trusted origins, credentialed write requests, stateful domains, persistent stores. |
| `backend/tests/Feature/AuthSessionTest.php` | Registration/login/logout/session/security boundaries. |
| `backend/tests/Feature/ProfileTest.php` | Profile fields, locale rules, privacy, identity and user isolation. |
| `backend/tests/Feature/ApiHealthTest.php` | Preserve health behavior under the new CORS contract. |
| `README.md` | Actual API contract, SPA cookie sequence, local and production prerequisites. Preserve existing Yerd notes. |

## Contract

| Route | Input | Result |
| --- | --- | --- |
| `POST /api/v1/auth/register` | `name`, `email`, `password`, `password_confirmation` | 201 CurrentUserResource; authenticated rotated session. |
| `POST /api/v1/auth/login` | `email`, `password` | 200 CurrentUserResource; authenticated rotated session; generic 422 credential failure. |
| `POST /api/v1/auth/logout` | CSRF header and current session | 204; invalidate current session and rotate CSRF token. |
| `GET /api/v1/me` | Current session | 200 CurrentUserResource; no arbitrary user selection or includes. |
| `PATCH /api/v1/me` | Any subset of `name`, `email`, `locale` | 200 CurrentUserResource, preserving absent fields. |

## Current Baseline and Execution Preflight

As checked on 2026-10-23, all five endpoints and their tests already exist in commit `6ea9030` (`Add Sanctum session account endpoints`) on `feat/live-event-data-layer`. The source files and installed routes were inspected during this planning update; tests were not rerun. The checked task boxes below record the earlier implementation, rather than new work requested by this planning-only turn. The historical verification results remain dated at the end of this document.

This plan is the complete construction reference for the feature. An executor on the current branch must first inspect and verify what exists; passing behavior needs no additional implementation. Do not delete existing endpoints or tests to manufacture a failing test, recreate existing files, reinstall an installed dependency, or reset another chat's work. If one behavior is missing, use only its owning task and add a regression test for that behavior before changing code.

```sh
cd /Users/pronestivm/Projects/spectacles
git status --short
git log -1 --oneline
git show --stat 6ea9030
cd backend
composer show --direct
php artisan route:list --path=api --no-interaction
```

Expected current routes: the five `/api/v1` routes in the contract above, plus `/api/health`. For an already complete implementation, Task 3's focused feature tests and final gates are sufficient; the temporary HTTP smoke check is an additional integration check when the cookie/session boundary needs proof. Never apply Event-domain migrations to the configured local database merely to exercise account endpoints.

The approved choices are Sanctum SPA cookies and the existing name/email/locale fields. The password bounds, immediate email-change behavior, and primary/fallback locale allowlist below describe the current implementation policy. There is no unresolved choice blocking these five endpoints. Connecting the React preview is a separate task.

### Task 1: Session Authentication and Private Identity

**Files (create only when missing):**
- Create: `backend/app/Http/Controllers/AuthController.php` — registration, login, logout.
- Create: `backend/app/Http/Controllers/ProfileController.php` — initially the `show` method.
- Create: `backend/app/Http/Resources/CurrentUserResource.php` — self identity only.
- Create/Test: `backend/tests/Feature/AuthSessionTest.php` — actual cookie/session authentication and trust boundaries.
- Modify: `backend/composer.json`, `backend/composer.lock` — the Sanctum dependency.
- Modify: `backend/bootstrap/app.php` — stateful API middleware.
- Modify: `backend/routes/api.php` — account routes and stateless health.
- Modify: `backend/app/Providers/AppServiceProvider.php` — cookie-only authentication and rate limits.
- Modify: `backend/config/cors.php`, `backend/.env.example` — credentialed SPA configuration and persistent stores.

**Interfaces:** Consumes `App\Models\User`, its hashed password cast and HasPublicId trait, and `PublicJsonApiResource`. Produces `AuthController::register(Request): JsonResponse`, `login(Request): JsonResponse`, `logout(Request): Response`, `ProfileController::show(Request): JsonResponse`, and `CurrentUserResource` with resource type `users` and UUID `id`.

- [x] **Step 1: Create the feature test using Artisan, then write these runnable tests.**

```sh
cd /Users/pronestivm/Projects/spectacles/backend
php artisan make:test AuthSessionTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database', 'sanctum.stateful' => ['localhost:5173']]);
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    private function carryCookies(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
        Auth::forgetGuards();
    }

    public function test_registration_cookie_survives_requests_and_logout_revokes_it(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jamie', 'email' => ' JAMIE@example.com ',
            'password' => 'concert-password', 'password_confirmation' => 'concert-password',
            'external_id' => 'client-owned-id',
        ])->assertCreated()->assertJsonPath('data.type', 'users')
            ->assertJsonPath('data.attributes.email', 'jamie@example.com');
        $user = User::sole();
        $this->assertSame($user->external_id, $response->json('data.id'));
        $this->assertNotSame('client-owned-id', $user->external_id);
        $this->assertTrue(Hash::check('concert-password', $user->password));
        $this->carryCookies($response);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->external_id);
        $logout = $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->carryCookies($logout);
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_accepts_existing_credentials_and_rotates_the_session(): void
    {
        $user = User::factory()->create(['email' => 'jamie@example.com']);
        $initial = $this->get('/sanctum/csrf-cookie')->assertNoContent();
        $this->carryCookies($initial);
        $oldId = session()->getId();
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'JAMIE@example.com', 'password' => 'password',
        ])->assertOk()->assertJsonPath('data.id', $user->external_id);
        $this->assertNotSame($oldId, session()->getId());
        $this->carryCookies($response);
        $this->getJson('/api/v1/me')->assertOk();
    }

    public function test_invalid_registration_does_not_create_a_user(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        foreach ([
            ['password' => 'short', 'password_confirmation' => 'short'],
            ['password_confirmation' => 'does-not-match'],
            ['password' => str_repeat('é', 37), 'password_confirmation' => str_repeat('é', 37)],
            ['password' => "concert\0password", 'password_confirmation' => "concert\0password"],
            ['email' => ['not-a-string']], ['name' => ''],
        ] as $invalid) {
            $this->postJson('/api/v1/auth/register', array_replace([
                'name' => 'Jamie', 'email' => 'jamie@example.com',
                'password' => 'concert-password', 'password_confirmation' => 'concert-password',
            ], $invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_normalized_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'jamie@example.com']);
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jamie', 'email' => 'JAMIE@example.com',
            'password' => 'concert-password', 'password_confirmation' => 'concert-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_credentials_are_generic_and_rate_limited(): void
    {
        User::factory()->create(['email' => 'jamie@example.com']);
        $missing = $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertUnprocessable();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->postJson('/api/v1/auth/login', ['email' => 'jamie@example.com', 'password' => 'wrong'])->assertUnprocessable();
            $this->assertSame($missing->json('errors'), $response->json('errors'));
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'jamie@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_guests_and_bearer_headers_cannot_read_private_identity(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken('invalid-token')->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_non_spa_registration_is_rejected_without_a_server_error(): void
    {
        $this->withHeader('Origin', 'https://untrusted.example')->postJson('/api/v1/auth/register', [
            'name' => 'Jamie', 'email' => 'jamie@example.com',
            'password' => 'concert-password', 'password_confirmation' => 'concert-password',
        ])->assertForbidden();
        $this->assertDatabaseCount('users', 0);
    }
}
```

- [x] **Step 2: Run the new test and observe missing account routes.**

```sh
php artisan test --compact tests/Feature/AuthSessionTest.php
```

Expected on a base without the feature: failures for missing routes. Expected on the current implemented branch: PASS; skip the creation and implementation steps. Tests require isolated SQLite memory or the dedicated PostgreSQL test database and must not write to the configured development database.

- [x] **Step 3: Install only Sanctum when it is absent.**

Do not run `install:api`, which also publishes unused token scaffolding.

```sh
composer require 'laravel/sanctum:^4.3' --no-interaction
```

- [x] **Step 4: Generate only the missing application files.**

```sh
php artisan make:controller AuthController --no-interaction
php artisan make:controller ProfileController --no-interaction
php artisan make:resource CurrentUserResource --json-api --no-interaction
```

- [x] **Step 5: Write the private current-user resource.**

Its explicit type preserves `users` despite the separate resource class name:

```php
<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/** @mixin User */
class CurrentUserResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = ['name', 'email', 'locale'];

    public function toType(Request $request): string
    {
        return 'users';
    }
}
```

- [x] **Step 6: Write the registration, login, and logout controller.**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        abort_unless($request->hasSession(), 403);
        $this->normalizeEmail($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'regex:/\A[^\x00]{1,72}\z/sD'],
        ], ['password.regex' => __('The password must contain no null bytes and must not exceed 72 bytes.')]);
        $user = User::create($data);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new CurrentUserResource($user))->response()->setStatusCode(201)->header('Cache-Control', 'no-store');
    }

    public function login(Request $request): JsonResponse
    {
        abort_unless($request->hasSession(), 403);
        $this->normalizeEmail($request);
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'regex:/\A[^\x00]{1,72}\z/sD'],
        ]);
        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $request->session()->regenerate();

        return (new CurrentUserResource(Auth::guard('web')->user()))->response()->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => strtolower($email)]);
        }
    }
}
```

- [x] **Step 7: Write the initial profile read controller.**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return (new CurrentUserResource($request->user()))->response()->header('Cache-Control', 'no-store');
    }
}
```

- [x] **Step 8: Enable stateful middleware and register the account routes.**

In `backend/bootstrap/app.php`, use:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->statefulApi();
})
```

Use this `backend/routes/api.php` content:

```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/health', fn (): array => ['status' => 'ok', 'service' => 'spectacles-api'])
    ->withoutMiddleware(EnsureFrontendRequestsAreStateful::class);

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [ProfileController::class, 'show']);
    });
});
```

- [x] **Step 9: Define cookie-only authentication and rate limits.**

In `backend/app/Providers/AppServiceProvider.php`, retain the existing class and register method. Add these imports and replace only the boot method:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

public function boot(): void
{
    Sanctum::getAccessTokenFromRequestUsing(fn (Request $request): ?string => null);
    RateLimiter::for('registration', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
    RateLimiter::for('login', function (Request $request): array {
        $email = $request->input('email');

        return [
            Limit::perMinute(20)->by($request->ip()),
            Limit::perMinute(5)->by((is_string($email) ? strtolower(trim($email)) : '').'|'.$request->ip()),
        ];
    });
}
```

- [x] **Step 10: Configure credentialed CORS and persistent store defaults.**

In `backend/config/cors.php` retain exact configured origins; change only these values:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['GET', 'POST', 'PATCH', 'OPTIONS'],
'allowed_headers' => ['Accept', 'Content-Type', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN', 'X-Requested-With'],
'supports_credentials' => true,
```

In `backend/.env.example`, replace the session/cache driver values and add the stateful frontend domains once. Keep the other settings intact:

```dotenv
SESSION_DRIVER=database
CACHE_STORE=database
SESSION_DOMAIN=null
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:3000
```

Do not edit the private `.env` during planning, publish a personal access token migration, or add HasApiTokens to User.

- [x] **Step 11: Run the focused test and repair any mismatch with installed Laravel/Sanctum behavior before proceeding.**

```sh
php artisan test --compact tests/Feature/AuthSessionTest.php
```

Expected: all methods pass, including actual cookie carryover, no token-table dependency, and clean logout.

### Task 2: Profile Update With Existing Fields Only

**Files:**
- Modify: `backend/app/Http/Controllers/ProfileController.php` — add the authenticated `update` method.
- Modify: `backend/routes/api.php` — add PATCH inside the existing `auth:sanctum` group.
- Create/Test: `backend/tests/Feature/ProfileTest.php` — field validation, email changes, immutable identity, and user isolation. Reuse this file if it already exists.

**Interfaces:** Consumes Task 1's `auth:sanctum` group and CurrentUserResource. Produces `ProfileController::update(Request): JsonResponse` at PATCH `/api/v1/me`; derives identity only from Request::user(), not payload/query IDs.

- [x] **Step 1: Generate and write the profile tests.**

```sh
php artisan make:test ProfileTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost:5173'], 'app.locale' => 'en', 'app.fallback_locale' => 'en']);
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_profile_update_is_private_preserves_identity_and_resets_changed_email_verification(): void
    {
        $user = User::factory()->create(['email' => 'before@example.com']);
        $other = User::factory()->create();
        $password = $user->password;
        $this->actingAs($user, 'web')->patchJson('/api/v1/me', [
            'name' => 'Jamie Updated', 'email' => 'AFTER@example.com', 'locale' => 'en',
            'id' => $other->id, 'external_id' => $other->external_id, 'password' => 'not-a-new-password',
            'city' => 'ignored', 'bio' => 'ignored', 'avatar_color' => 'ink',
        ])->assertOk()->assertJsonPath('data.id', $user->external_id)
            ->assertJsonPath('data.attributes.email', 'after@example.com')
            ->assertJsonMissingPath('data.attributes.password')->assertJsonMissingPath('data.attributes.city');
        $user->refresh();
        $this->assertSame('Jamie Updated', $user->name);
        $this->assertSame('en', $user->locale);
        $this->assertSame($password, $user->password);
        $this->assertNull($user->email_verified_at);
        $this->assertSame($other->getOriginal('name'), $other->fresh()->name);
        $public = (new UserResource($user))->toResponse(Request::create('/resource'))->getData(true);
        $this->assertArrayNotHasKey('email', $public['data']['attributes']);
    }

    public function test_unchanged_email_keeps_verification_and_absent_fields_are_preserved(): void
    {
        $user = User::factory()->create(['email' => 'jamie@example.com', 'locale' => 'en']);
        $this->actingAs($user, 'web')->patchJson('/api/v1/me', ['name' => 'New Name'])
            ->assertOk()->assertJsonPath('data.attributes.email', 'jamie@example.com');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('en', $user->fresh()->locale);
        $this->patchJson('/api/v1/me', ['locale' => null])->assertOk()->assertJsonPath('data.attributes.locale', null);
    }

    public function test_invalid_or_duplicate_profile_values_do_not_write(): void
    {
        $user = User::factory()->create(['name' => 'Original']);
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAs($user, 'web');
        foreach ([['name' => null], ['name' => str_repeat('x', 81)], ['email' => 'bad'],
            ['email' => strtoupper($other->email)], ['locale' => 'unsupported'], ['locale' => ['en']]] as $invalid) {
            $this->patchJson('/api/v1/me', $invalid)->assertUnprocessable();
        }
        $this->assertSame('Original', $user->fresh()->name);
    }

    public function test_current_user_response_has_no_shared_graph_or_internal_identifiers(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'web')->getJson('/api/v1/me?include=attendanceMemberships,ownedAttendances')
            ->assertOk()->assertJsonMissingPath('included')->assertJsonMissingPath('data.relationships')
            ->assertJsonMissingPath('data.attributes.id')->assertJsonMissingPath('data.attributes.remember_token');
        $this->assertSame(['name', 'email', 'locale'], array_keys($response->json('data.attributes')));
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patchJson('/api/v1/me', ['name' => 'Changed'])->assertUnauthorized();
    }
}
```

- [x] **Step 2: Run the new tests and observe PATCH missing.**

```sh
php artisan test --compact tests/Feature/ProfileTest.php
```

Expected on the intermediate implementation with GET but no PATCH: 405 responses from the PATCH tests. Expected on the current branch: PASS; retain the existing update method and route.

- [x] **Step 3: Add this update method and its imports.**

```php
use App\Models\User;
use Illuminate\Validation\Rule;

public function update(Request $request): JsonResponse
{
    if (is_string($email = $request->input('email'))) {
        $request->merge(['email' => strtolower($email)]);
    }
    $user = $request->user();
    $data = $request->validate([
        'name' => ['sometimes', 'required', 'string', 'max:80'],
        'email' => ['sometimes', 'required', 'string', 'email', 'max:254', Rule::unique(User::class)->ignore($user)],
        'locale' => ['sometimes', 'nullable', 'string', 'max:35', Rule::in([config('app.locale'), config('app.fallback_locale')])],
    ]);
    $user->fill($data);
    if ($user->isDirty('email')) {
        $user->email_verified_at = null;
    }
    $user->save();

    return (new CurrentUserResource($user))->response()->header('Cache-Control', 'no-store');
}
```

- [x] **Step 4: Register the profile update route.**

Place this statement inside the existing `auth:sanctum` group in `backend/routes/api.php`; preserve all sibling routes.

```php
Route::patch('/me', [ProfileController::class, 'update']);
```

- [x] **Step 5: Verify both feature files.**

```sh
php artisan test --compact tests/Feature/AuthSessionTest.php tests/Feature/ProfileTest.php
```

Expected: profile validation, identity isolation, email-change behavior, and session authentication all pass.

### Task 3: Browser Boundaries, Documentation, and Final Proof

**Files:**
- Modify/Test: `backend/tests/Feature/AuthSessionTest.php` — enforce real CSRF validation in the targeted test.
- Modify/Test: `backend/tests/Feature/ApiHealthTest.php` — credentialed CORS and session-free health.
- Modify: `README.md` — API contract and environment prerequisites, retaining the existing Yerd instructions.
- No frontend source changes or persisted verification scripts.

**Interfaces:** Consumes the five endpoints and Sanctum CSRF route. Produces a documented browser contract verified with real cookies/CSRF plus the existing suite.

- [x] **Step 1: Add a CSRF test with Laravel's test bypass disabled only inside the test.**

```php
public function test_stateful_writes_require_csrf_and_accept_the_cookie_header_pair(): void
{
    $this->app->instance('env', 'local');
    try {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jamie', 'email' => 'jamie@example.com',
            'password' => 'concert-password', 'password_confirmation' => 'concert-password',
        ])->assertStatus(419);
        $csrf = $this->get('/sanctum/csrf-cookie')->assertNoContent();
        $this->carryCookies($csrf);
        $this->withHeader('X-XSRF-TOKEN', $csrf->getCookie('XSRF-TOKEN', false)->getValue())
            ->postJson('/api/v1/auth/register', [
                'name' => 'Jamie', 'email' => 'jamie@example.com',
                'password' => 'concert-password', 'password_confirmation' => 'concert-password',
            ])->assertCreated();
    } finally {
        $this->app->instance('env', 'testing');
    }
}
```

- [x] **Step 2: Update the health and CORS boundary tests.**

Update ApiHealthTest: expect `Access-Control-Allow-Credentials: true` for configured origins, and `GET, POST, PATCH, OPTIONS` for allowed methods. Rename the credential-free test to describe credential support. Add these methods:

```php
public function test_health_remains_session_free_for_a_stateful_frontend(): void
{
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeader('Origin', 'http://localhost:5173')->getJson('/api/health')
        ->assertOk()->assertHeaderMissing('Set-Cookie');
}

public function test_account_preflight_allows_credentials_csrf_and_patch_only_for_configured_origins(): void
{
    config(['cors.allowed_origins' => ['https://spectacles.test']]);
    $this->withHeaders([
        'Origin' => 'https://spectacles.test',
        'Access-Control-Request-Method' => 'PATCH',
        'Access-Control-Request-Headers' => 'Content-Type, X-XSRF-TOKEN',
    ])->options('/api/v1/me')->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://spectacles.test')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
    $response = $this->withHeader('Origin', 'https://untrusted.example')->options('/api/v1/me');
    $this->assertNotSame('https://untrusted.example', $response->headers->get('Access-Control-Allow-Origin'));
}
```

- [x] **Step 3: Run focused boundary checks.**

```sh
php artisan test --compact tests/Feature/AuthSessionTest.php tests/Feature/ProfileTest.php tests/Feature/ApiHealthTest.php
```

- [x] **Step 4: Update README with this exact account contract and environment guidance, retaining unrelated existing edits.**

```markdown
## Account API

The backend supports Sanctum cookie/session authentication at `/api/v1/auth/register`, `/api/v1/auth/login`, `/api/v1/auth/logout`, and GET/PATCH `/api/v1/me`. The existing React account screens remain browser-local previews; this backend task does not connect them.

First GET `/sanctum/csrf-cookie` with credentials included. Send its URL-decoded `XSRF-TOKEN` cookie in `X-XSRF-TOKEN` on POST/PATCH requests, include credentials on subsequent requests, and send `Accept: application/json`. Registration accepts name, email, password, and password_confirmation, returns 201, and signs in immediately. Login returns 200; wrong credentials return generic 422 errors. Logout returns 204. Registration requires 12 characters and at most 72 bytes without NUL characters. Passwords are hashed; no bearer tokens are issued.

GET/PATCH `/api/v1/me` return a private JSON:API `users` resource whose id is the User UUID and whose attributes are name, email, and locale. PATCH accepts only those attributes as ordinary JSON fields. Unsupported preview fields are ignored. Email is normalized to lowercase, must be unique, and changes immediately; changing it clears verification without sending mail. Locale is null or a configured APP_LOCALE/APP_FALLBACK_LOCALE value; both default to en.

Use SESSION_DRIVER=database and CACHE_STORE=database, and run pending migrations on a new backend database. SANCTUM_STATEFUL_DOMAINS lists frontend hosts with ports, without schemes. FRONTEND_URLS remains exact allowed origins. For localhost use the defaults and SESSION_DOMAIN=null. For Yerd use SANCTUM_STATEFUL_DOMAINS=spectacles.test,localhost:5173,localhost:3000, SESSION_DOMAIN=.spectacles.test, and SESSION_SECURE_COOKIE=true with the HTTPS frontend/API URLs.

Production SPA sessions require frontend and API under the same parent domain, shared parent SESSION_DOMAIN, HTTPS secure cookies, and durable shared database/session/cache storage. Separate unrelated Cloud application domains do not satisfy the cookie-domain requirement. Configure custom domains before using this flow; no provisioning or deployment is performed here.
```

Replace obsolete README claims about deferred authentication, GET-only CORS, and array stores. Update the Cloud example to database session/cache stores and require managed PostgreSQL plus migrations for account persistence; preserve the health-only deployment distinction and existing Yerd instructions.

- [x] **Step 5: Verify a real HTTP session against an isolated SQLite database, using a temporary process and cookie jar rather than the user's database.**

Run the following from `backend/`. It uses fake credentials, an isolated SQLite database, an unused loopback port, and a temporary HTTP server. Its `finally` block stops its own process group; the temporary directory is removed even if an assertion fails. It does not modify the configured local database or private environment file.

```sh
python3 - <<'PY'
import http.cookiejar
import json
import os
import signal
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

backend = Path.cwd()
assert (backend / 'artisan').exists(), 'Run from the backend directory.'
with tempfile.TemporaryDirectory(prefix='spectacles-auth-') as folder:
    database = Path(folder) / 'auth.sqlite'
    database.touch()
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    origin = 'http://127.0.0.1:5173'
    environment = dict(
        os.environ,
        APP_ENV='local', APP_DEBUG='false', APP_URL=base,
        APP_LOCALE='en', APP_FALLBACK_LOCALE='en',
        APP_CONFIG_CACHE=str(Path(folder) / 'config.php'),
        APP_ROUTES_CACHE=str(Path(folder) / 'routes.php'),
        DB_CONNECTION='sqlite', DB_DATABASE=str(database), DB_URL='',
        SESSION_DRIVER='database', CACHE_STORE='database', SESSION_DOMAIN='',
        SESSION_SECURE_COOKIE='false',
        SANCTUM_STATEFUL_DOMAINS='127.0.0.1:5173', FRONTEND_URLS=origin,
    )
    migration = subprocess.run(
        ['php', 'artisan', 'migrate', '--no-interaction'],
        cwd=backend, env=environment, capture_output=True, text=True,
    )
    assert migration.returncode == 0, migration.stdout + migration.stderr
    with open(Path(folder) / 'server.log', 'w+') as log:
        server = subprocess.Popen(
            ['php', 'artisan', 'serve', '--host=127.0.0.1', f'--port={port}',
             '--no-reload', '--no-interaction'],
            cwd=backend, env=environment, stdout=log,
            stderr=subprocess.STDOUT, start_new_session=True,
        )
        cookies = http.cookiejar.CookieJar()
        browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))

        def request(method, path, body=None, csrf=True, opener=None, extra=None):
            headers = {'Accept': 'application/json', 'Origin': origin}
            if body is not None:
                headers['Content-Type'] = 'application/json'
            token = next((cookie.value for cookie in cookies if cookie.name == 'XSRF-TOKEN'), None)
            if csrf and token:
                headers['X-XSRF-TOKEN'] = urllib.parse.unquote(token)
            headers.update(extra or {})
            http_request = urllib.request.Request(
                base + path,
                data=None if body is None else json.dumps(body).encode(),
                headers=headers, method=method,
            )
            try:
                response = (opener or browser).open(http_request, timeout=5)
            except urllib.error.HTTPError as error:
                response = error
            content = response.read()
            return response.status, json.loads(content) if content else None, response.headers

        try:
            for attempt in range(50):
                try:
                    if request('GET', '/api/health')[0] == 200:
                        break
                except (OSError, urllib.error.URLError):
                    time.sleep(0.1)
            else:
                raise RuntimeError('Temporary HTTP server did not start.')

            account = {
                'name': 'HTTP Smoke', 'email': 'smoke@example.com',
                'password': 'smoke-password-123',
                'password_confirmation': 'smoke-password-123',
            }
            assert request('POST', '/api/v1/auth/register', account, csrf=False)[0] == 419
            assert request('GET', '/sanctum/csrf-cookie')[0] == 204
            status, identity, headers = request('POST', '/api/v1/auth/register', account)
            assert status == 201, (status, identity)
            user_uuid = identity['data']['id']
            assert identity['data']['type'] == 'users'
            status, identity, headers = request('GET', '/api/v1/me')
            assert status == 200 and identity['data']['id'] == user_uuid
            status, identity, headers = request('PATCH', '/api/v1/me', {
                'name': 'Updated HTTP', 'email': 'UPDATED@example.com',
                'locale': 'en', 'city': 'ignored',
            })
            assert status == 200, (status, identity)
            assert identity['data']['attributes'] == {
                'name': 'Updated HTTP', 'email': 'updated@example.com', 'locale': 'en',
            }
            previous_cookie = '; '.join(f'{cookie.name}={cookie.value}' for cookie in cookies)
            assert request('POST', '/api/v1/auth/logout', {})[0] == 204
            assert request('GET', '/api/v1/me')[0] == 401
            assert request('GET', '/api/v1/me', opener=urllib.request.build_opener(),
                           extra={'Cookie': previous_cookie})[0] == 401
            missing = request('POST', '/api/v1/auth/login', {
                'email': 'missing@example.com', 'password': 'incorrect-password',
            })
            wrong = request('POST', '/api/v1/auth/login', {
                'email': 'updated@example.com', 'password': 'incorrect-password',
            })
            assert missing[0] == wrong[0] == 422
            assert missing[1]['errors'] == wrong[1]['errors']
            assert request('POST', '/api/v1/auth/login', {
                'email': 'updated@example.com', 'password': 'smoke-password-123',
            })[0] == 200
            assert request('POST', '/api/v1/auth/register',
                           dict(account, email='UPDATED@example.com'))[0] == 422
            assert not request('GET', '/api/health')[2].get('Set-Cookie')
            status, document, headers = request('OPTIONS', '/api/v1/me', extra={
                'Access-Control-Request-Method': 'PATCH',
                'Access-Control-Request-Headers': 'Content-Type, X-XSRF-TOKEN',
            })
            assert status == 204
            assert headers.get('Access-Control-Allow-Credentials') == 'true'
            assert headers.get('Access-Control-Allow-Origin') == origin
            status, document, headers = request('OPTIONS', '/api/v1/me', extra={
                'Origin': 'https://untrusted.example',
                'Access-Control-Request-Method': 'PATCH',
            })
            assert headers.get('Access-Control-Allow-Origin') != 'https://untrusted.example'
            print('PASS: account cookie, CSRF, validation, profile, logout, and CORS HTTP checks')
        finally:
            os.killpg(server.pid, signal.SIGTERM)
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                os.killpg(server.pid, signal.SIGKILL)
                server.wait(timeout=5)
PY
```

Expected: one PASS line. The script verifies server-side cookie persistence and CORS headers; it does not prove a production browser deployment's parent-domain or HTTPS configuration. Do not replace its fake credentials with a real account, add provider calls, or persist a second verification script in the repository.

- [x] **Step 6: Run the required backend gates and inspect the scoped diff.**

```sh
vendor/bin/pint --dirty --format agent
composer validate --strict
php artisan test --compact
php artisan route:list --path=api --no-interaction
git diff --check
git diff --stat
```

Expected: formatter succeeds, Composer validates, all tests pass, five new versioned endpoints appear alongside health, and no unrelated work is removed. Inspect new untracked application/test files as well as tracked diffs. No commits, pushes, PRs, migrations of unrelated configured-domain tables, or deployment are authorized. Report whether the configured local User-identity migration and durable session/cache settings still need applying; isolated HTTP proof does not imply deployment.

## Self-Review

- All five requested endpoints map to Tasks 1–2; browser/security boundaries and existing health behavior map to Task 3.
- The confirmed narrow profile scope requires no migration or changes to shared UserResource.
- Tests exercise each Review Focus item, real cookie carryover, CSRF rejection, privacy, validation, rate limits, and mass-assignment boundaries.
- Sources checked: installed framework and package metadata, native JSON:API classes, existing tests and runtime configuration; official Laravel 13 Sanctum/authentication documentation.
- Plan review is performed by the main agent. No delegation or automatic commit stage is required.

## Historical Execution Results — 2026-10-23

- Installed Sanctum 4.3.3; implemented the five account routes and private current-user resource.
- Formatter and Composer strict validation passed; the full backend suite passed: 123 tests, 1,407 assertions.
- An isolated SQLite HTTP server passed nine checks covering CSRF, real cookie persistence, profile allowlisting, email normalization, duplicate email validation, login, logout/old-cookie replay, health behavior, and credentialed CORS. The temporary server and database were removed.
- Existing local Yerd session/cache settings now use database stores, secure cookies, and the spectacles.test parent cookie domain. Applied only the existing User UUID/locale migration; the five pending Event-domain migrations were not run against the configured local database. Existing environment values unrelated to authentication were preserved.
- The local Yerd API returned 200 for health and 401 for an anonymous `/api/v1/me`, both with the configured frontend CORS origin.
- No additional User columns, frontend account integration, tokens, pushes, or deployments were introduced by the original implementation. A follow-up chat subsequently committed the application changes as `6ea9030`; the planning document remains outside that application commit. No unresolved product decisions remain for these five backend endpoints.

## Planning-Only Update — 2026-10-23

- Rechecked the current branch, commit, installed dependency versions, registered routes, controllers, resources, and tests. The implementation already exists.
- Expanded exact task file lists, added the current-state execution guard, and supplied the complete isolated HTTP smoke procedure.
- Document checks passed: six complete PHP examples linted, the embedded Python procedure parsed, 15 referenced source paths confirmed, sequential task numbering and Markdown fences checked, and placeholder/whitespace scans passed. The smoke procedure was not executed during this planning update.
- This update changes only this Markdown plan. It does not execute the plan, edit application/test code, run migrations, change environment values, commit, or deploy.

References: [Sanctum SPA authentication](https://laravel.com/framework/docs/13.x/sanctum#spa-authentication), [Laravel authentication](https://laravel.com/framework/docs/13.x/authentication).
