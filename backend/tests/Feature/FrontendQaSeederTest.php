<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceMember;
use App\Models\Event;
use App\Models\SetlistEntry;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\FrontendQaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendQaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_samples_belong_to_existing_qa_account_and_reruns_preserve_edits_and_counts(): void
    {
        $user = User::factory()->create(['email' => 'frontend-qa@example.com']);
        $other = User::factory()->create();
        $password = $user->password;
        $this->seed(FrontendQaSeeder::class);
        $this->assertDatabaseCount('attendances', 12);
        $this->assertDatabaseCount('venues', 6);
        $this->assertDatabaseCount('setlist_entries', 30);
        expect(AttendanceMember::query()->where('user_id', $user->id)->count())->toBe(12)
            ->and(AttendanceMember::query()->where('user_id', $other->id)->count())->toBe(0);
        $attendance = Attendance::query()->firstOrFail();
        $attendance->update(['notes' => 'My edit']);
        $this->seed(FrontendQaSeeder::class);
        $this->assertDatabaseCount('attendances', 12);
        $this->assertDatabaseCount('setlist_entries', 30);
        expect($this->present($attendance->fresh())->notes)->toBe('My edit')
            ->and($this->present($user->fresh())->password)->toBe($password)
            ->and(Event::query()->whereNull('year')->exists())->toBeTrue()
            ->and(Event::query()->whereNotNull('year')->whereNull('month')->exists())->toBeTrue()
            ->and(Venue::query()->whereNull('lat')->exists())->toBeTrue()
            ->and(SetlistEntry::query()->where('is_tape', true)->count())->toBe(5);
        $this->actingAs($user)->getJson('/api/v1/collection')->assertOk()->assertJsonCount(12, 'data.concerts')->assertJsonCount(6, 'data.venues');
    }
}
