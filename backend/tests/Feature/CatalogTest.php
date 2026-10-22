<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Song;
use App\Models\Tour;
use App\Models\Venue;
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
