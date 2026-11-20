<?php

namespace Tests\Feature;

use App\Http\Resources\VenueResource;
use App\Models\TourProviderId;
use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ProviderHttp;
use Tests\TestCase;

class CatalogProviderMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_and_both_tour_provider_kinds_round_trip(): void
    {
        $venue = Venue::factory()->create(['address' => 'Humboldt 450, C1414 CABA', 'aliases' => ['Arena Ñ'], 'image' => null]);
        expect($this->present($venue->fresh())->aliases)->toBe(['Arena Ñ'])
            ->and($this->present(Venue::factory()->create()->fresh())->aliases)->toBe([]);
        foreach ([['musicbrainz', 'series', fake()->uuid()], ['setlist_fm', 'tour', 'bdac5de']] as [$provider, $kind, $id]) {
            $ref = TourProviderId::factory()->create(['provider' => $provider, 'resource_type' => $kind, 'external_provider_id' => $id]);
            expect($this->present($ref->fresh())->external_provider_id)->toBe($id);
        }
        expect(Schema::hasColumn('tour_provider_ids', 'provider_resource_id'))->toBeFalse();
        $legacy = Venue::factory()->create(['aliases' => null]);
        expect($this->arrayAt(json_decode(json_encode((new VenueResource($legacy))->resolve(), JSON_THROW_ON_ERROR), true), 'data.attributes.aliases'))->toBeEmpty();
        ProviderHttp::assertNothingSent();
    }

    public function test_duplicate_tour_identity_is_rejected(): void
    {
        $ref = TourProviderId::factory()->create();
        expect(fn () => TourProviderId::factory()->create(['provider' => $ref->provider, 'resource_type' => $ref->resource_type, 'external_provider_id' => $ref->external_provider_id]))->toThrow(UniqueConstraintViolationException::class);
    }

    public function test_migration_rollback_preserves_legacy_series_identity(): void
    {
        $ref = TourProviderId::factory()->create();
        $migration = require database_path('migrations/2026_10_25_000001_extend_catalog_provider_metadata.php');
        if (! $migration instanceof Migration || ! method_exists($migration, 'down') || ! method_exists($migration, 'up')) {
            $this->fail('The migration must define up() and down().');
        }
        $migration->down();
        $this->assertDatabaseHas('tour_provider_ids', ['provider_resource_id' => $ref->external_provider_id]);
        $migration->up();
        $this->assertDatabaseHas('tour_provider_ids', ['external_provider_id' => $ref->external_provider_id]);
    }
}
