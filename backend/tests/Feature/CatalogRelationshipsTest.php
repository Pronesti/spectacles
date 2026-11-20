<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Event;
use App\Models\EventArtist;
use App\Models\EventTour;
use App\Models\Tour;
use App\Models\TourArtist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_artists_and_optional_multiple_tours(): void
    {
        $event = Event::factory()->create();
        $this->assertSame(0, $event->tourLinks()->count());
        $artist = Artist::factory()->create();
        EventArtist::factory()->create(['event_id' => $event->id, 'artist_id' => $artist->id]);
        EventArtist::factory()->create(['event_id' => $event->id]);
        $tour = Tour::factory()->create();
        TourArtist::factory()->create(['tour_id' => $tour->id, 'artist_id' => $artist->id]);
        EventTour::factory()->create(['event_id' => $event->id, 'tour_id' => $tour->id]);
        EventTour::factory()->create(['event_id' => $event->id]);
        $this->assertSame(2, $event->artistLinks()->count());
        $this->assertSame(2, $event->tourLinks()->count());
        $this->assertSame(1, $tour->artistLinks()->count());
        $this->assertTrue($this->present($this->present($event->artistLinks()->first())->artist)->is($artist));
    }

    public function test_lineup_orders_positions_with_nulls_last_and_stable_ties(): void
    {
        $event = Event::factory()->create();
        $ids = collect([null, 2, 1, 2, null])->map(
            fn (?int $position): int => EventArtist::factory()->create(['event_id' => $event->id, 'position' => $position])->id
        )->all();
        $expected = [$ids[2], $ids[1], $ids[3], $ids[0], $ids[4]];
        $this->assertSame($expected, $event->artistLinks()->pluck('id')->all());
        $this->assertSame($expected, $this->present($event->fresh())->load('artistLinks')->artistLinks->modelKeys());
    }

    public function test_relationship_pairs_are_unique(): void
    {
        foreach ([EventArtist::class, TourArtist::class, EventTour::class] as $class) {
            $row = Factory::factoryForModel($class)->createOne();
            $copy = $row->getAttributes();
            unset($copy['id']);
            $copy['external_id'] = (string) Str::uuid7();
            $this->assertDatabaseRejects(fn () => DB::table($row->getTable())->insert($copy));
        }
    }
}
