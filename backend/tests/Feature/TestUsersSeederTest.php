<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TestUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_artificial_and_configured_personal_users(): void
    {
        config(['app.personal_user' => ['email' => 'me@example.org', 'name' => 'Me Myself', 'password' => 'secret-pass']]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', count(TestUsersSeeder::ARTIFICIAL_USERS) + 1);
        foreach (TestUsersSeeder::ARTIFICIAL_USERS as $user) {
            $this->assertDatabaseHas('users', $user);
        }
        $personal = User::query()->where('email', 'me@example.org')->firstOrFail();
        expect($personal->name)->toBe('Me Myself')
            ->and(Hash::check('secret-pass', $personal->password))->toBeTrue();
    }

    public function test_personal_user_is_skipped_without_configured_email(): void
    {
        config(['app.personal_user' => ['email' => null, 'name' => null, 'password' => null]]);

        $this->seed(TestUsersSeeder::class);

        $this->assertDatabaseCount('users', count(TestUsersSeeder::ARTIFICIAL_USERS));
    }

    public function test_reruns_preserve_existing_accounts(): void
    {
        config(['app.personal_user' => ['email' => 'me@example.org', 'name' => null, 'password' => null]]);
        $existing = User::factory()->create(['email' => 'me@example.org', 'name' => 'Edited Name']);

        $this->seed(TestUsersSeeder::class);
        $this->seed(TestUsersSeeder::class);

        $this->assertDatabaseCount('users', count(TestUsersSeeder::ARTIFICIAL_USERS) + 1);
        expect($this->present($existing->fresh())->name)->toBe('Edited Name')
            ->and($this->present($existing->fresh())->password)->toBe($existing->password);
    }
}
