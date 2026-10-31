<?php

namespace Tests\Feature;

use App\Http\CatalogProviderSelection;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueProviderId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VenueProviderLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.musicbrainz.enabled' => true, 'services.musicbrainz.user_agent' => 'SpectaclesOfflineTests/1.0']);
        Http::preventStrayRequests();
        Http::fake(require base_path('tests/Fixtures/catalog-provider-http-responses.php'));
        $this->actingAs(User::factory()->create())->withHeaders(['Accept' => 'application/json']);
    }

    public function test_place_coordinates_and_explicit_setlist_link_are_preserved(): void
    {
        $this->get('/api/v1/musicbrainz/places/50573105-4880-4a66-b668-0356374918f0')->assertOk()->assertJsonPath('data.0.values.lat', 42.005)->assertJsonPath('data.0.linkedRefs.0.externalId', '73d6ca11')->assertJsonPath('data.0.values.aliases.0', 'Arena Ñ')->assertJsonPath('data.0.values.address', 'Fixture street 1')->assertJsonPath('data.0.venueUrl', 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html');
        $this->assertDatabaseCount('venues', 0);
        Http::assertSentCount(1);
    }

    public function test_each_explicit_venue_reference_retains_its_own_canonical_url_in_the_proof(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $mbid = '50573105-4880-4a66-b668-0356374918f0';
        $urls = ['https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html', 'https://www.setlist.fm/venue/rosemont-horizon-rosemont-il-usa-63d626d3.html'];
        Http::fake(['https://musicbrainz.org/ws/2/place/*' => Http::response(['id' => $mbid, 'name' => 'Allstate Arena', 'relations' => array_map(fn ($url) => ['type' => 'setlistfm', 'target-type' => 'url', 'url' => ['resource' => $url]], $urls)])]);
        $value = $this->get('/api/v1/musicbrainz/places/'.$mbid)->assertOk()->assertJsonCount(2, 'data.0.venueSources')->assertJsonMissingPath('data.0.venueUrl')->json('data.0');
        $this->assertSame($urls, array_column($value['venueSources'], 'url'));
        $decoded = app(CatalogProviderSelection::class)->read($value['selection'], 'venue', 'selection');
        $this->assertSame(['73d6ca11', '63d626d3'], array_column($decoded['venueSources'], 'externalId'));
        $this->assertSame($urls, array_column($decoded['venueSources'], 'url'));
        Http::assertSentCount(1);
    }

    public function test_place_and_artist_share_the_pacing_gate(): void
    {
        $this->get('/api/v1/musicbrainz/artists?q=Mana')->assertOk();
        $this->get('/api/v1/musicbrainz/places/50573105-4880-4a66-b668-0356374918f0')->assertStatus(429);
        Http::assertSentCount(1);
    }

    public function test_optional_image_has_safe_attribution_and_accepts_thumbnail_host(): void
    {
        config(['services.venue_images.enabled' => true, 'services.venue_images.wikidata_daily_limit' => 10, 'services.venue_images.commons_daily_limit' => 10]);
        $image = $this->get('/api/v1/musicbrainz/places/50573105-4880-4a66-b668-0356374918f0')->assertOk()->json('data.0.values.image');
        $this->assertSame('BugWarp', $image['credit']);
        $this->assertSame('CC BY-SA 4.0', $image['license']);
        $this->assertStringStartsWith('https://thumb.wikimedia.org/', $image['thumbnailUrl']);
        Http::assertSentCount(3);
    }

    public function test_independently_confirmed_venue_proofs_create_nothing_and_conflicts_are_explicit(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $proofs = app(CatalogProviderSelection::class);
        $place = $f['venue'];
        $place['ref'] = ['provider' => 'musicbrainz', 'resourceType' => 'place', 'externalId' => fake()->uuid()];
        $place['url'] = 'https://musicbrainz.org/place/'.$place['ref']['externalId'];
        $body = ['selections' => [$proofs->issue('venue', $place), $proofs->issue('venue', $f['venue'])]];
        $this->postJson('/api/v1/providers/venues/confirm', $body)->assertOk()->assertJsonPath('data.0.linkedRefs.0.externalId', '73d6ca11');
        $this->assertDatabaseCount('venues', 0);
        Http::assertNothingSent();
        VenueProviderId::factory()->create(['provider' => 'musicbrainz', 'resource_type' => 'place', 'provider_resource_id' => $place['ref']['externalId']]);
        VenueProviderId::factory()->create(['provider' => 'setlist_fm', 'resource_type' => 'venue', 'provider_resource_id' => '73d6ca11']);
        $this->postJson('/api/v1/providers/venues/confirm', $body)->assertConflict();
    }

    public function test_missing_image_rights_or_failed_metadata_do_not_block_place(): void
    {
        config(['services.venue_images.enabled' => true, 'services.venue_images.wikidata_daily_limit' => 10, 'services.venue_images.commons_daily_limit' => 10]);
        foreach ([Http::response(['query' => ['pages' => ['1' => ['imageinfo' => [['url' => 'https://evil.example/image.jpg']]]]]]), Http::response([], 503)] as $response) {
            Cache::flush();
            Http::swap(new Factory);
            Http::preventStrayRequests();
            $responses = require base_path('tests/Fixtures/catalog-provider-http-responses.php');
            $responses['https://commons.wikimedia.org/w/api.php*'] = $response;
            Http::fake($responses);
            $this->get('/api/v1/musicbrainz/places/50573105-4880-4a66-b668-0356374918f0')->assertOk()->assertJsonPath('data.0.values.image', null);
        }
    }

    public function test_explicit_cross_link_conflict_never_reassigns_saved_venues(): void
    {
        VenueProviderId::factory()->create(['provider' => 'musicbrainz', 'resource_type' => 'place', 'provider_resource_id' => '50573105-4880-4a66-b668-0356374918f0']);
        VenueProviderId::factory()->create(['provider' => 'setlist_fm', 'resource_type' => 'venue', 'provider_resource_id' => '73d6ca11']);
        $this->get('/api/v1/musicbrainz/places/50573105-4880-4a66-b668-0356374918f0')->assertConflict();
        $this->assertDatabaseCount('venues', 2);
        $this->assertDatabaseCount('venue_provider_ids', 2);
    }

    public function test_manual_saved_venue_has_no_invented_provider_identity(): void
    {
        $venue = Venue::factory()->create();
        $this->get('/api/v1/providers/venues/'.$venue->external_id)->assertOk()->assertJsonPath('data.refs', []);
        Http::assertNothingSent();
    }
}
