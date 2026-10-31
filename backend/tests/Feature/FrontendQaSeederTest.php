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
        $this->assertSame(12, AttendanceMember::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, AttendanceMember::query()->where('user_id', $other->id)->count());
        $attendance = Attendance::query()->firstOrFail();
        $attendance->update(['notes' => 'My edit']);
        $this->seed(FrontendQaSeeder::class);
        $this->assertDatabaseCount('attendances', 12);
        $this->assertDatabaseCount('setlist_entries', 30);
        $this->assertSame('My edit', $attendance->fresh()->notes);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertTrue(Event::query()->whereNull('year')->exists());
        $this->assertTrue(Event::query()->whereNotNull('year')->whereNull('month')->exists());
        $this->assertTrue(Venue::query()->whereNull('lat')->exists());
        $this->assertSame(5, SetlistEntry::query()->where('is_tape', true)->count());
        $this->actingAs($user)->getJson('/api/v1/collection')->assertOk()->assertJsonCount(12, 'data.concerts')->assertJsonCount(6, 'data.venues');
    }
}
