<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventArtist;
use App\Models\EventProviderReference;
use App\Models\EventTour;
use App\Models\SetlistEntry;
use App\Models\Song;
use App\Models\SongLink;
use App\Models\Tour;
use App\Models\TourArtist;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogReadEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
    }

    /** @return array<string, list<string>> */
    public static function catalogs(): array
    {
        return ['events' => ['events'], 'artists' => ['artists'], 'venues' => ['venues'], 'songs' => ['songs'], 'tours' => ['tours']];
    }

    #[DataProvider('catalogs')]
    public function test_each_collection_requires_a_session_and_has_an_empty_document(string $catalog): void
    {
        $this->read($catalog)->assertOk()->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonPath('data', [])->assertJsonPath('meta.per_page', 20);
        auth()->forgetGuards();
        $this->getJson('/api/v1/'.$catalog)->assertUnauthorized();
    }

    public function test_detail_requires_a_session_and_resolves_only_public_uuid(): void
    {
        $event = Event::factory()->create();
        $this->read('events/'.$event->external_id)->assertOk()->assertJsonPath('data.id', $event->external_id);
        $this->read('events/'.$event->id)->assertNotFound();
        $this->read('events/not-a-uuid')->assertNotFound();
        $this->read('events/'.Str::uuid())->assertNotFound();
        auth()->forgetGuards();
        $this->read('events/'.$event->external_id)->assertUnauthorized();
    }

    public function test_shared_documents_preserve_nulls_midnight_coordinates_and_lineup_order(): void
    {
        $venue = Venue::factory()->create(['name' => 'Teatro Ñ', 'street' => null, 'house_number' => '26-35', 'postal_code' => 'C001', 'lat' => 0, 'lng' => 0, 'timezone' => 'America/Argentina/Buenos_Aires']);
        $event = Event::factory()->create(['title' => 'Canción de invierno', 'venue_id' => $venue->id, 'year' => 2024, 'month' => null, 'day' => null, 'local_start_time' => '00:00:00', 'timezone' => null]);
        $last = EventArtist::factory()->create(['event_id' => $event->id, 'position' => null]);
        $second = EventArtist::factory()->create(['event_id' => $event->id, 'position' => 2]);
        $first = EventArtist::factory()->create(['event_id' => $event->id, 'position' => 1]);
        $tour = Tour::factory()->create(['name' => 'Gira del sur']);
        EventTour::factory()->create(['event_id' => $event->id, 'tour_id' => $tour->id]);
        $roster = TourArtist::factory()->create(['tour_id' => $tour->id]);
        $attendance = Attendance::factory()->create(['event_id' => $event->id, 'notes' => 'private-attendance-marker']);
        SetlistEntry::factory()->create(['attendance_id' => $attendance->id, 'notes' => 'private-setlist-marker']);
        Attendance::factory()->create(['event_id' => $event->id, 'owner_user_id' => auth()->id(), 'notes' => 'private-own-marker']);
        $response = $this->read('events/'.$event->external_id, ['include' => 'tourLinks.tour.artistLinks.artist'])->assertOk();
        $response->assertJsonPath('data.attributes.title', 'Canción de invierno')->assertJsonPath('data.attributes.year', 2024)
            ->assertJsonPath('data.attributes.month', null)->assertJsonPath('data.attributes.day', null)
            ->assertJsonPath('data.attributes.local_start_time', '00:00:00')->assertJsonPath('data.attributes.timezone', null);
        $document = $response->json();
        $this->assertSame([$first->external_id, $second->external_id, $last->external_id], array_column($document['data']['relationships']['artistLinks']['data'], 'id'));
        $included = collect($document['included']);
        $publicVenue = $included->firstWhere('type', 'venues');
        $this->assertSame($venue->external_id, $publicVenue['id']);
        $this->assertNull($publicVenue['attributes']['street']);
        $this->assertSame('26-35', $publicVenue['attributes']['house_number']);
        $this->assertSame('0.0000000', $publicVenue['attributes']['lat']);
        $this->assertSame($tour->external_id, $included->firstWhere('type', 'tours')['id']);
        $this->assertTrue($included->contains('id', $roster->artist->external_id));
        $this->assertSharedOnly($document);
        $this->assertStringNotContainsString('private-attendance-marker', $response->getContent());
        $this->assertStringNotContainsString('private-setlist-marker', $response->getContent());
        $this->assertStringNotContainsString('private-own-marker', $response->getContent());
        $unknown = Event::factory()->create(['venue_id' => null]);
        $this->read('events/'.$unknown->external_id)->assertOk()->assertJsonPath('data.relationships.venue.data', null)
            ->assertJsonPath('data.relationships.artistLinks.data', [])->assertJsonPath('data.attributes.year', null);
        $this->read('events', ['filter' => ['q' => 'Canción']])->assertOk()->assertJsonPath('data.0.id', $event->external_id);
    }

    public function test_event_search_groups_artist_matches_with_venue_and_date_filters(): void
    {
        $venue = Venue::factory()->create();
        $artist = Artist::factory()->create(['name' => 'Björk']);
        $wanted = Event::factory()->create(['title' => 'Live night', 'venue_id' => $venue->id, 'year' => 2024, 'month' => 2, 'day' => 29]);
        $other = Event::factory()->create(['title' => 'Other night', 'year' => 2023]);
        foreach ([$wanted, $other] as $event) {
            EventArtist::factory()->create(['event_id' => $event->id, 'artist_id' => $artist->id]);
        }
        $filter = ['q' => 'björk', 'venue_uuid' => $venue->external_id, 'year' => 2024, 'month' => 2, 'day' => 29];
        $this->read('events', ['filter' => $filter])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $wanted->external_id);
        $this->read('events', ['filter' => ['q' => 'Live night']])->assertJsonPath('data.0.id', $wanted->external_id);
        $this->read('events', ['filter' => ['venue_uuid' => (string) Str::uuid()]])->assertOk()->assertJsonPath('data', []);
    }

    public function test_date_filters_preserve_known_precision_and_validate_calendar_dates(): void
    {
        Event::factory()->create(['year' => null]);
        Event::factory()->create(['year' => 2024]);
        Event::factory()->create(['year' => 2024, 'month' => 2]);
        Event::factory()->create(['year' => 2024, 'month' => 2, 'day' => 29]);
        $this->read('events', ['filter' => ['year' => 2024]])->assertJsonCount(3, 'data');
        $this->read('events', ['filter' => ['year' => 2024, 'month' => 2]])->assertJsonCount(2, 'data');
        $this->read('events', ['filter' => ['year' => 2024, 'month' => 2, 'day' => 29]])->assertJsonCount(1, 'data');
        foreach ([['month' => 2], ['day' => 1], ['year' => 2023, 'month' => 2, 'day' => 29], ['year' => 2024, 'month' => 13], ['year' => 0]] as $filter) {
            $this->read('events', ['filter' => $filter])->assertUnprocessable();
        }
    }

    public function test_artist_venue_song_and_tour_selection_uses_available_context(): void
    {
        $artist = Artist::factory()->create(['name' => 'Soda Stereo', 'type' => 'group', 'disambiguation' => 'Banda argentina']);
        Artist::factory()->create(['name' => 'Soda Stereo', 'type' => 'person']);
        $this->read('artists', ['filter' => ['q' => 'argentina', 'type' => 'group']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $artist->external_id);
        $venue = Venue::factory()->create(['name' => 'Teatro Colón', 'city' => 'Buenos Aires', 'country_code' => 'AR']);
        Venue::factory()->create(['name' => 'Teatro Colón', 'city' => 'Other city', 'country_code' => 'UY']);
        $this->read('venues', ['filter' => ['q' => 'Colón', 'city' => 'buenos', 'country_code' => 'ar']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $venue->external_id);
        $song = Song::factory()->create(['title' => 'De música ligera', 'disambiguation' => 'Composición de Cerati y Bosio']);
        Song::factory()->create(['title' => 'De música ligera', 'disambiguation' => 'Different work']);
        SongLink::factory()->create(['song_id' => $song->id]);
        $response = $this->read('songs', ['filter' => ['q' => 'música', 'disambiguation' => 'Cerati']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $song->external_id);
        $this->assertArrayNotHasKey('relationships', $response->json('data.0'));
        $this->assertArrayNotHasKey('included', $response->json());
        $tour = Tour::factory()->create(['name' => 'Gira continental']);
        TourArtist::factory()->create(['tour_id' => $tour->id, 'artist_id' => $artist->id]);
        Tour::factory()->create(['name' => 'Gira continental']);
        $response = $this->read('tours', ['filter' => ['q' => 'continental', 'artist_uuid' => $artist->external_id]])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tour->external_id);
        $this->assertTrue(collect($response->json('included'))->contains('id', $artist->external_id));
        $this->read('tours', ['filter' => ['artist_uuid' => (string) Str::uuid()]])->assertJsonPath('data', []);
    }

    public function test_literal_text_and_duplicate_names_keep_distinct_public_identities(): void
    {
        $literal = Artist::factory()->create(['name' => '100%_! Live']);
        Artist::factory()->create(['name' => '100anything Live']);
        $this->read('artists', ['filter' => ['q' => '100%_!']])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $literal->external_id);
        $a = Artist::factory()->create(['name' => 'Sigur Rós']);
        $b = Artist::factory()->create(['name' => 'Sigur Rós']);
        $response = $this->read('artists', ['filter' => ['q' => 'Sigur Rós']])->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing([$a->external_id, $b->external_id], array_column($response->json('data'), 'id'));
        $this->read('artists', ['filter' => ['q' => "' OR 1=1 --"]])->assertOk()->assertJsonPath('data', []);
    }

    public function test_provider_tuples_match_one_row_and_can_find_multiple_shared_events(): void
    {
        $artist = Artist::factory()->create();
        ArtistProviderId::factory()->create(['artist_id' => $artist->id, 'provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => 'known-artist']);
        $tuple = ['provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => 'known-artist'];
        $response = $this->read('artists', ['filter' => $tuple])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $artist->external_id);
        $this->assertArrayNotHasKey('relationships', $response->json('data.0'));
        $this->read('artists', ['filter' => ['provider' => 'musicbrainz']])->assertUnprocessable();
        $this->read('artists', ['filter' => [...$tuple, 'resource_type' => 'work']])->assertOk()->assertJsonPath('data', []);
        $events = Event::factory()->count(2)->create();
        foreach ($events as $event) {
            EventProviderReference::factory()->create(['event_id' => $event->id, 'provider' => 'manual_source', 'resource_type' => 'festival', 'provider_resource_id' => 'shared-source']);
            EventProviderReference::factory()->create(['event_id' => $event->id, 'provider' => 'other_source', 'resource_type' => 'day', 'provider_resource_id' => 'other-id']);
        }
        $tuple = ['provider' => 'manual_source', 'resource_type' => 'festival', 'provider_resource_id' => 'shared-source'];
        $response = $this->read('events', ['filter' => $tuple])->assertOk()->assertJsonCount(2, 'data');
        $this->assertSharedOnly($response->json());
        $this->read('events', ['filter' => [...$tuple, 'resource_type' => 'day', 'provider_resource_id' => 'other-id']])->assertJsonPath('data', []);
        $this->read('events', ['filter' => ['provider' => 'manual_source', 'resource_type' => 'festival']])->assertUnprocessable();
    }

    #[DataProvider('catalogs')]
    public function test_pagination_is_bounded_stable_and_retains_filters(string $catalog): void
    {
        $class = match ($catalog) {
            'events' => Event::class, 'artists' => Artist::class, 'venues' => Venue::class, 'songs' => Song::class, 'tours' => Tour::class
        };
        $field = in_array($catalog, ['events', 'songs'], true) ? 'title' : 'name';
        $items = $class::factory()->count(3)->create([$field => 'Repeated name']);
        $expected = $items->sortBy('external_id')->pluck('external_id')->all();
        $first = $this->read($catalog, ['filter' => ['q' => 'Repeated'], 'per_page' => 2])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $second = $this->read($catalog, ['filter' => ['q' => 'Repeated'], 'per_page' => 2, 'page' => 2])->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($expected, [...array_column($first->json('data'), 'id'), ...array_column($second->json('data'), 'id')]);
        parse_str(parse_url($first->json('links.next'), PHP_URL_QUERY), $next);
        $this->assertSame('Repeated', $next['filter']['q']);
        $this->assertSame('2', $next['page']);
        $this->read($catalog, ['page' => 99])->assertOk()->assertJsonPath('data', []);
        $this->read($catalog, ['per_page' => 101])->assertUnprocessable();
        $this->read($catalog, ['page' => 0])->assertUnprocessable();
        $this->read($catalog, ['filter' => ['q' => 'no-such-catalog-term']])->assertJsonPath('data', []);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function unsafeQueries(): array
    {
        return [
            'attendance' => ['events', ['include' => 'attendances.members.user']],
            'lineup to private entries' => ['events', ['include' => 'artistLinks.artist.entryLinks.entry.attendance']],
            'venue back edge' => ['events', ['include' => 'venue.events.attendances']],
            'tour back edge' => ['events', ['include' => 'tourLinks.tour.eventLinks.event.attendances']],
            'artist entries' => ['artists', ['include' => 'entryLinks.entry.attendance.members']],
            'venue events' => ['venues', ['include' => 'events.attendances']],
            'song entries' => ['songs', ['include' => 'entries.attendance']],
            'song playable links' => ['songs', ['include' => 'links']],
            'event references' => ['events', ['include' => 'providerReferences']],
            'tour event back edge' => ['tours', ['include' => 'eventLinks.event.attendances']],
            'include array' => ['events', ['include' => ['attendances']]],
            'private filter' => ['events', ['filter' => ['owner_user_id' => 1]]],
            'fake song artist context' => ['songs', ['filter' => ['artist_uuid' => '00000000-0000-4000-8000-000000000001']]],
            'unknown sparse fields' => ['events', ['fields' => ['events' => 'attendances']]],
            'unknown top key' => ['artists', ['sort' => 'name']],
            'filter scalar' => ['songs', ['filter' => 'private']],
            'long query' => ['artists', ['filter' => ['q' => str_repeat('x', 201)]]],
            'page array' => ['events', ['page' => [1]]],
            'page ceiling' => ['venues', ['page' => 100001]],
            'query array' => ['artists', ['filter' => ['q' => ['bad']]]],
            'invalid UUID' => ['events', ['filter' => ['venue_uuid' => '1']]],
            'invalid country' => ['venues', ['filter' => ['country_code' => 'ARG']]],
            'invalid artist UUID' => ['tours', ['filter' => ['artist_uuid' => '1']]],
        ];
    }

    #[DataProvider('unsafeQueries')]
    public function test_unsafe_requests_are_rejected_before_private_queries(string $catalog, array $query): void
    {
        $event = Event::factory()->create();
        $attendance = Attendance::factory()->create(['event_id' => $event->id]);
        SetlistEntry::factory()->create(['attendance_id' => $attendance->id]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->read($catalog, $query)->assertUnprocessable();
            $this->assertNoPrivateQueries(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        if ($catalog === 'events' && array_key_exists('include', $query)) {
            $this->read('events/'.$event->external_id, $query)->assertUnprocessable();
        }
    }

    public function test_get_json_bodies_cannot_bypass_the_include_boundary(): void
    {
        $event = Event::factory()->create();
        foreach (['events', 'events/'.$event->external_id, 'artists', 'venues', 'songs', 'tours'] as $path) {
            $this->json('GET', '/api/v1/'.$path, ['include' => 'attendances.members.user'])->assertUnprocessable()->assertJsonValidationErrors('body');
        }
    }

    public function test_event_default_and_optional_includes_do_not_query_per_result(): void
    {
        $this->eventGraph();
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $response = $this->read('events', ['include' => 'tourLinks.tour.artistLinks.artist', 'per_page' => 100])->assertOk();
                $this->assertSharedOnly($response->json());
                $queries = DB::getQueryLog();
                $this->assertNoPrivateQueries($queries);

                return count($queries);
            } finally {
                DB::disableQueryLog();
            }
        };
        $single = $count();
        for ($index = 0; $index < 20; $index++) {
            $this->eventGraph();
        }
        $this->assertLessThanOrEqual($single + 1, $count());
    }

    public function test_empty_text_and_safe_includes_preserve_default_relationships(): void
    {
        $event = Event::factory()->create();
        $tour = Tour::factory()->create();
        EventTour::factory()->create(['event_id' => $event->id, 'tour_id' => $tour->id]);
        foreach (['', 'venue', 'artistLinks', 'artistLinks.artist'] as $include) {
            $response = $this->read('events', ['filter' => ['q' => '   '], 'include' => $include])->assertOk();
            $response->assertJsonPath('data.0.relationships.venue.data', null)
                ->assertJsonPath('data.0.relationships.artistLinks.data', []);
            $this->assertArrayNotHasKey('tourLinks', $response->json('data.0.relationships'));
            $this->assertSharedOnly($response->json());
        }
        foreach (['tourLinks', 'tourLinks.tour', 'tourLinks.tour.artistLinks', 'tourLinks.tour.artistLinks.artist'] as $include) {
            $response = $this->read('events/'.$event->external_id, ['include' => $include])->assertOk();
            $this->assertSharedOnly($response->json());
        }
    }

    public function test_provider_identity_is_exact_and_combines_with_other_filters(): void
    {
        $artist = Artist::factory()->create(['name' => 'Exact artist']);
        $tuple = ['provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => 'CaseSensitive'];
        ArtistProviderId::factory()->create(['artist_id' => $artist->id, ...$tuple]);
        $this->read('artists', ['filter' => [...$tuple, 'provider_resource_id' => 'casesensitive']])
            ->assertOk()->assertJsonPath('data', []);
        $this->read('artists', ['filter' => [...$tuple, 'q' => 'unrelated']])
            ->assertOk()->assertJsonPath('data', []);
        foreach (array_keys($tuple) as $key) {
            $this->read('artists', ['filter' => [$key => '']])->assertUnprocessable();
            $this->read('artists', ['filter' => [...$tuple, $key => '']])->assertUnprocessable();
        }
    }

    private function eventGraph(): void
    {
        $event = Event::factory()->create(['venue_id' => Venue::factory()->create()->id]);
        EventArtist::factory()->create(['event_id' => $event->id]);
        $link = EventTour::factory()->create(['event_id' => $event->id]);
        TourArtist::factory()->create(['tour_id' => $link->tour_id]);
    }

    private function read(string $path, array $query = []): TestResponse
    {
        $suffix = $query === [] ? '' : '?'.http_build_query($query);

        return $this->get('/api/v1/'.$path.$suffix, ['Accept' => 'application/json']);
    }

    private function assertSharedOnly(array $document): void
    {
        $data = isset($document['data']['id']) ? [$document['data']] : $document['data'];
        foreach ([...$data, ...($document['included'] ?? [])] as $object) {
            $this->assertContains($object['type'], ['events', 'artists', 'venues', 'songs', 'tours', 'event_artists', 'event_tours', 'tour_artists']);
            $this->assertTrue(Str::isUuid($object['id']));
            foreach (array_keys($object['attributes'] ?? []) as $key) {
                $this->assertFalse(in_array($key, ['id', 'external_id', 'notes', 'email', 'password'], true) || str_ends_with($key, '_id'));
            }
            foreach (array_keys($object['relationships'] ?? []) as $name) {
                $this->assertContains($name, ['venue', 'artistLinks', 'artist', 'tourLinks', 'tour']);
            }
        }
    }

    private function assertNoPrivateQueries(array $queries): void
    {
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(attendances|attendance_members|attendance_member_seats|attendance_invitations|attendance_links|setlist_entries|setlist_entry_artists)\b/i', $query['query']);
        }
    }
}
