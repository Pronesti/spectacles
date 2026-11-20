<?php

namespace Tests\Feature;

use App\Models\ArtistProviderId;
use App\Models\Event;
use App\Models\EventProviderReference;
use App\Models\SongLink;
use App\Models\SongProviderId;
use App\Models\TourProviderId;
use App\Models\VenueProviderId;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderReferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_resources_cannot_claim_two_parents(): void
    {
        foreach ([ArtistProviderId::class, VenueProviderId::class, TourProviderId::class, SongProviderId::class] as $class) {
            $one = Factory::factoryForModel($class)->createOne();
            $two = Factory::factoryForModel($class)->makeOne()->getAttributes();
            $two['external_id'] = (string) Str::uuid7();
            $two['provider'] = $one->provider;
            $two['resource_type'] = $one->resource_type;
            $column = $one instanceof TourProviderId ? 'external_provider_id' : 'provider_resource_id';
            $two[$column] = $one->getAttribute($column);
            $this->assertDatabaseRejects(fn () => DB::table($one->getTable())->insert($two));
        }
    }

    public function test_provider_ids_are_strings_and_multiple_handles_can_share_a_parent(): void
    {
        $one = VenueProviderId::factory()->create(['provider_resource_id' => '0000123']);
        $two = VenueProviderId::factory()->create([
            'venue_id' => $one->venue_id, 'provider_resource_id' => str_repeat('a', 512),
        ]);
        expect($this->present($one->fresh())->provider_resource_id)->toBe('0000123')
            ->and($this->present($two->fresh())->provider_resource_id)->toBe(str_repeat('a', 512))
            ->and($this->present($one->venue)->providerIds()->count())->toBe(2)
            ->and($one->provider_resource_id)->not->toBe($one->external_id);
    }

    public function test_single_performance_reference_cannot_be_reused_on_another_event(): void
    {
        $one = EventProviderReference::factory()->create();
        $row = $one->getAttributes();
        unset($row['id']);
        $row['event_id'] = Event::factory()->create()->id;
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('event_provider_references')->insert($row));
    }

    public function test_multiday_reference_and_playable_link_are_parent_scoped(): void
    {
        $one = EventProviderReference::factory()->create(['provider' => 'musicbrainz', 'resource_type' => 'event']);
        EventProviderReference::factory()->create([
            'provider' => $one->provider, 'resource_type' => $one->resource_type,
            'provider_resource_id' => $one->provider_resource_id,
        ]);
        $link = SongLink::factory()->create();
        SongLink::factory()->create(['song_id' => $link->song_id]);
        SongLink::factory()->create(['provider_resource_id' => $link->provider_resource_id]);
        expect($this->present($link->song)->links()->count())->toBe(2);
        $row = $link->getAttributes();
        unset($row['id']);
        $row['external_id'] = (string) Str::uuid7();
        $this->assertDatabaseRejects(fn () => DB::table('song_links')->insert($row));
    }

    public function test_openstreetmap_resource_type_is_part_of_identity(): void
    {
        VenueProviderId::factory()->create(['provider' => 'openstreetmap', 'resource_type' => 'node', 'provider_resource_id' => '123']);
        VenueProviderId::factory()->create(['provider' => 'openstreetmap', 'resource_type' => 'way', 'provider_resource_id' => '123']);
        expect(VenueProviderId::where('provider', 'openstreetmap')->count())->toBe(2);
    }
}
