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
        expect($user->getKey())->toBeInt()
            ->and(Str::isUuid($uuid))->toBeTrue()
            ->and($user->getIncrementing())->toBeTrue()
            ->and($user->getRouteKeyName())->toBe('external_id')
            ->and($this->present($user->resolveRouteBinding($uuid))->is($user))->toBeTrue();
        $user->update(['name' => 'Updated', 'locale' => 'en']);
        expect($this->present($user->fresh())->external_id)->toBe($uuid);
    }

    public function test_public_uuid_cannot_be_changed_on_an_existing_model(): void
    {
        $user = User::factory()->create();
        $user->external_id = (string) Str::uuid7();
        expect(fn () => $user->save())->toThrow(ValidationException::class);
    }

    public function test_replicating_a_user_generates_a_new_public_uuid(): void
    {
        $user = User::factory()->create();
        $copy = $user->replicate();
        $copy->email = 'copy@example.com';
        $copy->save();
        expect($copy->external_id)->not->toBe($user->external_id);
    }
}
