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
        expect($user->name)->toBe('Jamie Updated')
            ->and($user->locale)->toBe('en')
            ->and($user->password)->toBe($password)
            ->and($user->email_verified_at)->toBeNull()
            ->and($this->present($other->fresh())->name)->toBe($other->getOriginal('name'));
        $public = (new UserResource($user))->toResponse(Request::create('/resource'))->getData(true);
        expect($this->arrayAt($public, 'data.attributes'))->not->toHaveKey('email');
    }

    public function test_unchanged_email_keeps_verification_and_absent_fields_are_preserved(): void
    {
        $user = User::factory()->create(['email' => 'jamie@example.com', 'locale' => 'en']);
        $this->actingAs($user, 'web')->patchJson('/api/v1/me', ['name' => 'New Name'])
            ->assertOk()->assertJsonPath('data.attributes.email', 'jamie@example.com');
        expect($this->present($user->fresh())->email_verified_at)->not->toBeNull()
            ->and($this->present($user->fresh())->locale)->toBe('en');
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
        expect($this->present($user->fresh())->name)->toBe('Original');
    }

    public function test_current_user_response_has_no_shared_graph_or_internal_identifiers(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'web')->getJson('/api/v1/me?include=attendanceMemberships,ownedAttendances')
            ->assertOk()->assertJsonMissingPath('included')->assertJsonMissingPath('data.relationships')
            ->assertJsonMissingPath('data.attributes.id')->assertJsonMissingPath('data.attributes.remember_token');
        expect(array_keys($this->arrayAt($response->json('data.attributes'))))->toBe(['name', 'email', 'locale']);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patchJson('/api/v1/me', ['name' => 'Changed'])->assertUnauthorized();
    }
}
