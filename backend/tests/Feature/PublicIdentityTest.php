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
