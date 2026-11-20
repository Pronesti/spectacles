<?php

namespace Tests\Feature;

use App\Http\Data\ProviderRefData;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RecordedRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\Support\ProviderHttp;
use Tests\TestCase;

class SetlistFmApiDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2));
        Cache::flush();
        Sleep::fake(syncWithCarbon: true);
        config(['services.setlistfm.enabled' => true, 'services.setlistfm.api_key' => 'test-server-secret', 'services.setlistfm.api_interval_ms' => 0, 'services.setlistfm.api_daily_limit' => 100, 'services.setlistfm.cache_seconds' => 900]);
        $this->actingAs(User::factory()->create());
        $this->withHeaders(['Accept' => 'application/json']);
    }

    protected function tearDown(): void
    {
        Sleep::fake(false);
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function searchUrl(array $filters = []): string
    {
        return '/api/v1/providers/setlistfm/performances?'.http_build_query(['artistMbid' => SetlistFmApiTest::ARTIST_MBID, 'period' => 'past', ...$filters]);
    }

    /**
     * @param  array<array-key, mixed>  $row
     */
    private function previewUrl(array $row): string
    {
        return '/api/v1/providers/setlistfm/performances/'.$this->stringAt($row, 'ref.externalId').'?'.http_build_query(['locator' => $row['locator']]);
    }

    /**
     * @param  list<array<array-key, mixed>>  $rows
     */
    private function fakeSearch(array $rows, int $total = 1): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/rest/1.0/search/setlists*' => ProviderHttp::response(['setlist' => $rows, 'total' => $total, 'page' => 1, 'itemsPerPage' => 20]), 'https://api.setlist.fm/rest/1.0/setlist/*' => ProviderHttp::response($rows[0] ?? [])]);
    }

    public function test_search_and_preview_read_only_the_api(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance()]);
        $row = $this->arrayAt($this->get($this->searchUrl())->assertOk()->json('data.0'));
        expect(json_encode($row, JSON_THROW_ON_ERROR))->not->toContain('test-server-secret');
        $this->get($this->previewUrl($row))
            ->assertOk()->assertJsonPath('data.0.artist.values.name', 'The Beatles')->assertJsonPath('data.0.venue.values.name', 'Hollywood Bowl')->assertJsonPath('data.0.date.year', 1964);
        ProviderHttp::assertSentCount(2);
        ProviderHttp::assertNotSent(fn (RecordedRequest $r): bool => parse_url($r->url(), PHP_URL_HOST) !== 'api.setlist.fm');
        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('tours', 0);
    }

    public function test_preview_keeps_its_field_order_and_identity_keys(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance()]);
        $row = $this->arrayAt($this->get($this->searchUrl())->assertOk()->json('data.0'));
        expect(array_keys($row))->toBe(['ref', 'url', 'date', 'artistName', 'venueName', 'city', 'localEventId', 'locator']);
        $preview = $this->arrayAt($this->get($this->previewUrl($row))->assertOk()->json('data.0'));
        expect(array_keys($preview))->toBe(['ref', 'url', 'date', 'artistName', 'venueName', 'city', 'localEventId', 'locator', 'artist', 'tour', 'tourName', 'venue', 'time', 'timezone', 'selection', 'localEvent'])
            ->and(array_keys($this->arrayAt($preview, 'artist')))->toBe(['ref', 'values', 'url', 'selection', 'localId'])
            ->and(array_keys($this->arrayAt($preview, 'venue')))->toBe(['ref', 'values', 'url', 'linkedRefs', 'venueUrl', 'venueSources', 'localId', 'selection'])
            ->and(ProviderRefData::from($preview['ref'])->key())->toBe('{"provider":"setlist_fm","resourceType":"setlist","externalId":"63de4613"}');
    }

    public function test_upcoming_only_offers_api_rows_with_future_dates(): void
    {
        $future = SetlistFmApiTest::performance(['eventDate' => '12-12-2026', 'id' => 'future', 'url' => 'https://www.setlist.fm/setlist/the-beatles/2026/hollywood-bowl-hollywood-ca-future.html']);
        $this->fakeSearch([SetlistFmApiTest::performance(), $future], 2);
        $this->get($this->searchUrl(['period' => 'upcoming']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.ref.externalId', 'future');
        ProviderHttp::assertSentCount(1);
    }

    public function test_search_translates_documented_api_filters_and_checks_each_result(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance()]);
        $this->get($this->searchUrl(['date' => '1964-08-23', 'year' => 1964, 'countryCode' => 'US', 'venueId' => '6bd6ca6e']))->assertOk()->assertJsonCount(1, 'data');
        ProviderHttp::assertSent(fn (RecordedRequest $r): bool => $r['date'] === '23-08-1964' && $r['year'] == 1964 && $r['countryCode'] === 'US' && $r['venueId'] === '6bd6ca6e');
    }

    public function test_search_drops_rows_outside_the_requested_year(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance()]);
        $this->get($this->searchUrl(['year' => '1965']))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cursor_cannot_be_reused_with_another_search_scope(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance()], 40);
        $cursor = $this->get($this->searchUrl())->assertOk()->json('meta.next_cursor');
        expect($cursor)->not->toBeNull();
        $this->get($this->searchUrl(['cursor' => $cursor, 'period' => 'upcoming']))->assertUnprocessable();
        ProviderHttp::assertSentCount(1);
    }

    public function test_api_auth_failure_is_reported_without_another_request(): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response('', 403)]);
        $this->get($this->searchUrl())->assertStatus(503)->assertJsonPath('reason', 'setlistfm_api_auth');
        ProviderHttp::assertSentCount(1);
    }

    public function test_venue_lookup_uses_api_without_website_url_or_musicbrainz(): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/rest/1.0/venue/6bd6ca6e' => ProviderHttp::response($this->arrayAt(SetlistFmApiTest::performance(), 'venue'))]);
        $this->get('/api/v1/providers/setlistfm/venues/6bd6ca6e')->assertOk()->assertJsonPath('data.0.ref.externalId', '6bd6ca6e');
        ProviderHttp::assertSentCount(1);
    }

    public function test_preview_is_unavailable_once_the_api_key_is_removed(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance()]);
        $row = $this->arrayAt($this->get($this->searchUrl())->assertOk()->json('data.0'));
        config(['services.setlistfm.api_key' => '']);
        $this->get($this->previewUrl($row))->assertStatus(503);
        ProviderHttp::assertSentCount(1);
    }

    public function test_tours_offer_only_manual_names_without_fabricated_provider_identities(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance(), SetlistFmApiTest::performance(['id' => 'other', 'url' => 'https://www.setlist.fm/setlist/the-beatles/1964/other-other.html'])], 2);
        $this->get('/api/v1/providers/setlistfm/tours?'.http_build_query(['artistMbid' => SetlistFmApiTest::ARTIST_MBID, 'q' => 'north american']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.manual', true)->assertJsonPath('data.0.ref', null)->assertJsonPath('data.0.selection', null)->assertJsonPath('data.0.values.name', 'North American Tour 1964');
        ProviderHttp::assertSentCount(1);
        $this->assertDatabaseCount('tours', 0);
        $this->assertDatabaseCount('tour_provider_ids', 0);
    }

    public function test_tour_name_filter_does_not_require_or_invent_a_tour_id(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance()]);
        $this->get($this->searchUrl(['tourName' => 'North American Tour 1964']))->assertOk()->assertJsonCount(1, 'data');
        ProviderHttp::assertSent(fn (RecordedRequest $r): bool => $r['tourName'] === 'North American Tour 1964' && ! isset($r['tourId']));
        $this->get($this->searchUrl(['tourName' => 'North American Tour 1964', 'tourId' => 'old-website-tour']))->assertUnprocessable();
        ProviderHttp::assertSentCount(1);
    }

    public function test_api_preview_saves_a_manual_tour_without_a_tour_provider_identity(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance()]);
        $row = $this->arrayAt($this->get($this->searchUrl())->assertOk()->json('data.0'));
        $preview = $this->arrayAt($this->get($this->previewUrl($row))->assertOk()->json('data.0'));
        expect($preview['tourName'])->toBe('North American Tour 1964');
        $artistKey = (string) Str::uuid();
        $venueKey = (string) Str::uuid();
        $body = [
            'concert' => ['id' => (string) Str::uuid(), 'title' => $this->stringAt($preview, 'artist.values.name'), 'artists' => [$this->stringAt($preview, 'artist.values.name')], 'venueId' => $venueKey, 'date' => $preview['date'], 'time' => '', 'timezone' => null, 'interested' => true, 'ticketsBought' => false, 'notes' => 'Keep this note', 'catalog' => ['artistIds' => [null], 'tours' => [['name' => $preview['tourName'], 'catalogId' => null]]], 'setlist' => []],
            'venue' => ['id' => $venueKey, 'name' => $this->stringAt($preview, 'venue.values.name'), 'city' => $this->stringAt($preview, 'venue.values.city'), 'neighborhood' => '', 'lat' => null, 'lng' => null, 'address' => null, 'aliases' => [], 'image' => null],
            'pendingCatalog' => ['artists' => [$artistKey => [...$this->arrayAt($preview, 'artist.values'), 'selection' => $this->stringAt($preview, 'artist.selection')]], 'songs' => [], 'lineup' => ['0' => $artistKey], 'entries' => [], 'tours' => [], 'tourChoices' => [], 'venue' => ['selection' => $this->stringAt($preview, 'venue.selection')], 'event' => ['selection' => $this->stringAt($preview, 'selection')]],
        ];
        $changed = $body;
        $changed['concert']['catalog']['tours'][0]['name'] = 'Different Tour';
        $this->postJson('/api/v1/attendances', $changed)->assertUnprocessable()->assertJsonValidationErrors('pendingCatalog.event.selection');
        $this->assertDatabaseCount('events', 0);
        $this->postJson('/api/v1/attendances', $body)->assertOk()->assertJsonPath('data.concerts.0.notes', 'Keep this note');
        $this->assertDatabaseHas('event_provider_references', ['provider' => 'setlist_fm', 'provider_resource_id' => '63de4613']);
        $this->assertDatabaseHas('tours', ['name' => 'North American Tour 1964']);
        $this->assertDatabaseCount('tour_provider_ids', 0);
        ProviderHttp::assertSentCount(2);
    }

    public function test_existing_api_event_uses_saved_details_without_another_provider_read(): void
    {
        $this->fakeSearch([SetlistFmApiTest::performance()]);
        $event = Event::factory()->create(['title' => 'Saved title']);
        $event->providerReferences()->create(['provider' => 'setlist_fm', 'resource_type' => 'setlist', 'provider_resource_id' => '63de4613']);
        $row = $this->arrayAt($this->get($this->searchUrl())->assertOk()->json('data.0'));
        $this->get($this->previewUrl($row))
            ->assertOk()->assertJsonPath('data.0.localEvent.title', 'Saved title');
        ProviderHttp::assertSentCount(1);
    }
}
