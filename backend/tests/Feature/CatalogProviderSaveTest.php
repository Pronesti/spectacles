<?php

namespace Tests\Feature;

use App\Http\CatalogProviderSelection;
use App\Http\MusicBrainzSelection;
use App\Models\Event;
use App\Models\SetlistEntry;
use App\Models\User;
use App\Models\VenueProviderId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogProviderSaveTest extends TestCase
{
    use RefreshDatabase;

    private function requestBody(): array
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $proofs = app(CatalogProviderSelection::class);
        $artist = (string) Str::uuid();
        $tour = (string) Str::uuid();
        $venue = (string) Str::uuid();

        return [
            'concert' => ['id' => (string) Str::uuid(),
                'title' => 'Maná', 'artists' => ['Maná'], 'venueId' => $venue,
                'date' => $f['performance']['date'], 'time' => '', 'timezone' => null,
                'interested' => true, 'ticketsBought' => false, 'notes' => 'Keep my note',
                'catalog' => ['artistIds' => [null],
                    'tours' => [['name' => 'Drama y luz', 'catalogId' => null]]],
                'setlist' => []],
            'venue' => ['id' => $venue, 'name' => 'Allstate Arena', 'city' => 'Rosemont',
                'neighborhood' => '', 'lat' => null, 'lng' => null,
                'address' => null, 'aliases' => [], 'image' => null],
            'pendingCatalog' => ['artists' => [$artist => ['name' => 'Maná', 'type' => 'group',
                'disambiguation' => null, 'selection' => app(MusicBrainzSelection::class)
                    ->issue('artist', $f['mbid'])]], 'songs' => [], 'lineup' => ['0' => $artist],
                'entries' => [], 'tours' => [$tour => ['name' => 'Drama y luz',
                    'selection' => $proofs->issue('tour', $f['tour'])]],
                'tourChoices' => ['0' => $tour],
                'venue' => ['selection' => $proofs->issue('venue', $f['venue'])],
                'event' => ['selection' => $proofs->issue('performance', $f['performance'])]],
        ];
    }

    public function test_html_discovery_selection_saves_without_api_key_or_further_http(): void
    {
        Cache::flush();
        config(['services.setlistfm.enabled' => true, 'services.setlistfm.api_key' => '', 'services.setlistfm.user_agent' => 'SpectaclesOfflineTests/1.0', 'services.setlistfm.html_daily_limit' => 100, 'services.setlistfm.html_interval_ms' => 0, 'services.setlistfm.cache_seconds' => 900, 'services.musicbrainz.enabled' => true, 'services.musicbrainz.user_agent' => 'SpectaclesOfflineTests/1.0']);
        Http::preventStrayRequests();
        Http::fake(require base_path('tests/Fixtures/catalog-provider-http-responses.php'));
        $this->actingAs(User::factory()->create())->withHeaders(['Accept' => 'application/json']);
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $summary = $this->get('/api/v1/providers/setlistfm/performances?period=past&artistMbid='.$f['mbid'])->assertOk()->json('data.0');
        $preview = $this->get('/api/v1/providers/setlistfm/performances/6bc56e8a?'.http_build_query(['locator' => $summary['locator']]))->assertOk()->json('data.0');
        $body = $this->requestBody();
        $body['concert']['date'] = $preview['date'];
        $body['pendingCatalog']['event']['selection'] = $preview['selection'];
        $body['pendingCatalog']['venue']['selection'] = $preview['venue']['selection'];
        $tour = array_key_first($body['pendingCatalog']['tours']);
        $body['pendingCatalog']['tours'][$tour]['selection'] = $preview['tour']['selection'];
        $reads = count(Http::recorded());
        $this->postJson('/api/v1/attendances', $body)->assertOk()->assertJsonPath('data.concerts.0.notes', 'Keep my note');
        Http::assertSentCount($reads);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.setlist.fm'));
        $this->assertDatabaseHas('event_provider_references', ['provider' => 'setlist_fm', 'provider_resource_id' => '6bc56e8a']);
        $this->assertDatabaseHas('tour_provider_ids', ['provider' => 'setlist_fm', 'external_provider_id' => '2bde703a']);
        $this->assertDatabaseHas('venue_provider_ids', ['provider' => 'setlist_fm', 'provider_resource_id' => '73d6ca11', 'source_url' => 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html']);
    }

    public function test_same_performance_reuses_event_without_upstream_calls(): void
    {
        Http::preventStrayRequests();
        $first = $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/attendances', $this->requestBody())->assertOk()->json('data.concerts.0');
        $second = $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/attendances', $this->requestBody())->assertOk()->json('data.concerts.0');
        $this->assertSame($first['catalog']['eventId'], $second['catalog']['eventId']);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseHas('tour_provider_ids', ['provider' => 'setlist_fm',
            'resource_type' => 'tour', 'external_provider_id' => '2bde703a']);
        Http::assertNothingSent();
    }

    public function test_late_local_event_conflict_rolls_back_new_entities(): void
    {
        Http::preventStrayRequests();
        $body = $this->requestBody();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/attendances', $body)->assertOk();
        Event::firstOrFail()->update(['title' => 'Saved authoritative title']);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/attendances', $this->requestBody())->assertConflict();
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('venues', 1);
        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseCount('tours', 1);
        $this->assertSame('Saved authoritative title', Event::firstOrFail()->title);
        Http::assertNothingSent();
    }

    public function test_altered_date_or_identity_cannot_claim_performance_and_proofs_expire(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        $body = $this->requestBody();
        $body['concert']['date']['day'] = 29;
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('artists', 0);
        $this->assertDatabaseCount('venues', 0);
        $body = $this->requestBody();
        $this->travel(25)->hours();
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable()->assertJsonValidationErrors('pendingCatalog.event.selection');
        $this->assertDatabaseCount('attendances', 0);
        Http::assertNothingSent();
    }

    public function test_same_account_duplicate_is_conflict_and_personal_rows_survive(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        $first = $this->postJson('/api/v1/attendances', $this->requestBody())->assertOk();
        $first->assertJsonPath('data.concerts.0.notes', 'Keep my note');
        $this->postJson('/api/v1/attendances', $this->requestBody())->assertConflict();
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_same_name_artists_on_same_date_and_venue_keep_distinct_performance_events(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        $first = $this->requestBody();
        $this->postJson('/api/v1/attendances', $first)->assertOk();
        $second = $this->requestBody();
        $service = app(CatalogProviderSelection::class);
        $group = $service->read($second['pendingCatalog']['event']['selection'], 'performance', 'event');
        $id = fake()->uuid();
        $group['ref']['externalId'] = 'secondperformance';
        $group['url'] = 'https://www.setlist.fm/show/2026-10-30/arena-2b893c8a.html?setlist=secondperformance';
        $group['artist']['ref']['externalId'] = $id;
        $group['artist']['url'] = 'https://www.setlist.fm/setlists/mana-otherartist.html';
        $key = array_key_first($second['pendingCatalog']['artists']);
        $second['pendingCatalog']['artists'][$key]['selection'] = app(MusicBrainzSelection::class)->issue('artist', $id);
        $second['pendingCatalog']['event']['selection'] = $service->issue('performance', $group);
        $this->postJson('/api/v1/attendances', $second)->assertOk();
        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseCount('artists', 2);
        $this->assertDatabaseCount('venues', 1);
        $this->assertDatabaseCount('event_provider_references', 2);
        Http::assertNothingSent();
    }

    public function test_invalid_setlist_row_rolls_back_all_catalog_writes(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        $body = $this->requestBody();
        $body['concert']['setlist'] = [['id' => fake()->uuid(), 'type' => 'note', 'title' => 'Invalid identity', 'artist' => '', 'tape' => true]];
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
        foreach (['artists', 'tours', 'venues', 'events', 'attendances', 'event_provider_references'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_late_setlist_identity_collision_rolls_back_the_source_graph(): void
    {
        Http::preventStrayRequests();
        $entry = SetlistEntry::factory()->create();
        $counts = [];
        foreach (['artists', 'tours', 'venues', 'events', 'attendances', 'event_provider_references'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $this->actingAs(User::factory()->create());
        $body = $this->requestBody();
        $body['concert']['setlist'] = [['id' => $entry->external_id, 'type' => 'note', 'title' => 'Private collision', 'artist' => '', 'tape' => false]];
        $this->postJson('/api/v1/attendances', $body)->assertConflict();
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        Http::assertNothingSent();
    }

    public function test_confirmed_provider_save_backfills_source_url_without_overwriting_saved_venue_facts(): void
    {
        Http::preventStrayRequests();
        $ref = VenueProviderId::factory()->create(['provider' => 'setlist_fm', 'resource_type' => 'venue', 'provider_resource_id' => '73d6ca11', 'source_url' => null]);
        $ref->venue->update(['name' => 'Saved authoritative Arena', 'city' => 'Saved city']);
        $this->actingAs(User::factory()->create());
        $body = $this->requestBody();
        unset($body['pendingCatalog']['event']);
        $this->postJson('/api/v1/attendances', $body)->assertOk();
        $this->assertDatabaseCount('venues', 1);
        $this->assertSame('Saved authoritative Arena', $ref->venue->fresh()->name);
        $this->assertSame('Saved city', $ref->venue->fresh()->city);
        $this->assertSame('https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html', $ref->fresh()->source_url);
        Http::assertNothingSent();
    }

    public function test_multiple_venue_sources_are_saved_only_on_the_matching_reference(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $otherUrl = 'https://www.setlist.fm/venue/rosemont-horizon-rosemont-il-usa-63d626d3.html';
        $value = [...$f['venue'], 'linkedRefs' => [['provider' => 'setlist_fm', 'resourceType' => 'venue', 'externalId' => '63d626d3']], 'venueSources' => [['externalId' => '73d6ca11', 'url' => $f['venue']['url']], ['externalId' => '63d626d3', 'url' => $otherUrl]]];
        $body = $this->requestBody();
        unset($body['pendingCatalog']['event']);
        $body['pendingCatalog']['venue']['selection'] = app(CatalogProviderSelection::class)->issue('venue', $value);
        $this->postJson('/api/v1/attendances', $body)->assertOk();
        $this->assertDatabaseCount('venues', 1);
        $this->assertDatabaseHas('venue_provider_ids', ['provider_resource_id' => '73d6ca11', 'source_url' => $f['venue']['url']]);
        $this->assertDatabaseHas('venue_provider_ids', ['provider_resource_id' => '63d626d3', 'source_url' => $otherUrl]);
        Http::assertNothingSent();
    }

    public function test_standalone_reviewed_venue_metadata_persists_without_performance_binding(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        $body = $this->requestBody();
        unset($body['pendingCatalog']['event']);
        $body['venue']['address'] = 'Fixture full address Ñ';
        $body['venue']['aliases'] = ['Arena Ñ'];
        $this->postJson('/api/v1/attendances', $body)->assertOk()->assertJsonPath('data.venues.0.address', 'Fixture full address Ñ')->assertJsonPath('data.venues.0.aliases.0', 'Arena Ñ');
        $this->assertDatabaseCount('event_provider_references', 0);
        $this->assertDatabaseCount('venue_provider_ids', 1);
        Http::assertNothingSent();
    }
}
