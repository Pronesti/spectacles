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
        expect($other->id)->not->toBe($artist->id)
            ->and($this->present($artist->fresh())->name)->toBe('Björk');
        $song = Song::factory()->create(['title' => 'Canción', 'disambiguation' => null]);
        expect($this->present($song->fresh())->title)->toBe('Canción')
            ->and(Venue::factory()->create()->lat)->toBeNull()
            ->and(Tour::factory()->create()->external_id)->not->toBeEmpty();
    }

    public function test_sparse_dates_and_unknown_time_are_not_invented(): void
    {
        $unknown = Event::factory()->create();
        $year = Event::factory()->create(['year' => 2024]);
        $month = Event::factory()->create(['year' => 2024, 'month' => 2]);
        $leap = Event::factory()->create(['year' => 2024, 'month' => 2, 'day' => 29, 'local_start_time' => '00:00:00']);
        expect($unknown->year)->toBeNull()
            ->and($year->month)->toBeNull()
            ->and($month->day)->toBeNull()
            ->and($year->local_start_time)->toBeNull()
            ->and($this->present($leap->fresh())->local_start_time)->toBe('00:00:00');
    }

    public function test_venue_preserves_text_addresses_zero_coordinates_and_timezone(): void
    {
        $venue = $this->present(Venue::factory()->create([
            'house_number' => '26-35', 'postal_code' => 'C1414BBC',
            'country_code' => 'AR', 'lat' => 0, 'lng' => 0,
            'timezone' => 'America/Argentina/Buenos_Aires',
        ])->fresh());
        expect($venue->house_number)->toBe('26-35')
            ->and($venue->postal_code)->toBe('C1414BBC')
            ->and($venue->lat)->toBe('0.0000000');
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        expect($event->timezone)->toBeNull()
            ->and($this->present($event->venue)->timezone)->toBe($venue->timezone);
    }
}
