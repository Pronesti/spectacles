<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TourProviderId;
use App\Models\User;
use App\Models\VenueProviderId;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetlistFmDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-25 12:00:00 UTC'));
        Cache::flush();
        config(['services.setlistfm.enabled' => true, 'services.setlistfm.api_key' => '', 'services.setlistfm.user_agent' => 'SpectaclesOfflineTests/1.0', 'services.setlistfm.html_daily_limit' => 100, 'services.setlistfm.html_interval_ms' => 0, 'services.setlistfm.cache_seconds' => 900, 'services.musicbrainz.enabled' => true, 'services.musicbrainz.user_agent' => 'SpectaclesOfflineTests/1.0']);
        Http::preventStrayRequests();
        Http::fake(require base_path('tests/Fixtures/catalog-provider-http-responses.php'));
        $this->actingAs(User::factory()->create());
        $this->withHeaders(['Accept' => 'application/json']);
    }

    private function mbid(): string
    {
        return '55441123-89a2-49ab-ae3f-df7a3e86835a';
    }

    public function test_disabled_html_source_never_calls_musicbrainz_or_setlistfm(): void
    {
        config(['services.setlistfm.enabled' => false]);
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_tours_are_automatic_and_reads_create_nothing(): void
    {
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid().'&q=Drama')->assertOk()->assertJsonPath('data.0.ref.externalId', '2bde703a')->assertJsonPath('data.0.values.name', 'Drama y luz');
        $this->assertDatabaseCount('tours', 0);
        $this->assertDatabaseCount('events', 0);
        Http::assertSentCount(2);
    }

    public function test_empty_202_from_setlist_is_reported_as_unavailable_with_specific_reason(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $responses = require base_path('tests/Fixtures/catalog-provider-http-responses.php');
        $responses['https://www.setlist.fm/setlists/*'] = Http::response('', 202);
        Http::fake($responses);

        $this->get('/api/v1/providers/setlistfm/performances?period=past&artistMbid='.$this->mbid())
            ->assertStatus(503)->assertJsonPath('reason', 'setlistfm_not_ready')->assertJsonMissingPath('data');
    }

    public function test_tour_search_ignores_accents_and_case_without_changing_provider_values(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $responses = require base_path('tests/Fixtures/catalog-provider-http-responses.php');
        $html = file_get_contents(base_path('tests/Fixtures/setlistfm/artist-listing.html'));
        $responses['https://www.setlist.fm/setlists/*'] = Http::response(str_replace('Drama y luz', 'México lindo y querido', $html));
        Http::fake($responses);

        foreach (['mexico lindo y querido', 'MÉXICO LINDO Y QUERIDO', "Me\u{0301}xico lindo y querido"] as $query) {
            $this->get('/api/v1/providers/setlistfm/tours?'.http_build_query(['artistMbid' => $this->mbid(), 'q' => $query]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.ref.provider', 'setlist_fm')
                ->assertJsonPath('data.0.ref.externalId', '2bde703a')
                ->assertJsonPath('data.0.values.name', 'México lindo y querido');
        }
        $this->assertDatabaseCount('tours', 0);
        Http::assertSentCount(2);
    }

    public function test_tour_search_rejects_another_artists_listing_even_when_names_match(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $responses = require base_path('tests/Fixtures/catalog-provider-http-responses.php');
        $responses['https://www.setlist.fm/setlists/*'] = Http::response(str_replace($this->mbid(), 'a74b1b7f-71a5-4011-9441-d0b5e4122711', file_get_contents(base_path('tests/Fixtures/setlistfm/artist-listing.html'))));
        Http::fake($responses);

        $this->get('/api/v1/providers/setlistfm/tours?'.http_build_query(['artistMbid' => $this->mbid(), 'q' => 'drama']))
            ->assertStatus(503)->assertJsonMissingPath('data');
    }

    public function test_upcoming_uses_html_and_locator_binds_performance_id(): void
    {
        $summary = $this->get('/api/v1/providers/setlistfm/performances?period=upcoming&artistMbid='.$this->mbid())->assertOk()->json('data.0');
        $this->assertSame('b4cc196', $summary['ref']['externalId']);
        $preview = $this->get('/api/v1/providers/setlistfm/performances/b4cc196?'.http_build_query(['locator' => $summary['locator']]))->assertOk()->json('data.0');
        $this->assertSame('bdac5de', $preview['tour']['ref']['externalId']);
        $this->assertSame($this->mbid(), $preview['artist']['ref']['externalId']);
        $this->assertNull($preview['time']);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_past_search_and_detail_are_keyless_and_verify_html_artist(): void
    {
        $row = $this->get('/api/v1/providers/setlistfm/performances?period=past&artistMbid='.$this->mbid())->assertOk()->json('data.0');
        $this->get('/api/v1/providers/setlistfm/performances/6bc56e8a?'.http_build_query(['locator' => $row['locator']]))->assertOk()->assertJsonPath('data.0.tour.ref.externalId', '2bde703a')->assertJsonPath('data.0.artist.ref.externalId', $this->mbid());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.setlist.fm') || $request->hasHeader('x-api-key'));
        $this->assertDatabaseCount('events', 0);
    }

    public function test_search_cursor_keeps_both_halves_of_a_website_page(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $responses = require base_path('tests/Fixtures/catalog-provider-http-responses.php');
        $html = file_get_contents(base_path('tests/Fixtures/setlistfm/real-artist.html'));
        $html = str_replace(['radiohead-bd6bd12', 'Radiohead', 'artist=bd6bd12'], ['mana-4bd7afb6', 'Maná', 'artist=4bd7afb6'], $html);
        $html = preg_replace('~<a[^>]+title="Go to next page"[^>]*>.*?</a>~s', '', $html);
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " setlistPreview ")]') as $card) {
            $copy = $card->cloneNode(true);
            foreach ($xpath->query('.//h2/a[@href]', $copy) as $link) {
                $link->setAttribute('href', preg_replace('/-([a-z0-9]+)\.html$/', '-copy$1.html', $link->getAttribute('href')));
            }
            $card->parentNode->appendChild($copy);
        }
        $responses['https://www.setlist.fm/setlists/*'] = Http::response(str_replace('a74b1b7f-71a5-4011-9441-d0b5e4122711', $this->mbid(), $document->saveHTML()));
        Http::fake($responses);
        $url = '/api/v1/providers/setlistfm/performances?period=past&artistMbid='.$this->mbid();
        $first = $this->get($url)->assertOk()->json();
        $this->assertCount(10, $first['data']);
        $second = $this->get($url.'&'.http_build_query(['cursor' => $first['meta']['next_cursor']]))->assertOk()->json();
        $this->assertCount(10, $second['data']);
        $this->assertCount(20, array_unique(array_column(array_column([...$first['data'], ...$second['data']], 'ref'), 'externalId')));
        $this->assertNull($second['meta']['next_cursor']);
    }

    public function test_tour_filter_uses_exact_website_id(): void
    {
        $this->get('/api/v1/providers/setlistfm/performances?'.http_build_query(['period' => 'past', 'artistMbid' => $this->mbid(), 'tourId' => 'different', 'tourName' => 'Drama y luz']))->assertOk()->assertJsonCount(0, 'data');
        $this->get('/api/v1/providers/setlistfm/performances?'.http_build_query(['period' => 'past', 'artistMbid' => $this->mbid(), 'tourId' => '2bde703a', 'tourName' => 'Drama y luz']))->assertOk()->assertJsonPath('data.0.ref.externalId', '6bc56e8a');
    }

    public function test_unavailable_detail_does_not_issue_a_selection(): void
    {
        $row = $this->get('/api/v1/providers/setlistfm/performances?period=past&artistMbid='.$this->mbid())->assertOk()->json('data.0');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://www.setlist.fm/setlist/*' => Http::response('Unavailable', 503)]);
        $this->get('/api/v1/providers/setlistfm/performances/6bc56e8a?'.http_build_query(['locator' => $row['locator']]))->assertStatus(503)->assertJsonMissingPath('data')->assertJsonMissingPath('selection');
        $this->assertDatabaseCount('events', 0);
    }

    public function test_challenges_empty_accepted_responses_and_throttling_are_not_zero_matches(): void
    {
        foreach ([Http::response('challenge', 200), Http::response('', 202), Http::response('', 503), Http::response('', 429, ['Retry-After' => '9']), Http::response('oversized', 200, ['Content-Length' => '2097153'])] as $index => $response) {
            Cache::flush();
            Http::swap(new Factory);
            Http::preventStrayRequests();
            $responses = require base_path('tests/Fixtures/catalog-provider-http-responses.php');
            $responses['https://www.setlist.fm/setlists/*'] = $response;
            Http::fake($responses);
            $result = $this->get('/api/v1/providers/setlistfm/performances?period=past&artistMbid='.$this->mbid());
            $result->assertStatus($index === 3 ? 429 : 503)->assertJsonMissingPath('data');
            if ($index === 3) {
                $result->assertHeader('Retry-After', '9');
            }
            Http::assertSentCount(2);
        }
    }

    public function test_failed_detail_retains_verified_manual_values_without_event_or_tour_identity(): void
    {
        $row = $this->get('/api/v1/providers/setlistfm/performances?period=past&artistMbid='.$this->mbid())->assertOk()->json('data.0');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://www.setlist.fm/setlist/*' => Http::response('', 503)]);
        $this->get('/api/v1/providers/setlistfm/performances/6bc56e8a?'.http_build_query(['locator' => $row['locator']]))->assertStatus(503)->assertJsonPath('manual.artist.ref.externalId', $this->mbid())->assertJsonPath('manual.tourName', 'Drama y luz')->assertJsonPath('manual.venue.values.name', 'Allstate Arena')->assertJsonMissingPath('manual.selection')->assertJsonMissingPath('manual.tour');
    }

    public function test_saved_local_event_adoption_needs_no_detail_enrichment(): void
    {
        $event = Event::factory()->create(['title' => 'Saved authoritative Event']);
        $event->providerReferences()->create(['provider' => 'setlist_fm', 'resource_type' => 'setlist', 'provider_resource_id' => '6bc56e8a']);
        $row = $this->get('/api/v1/providers/setlistfm/performances?period=past&artistMbid='.$this->mbid())->assertOk()->json('data.0');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $this->get('/api/v1/providers/setlistfm/performances/6bc56e8a?'.http_build_query(['locator' => $row['locator']]))->assertOk()->assertJsonPath('data.0.localEvent.title', 'Saved authoritative Event')->assertJsonPath('data.0.localEventId', $event->external_id)->assertJsonPath('data.0.venue.values.name', 'Allstate Arena');
        Http::assertNothingSent();
    }

    public function test_discovery_artist_page_must_match_the_musicbrainz_relationship(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $responses = require base_path('tests/Fixtures/catalog-provider-http-responses.php');
        $responses['https://www.setlist.fm/setlists/*'] = Http::response(str_replace('radiohead-bd6bd12', 'mana-4bd7afb6', file_get_contents(base_path('tests/Fixtures/setlistfm/real-artist.html'))));
        Http::fake($responses);
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid())->assertStatus(503)->assertJsonMissingPath('data');
    }

    public function test_missing_or_conflicting_artist_relationship_never_uses_a_name_match(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://musicbrainz.org/ws/2/artist/*' => Http::response(['id' => $this->mbid(), 'name' => 'Maná', 'relations' => []])]);
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid())->assertStatus(503);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'setlist.fm'));
    }

    public function test_venue_uses_canonical_html_metadata_without_api_key(): void
    {
        $this->get('/api/v1/providers/setlistfm/venues/73d6ca11?'.http_build_query(['venueUrl' => 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html']))->assertOk()->assertJsonPath('data.0.values.name', 'Allstate Arena')->assertJsonPath('data.0.values.city', 'Rosemont')->assertJsonPath('data.0.values.countryCode', 'US')->assertJsonPath('data.0.ref.externalId', '73d6ca11');
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.setlist.fm'));
    }

    public function test_detail_artist_access_challenge_never_downgrades_identity_or_issues_performance_proof(): void
    {
        $row = $this->get('/api/v1/providers/setlistfm/performances?period=past&artistMbid='.$this->mbid())->assertOk()->json('data.0');
        Cache::flush();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'https://www.setlist.fm/setlist/*' => Http::response(file_get_contents(base_path('tests/Fixtures/setlistfm/past-performance.html'))),
            'https://www.setlist.fm/setlists/*' => Http::response('', 202),
        ]);
        $this->get('/api/v1/providers/setlistfm/performances/6bc56e8a?'.http_build_query(['locator' => $row['locator']]))->assertStatus(503)->assertJsonMissingPath('data')->assertJsonMissingPath('manual.selection')->assertJsonPath('manual.artist.ref.externalId', $this->mbid());
        Http::assertSentCount(2);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_artist_listing_honors_local_text_date_and_supported_source_filters(): void
    {
        $path = '/api/v1/providers/setlistfm/performances?';
        $filters = ['period' => 'past', 'artistMbid' => $this->mbid(), 'q' => 'Drama Rosemont', 'date' => '2014-02-14'];
        $this->get($path.http_build_query($filters))->assertOk()->assertJsonPath('data.0.ref.externalId', '6bc56e8a')->assertJsonPath('meta.partial', true);
        $this->get($path.http_build_query([...$filters, 'q' => 'another venue']))->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.partial', true);
        $this->get($path.http_build_query([...$filters, 'countryCode' => 'US', 'year' => 2014]))->assertOk()->assertJsonPath('data.0.ref.externalId', '6bc56e8a');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/setlists/mana-4bd7afb6.html?') && $request['country'] === 'us' && (string) $request['year'] === '2014');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/search') || isset($request['date']) || isset($request['query']));
    }

    public function test_venue_only_history_uses_the_validated_canonical_page(): void
    {
        $url = 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html';
        $this->get('/api/v1/providers/setlistfm/performances?'.http_build_query(['period' => 'past', 'venueId' => '73d6ca11', 'venueUrl' => $url]))->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.partial', true);
        $this->get('/api/v1/providers/setlistfm/performances?'.http_build_query(['period' => 'upcoming', 'venueId' => '73d6ca11', 'venueUrl' => $url]))->assertOk()->assertJsonPath('data.0.artistName', 'Maná')->assertJsonPath('data.0.venueName', 'Allstate Arena');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/search') || str_contains($request->url(), '/venue/venue-'));
    }

    public function test_venue_url_requires_fixed_origin_exact_id_and_no_navigation_query(): void
    {
        foreach ([null, 'https://evil.example/venue/arena-73d6ca11.html', 'https://www.setlist.fm/venue/arena-other.html', 'https://www.setlist.fm/venue/arena-73d6ca11.html?page=2'] as $url) {
            $this->get('/api/v1/providers/setlistfm/venues/73d6ca11'.($url === null ? '' : '?'.http_build_query(['venueUrl' => $url])))->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_saved_source_url_is_reused_without_a_guessed_slug(): void
    {
        $url = 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html';
        $ref = VenueProviderId::factory()->create(['provider' => 'setlist_fm', 'resource_type' => 'venue', 'provider_resource_id' => '73d6ca11', 'source_url' => $url]);
        $this->get('/api/v1/providers/setlistfm/venues/73d6ca11')->assertOk()->assertJsonPath('data.0.localId', $ref->venue->external_id)->assertJsonPath('data.0.venueUrl', $url);
        $this->get('/api/v1/providers/venues/'.$ref->venue->external_id)->assertOk()->assertJsonPath('data.venueUrl', $url);
        Http::assertSent(fn ($request) => $request->url() === $url);
    }

    public function test_venue_only_unsupported_filter_is_actionable_instead_of_ignored(): void
    {
        $this->get('/api/v1/providers/setlistfm/performances?'.http_build_query(['period' => 'past', 'venueId' => '73d6ca11', 'venueUrl' => 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html', 'tourId' => '2bde703a', 'tourName' => 'Drama y luz']))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_local_matches_are_reattached_after_cache_hit(): void
    {
        config(['services.setlistfm.cache_seconds' => 100]);
        $url = '/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid().'&q=Drama';
        $this->get($url)->assertOk()->assertJsonPath('data.0.localId', null);
        $ref = TourProviderId::factory()->create(['provider' => 'setlist_fm', 'resource_type' => 'tour', 'external_provider_id' => '2bde703a']);
        $this->get($url)->assertOk()->assertJsonPath('data.0.localId', $ref->tour->external_id);
        Http::assertSentCount(2);
    }

    public function test_inbound_auth_and_query_allowlists(): void
    {
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid().'&url=https://evil.example')->assertUnprocessable();
        $this->get('/api/v1/providers/setlistfm/tours')->assertUnprocessable();
        $this->get('/api/v1/providers/setlistfm/performances?period=past')->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid())->assertUnauthorized();
        Http::assertNothingSent();
    }
}
