<?php

namespace Tests\Feature;

use App\Http\Resources\VenueResource;
use App\Models\TourProviderId;
use App\Models\Venue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogProviderMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_and_both_tour_provider_kinds_round_trip(): void
    {
        Http::preventStrayRequests();
        $venue = Venue::factory()->create(['address' => 'Humboldt 450, C1414 CABA', 'aliases' => ['Arena Ñ'], 'image' => null]);
        $this->assertSame(['Arena Ñ'], $venue->fresh()->aliases);
        $this->assertSame([], Venue::factory()->create()->fresh()->aliases);
        foreach ([['musicbrainz', 'series', fake()->uuid()], ['setlist_fm', 'tour', 'bdac5de']] as [$provider, $kind, $id]) {
            $ref = TourProviderId::factory()->create(['provider' => $provider, 'resource_type' => $kind, 'external_provider_id' => $id]);
            $this->assertSame($id, $ref->fresh()->external_provider_id);
        }
        $this->assertFalse(Schema::hasColumn('tour_provider_ids', 'provider_resource_id'));
        $legacy = Venue::factory()->create(['aliases' => null]);
        $this->assertSame([], json_decode(json_encode((new VenueResource($legacy))->resolve()), true)['data']['attributes']['aliases']);
        Http::assertNothingSent();
    }

    public function test_duplicate_tour_identity_is_rejected(): void
    {
        $ref = TourProviderId::factory()->create();
        $this->expectException(UniqueConstraintViolationException::class);
        TourProviderId::factory()->create(['provider' => $ref->provider, 'resource_type' => $ref->resource_type, 'external_provider_id' => $ref->external_provider_id]);
    }

    public function test_migration_rollback_preserves_legacy_series_identity(): void
    {
        $ref = TourProviderId::factory()->create();
        $migration = require database_path('migrations/2026_10_25_000001_extend_catalog_provider_metadata.php');
        $migration->down();
        $this->assertDatabaseHas('tour_provider_ids', ['provider_resource_id' => $ref->external_provider_id]);
        $migration->up();
        $this->assertDatabaseHas('tour_provider_ids', ['external_provider_id' => $ref->external_provider_id]);
    }
}
