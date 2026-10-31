<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
        $this->withoutMiddleware(ThrottleRequests::class);
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
}
