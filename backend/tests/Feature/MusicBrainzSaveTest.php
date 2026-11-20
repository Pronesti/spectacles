<?php

namespace Tests\Feature;

use App\Http\MusicBrainzSelection;
use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Event;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\ProviderHttp;
use Tests\TestCase;

/**
 * @phpstan-type SetlistRow array{id: string, type: string, title: string, artist: string, tape: bool}
 * @phpstan-type TourChoice array{name: string, catalogId: string|null}
 * @phpstan-type ConcertCatalog array{artistIds: list<string|null>, tours: list<TourChoice>}
 * @phpstan-type Concert array{id: string, title: string, artists: list<string>, venueId: null, date: array{year: int, month: null, day: null}, time: string, timezone: null, interested: bool, ticketsBought: bool, notes: string, catalog: ConcertCatalog, setlist: list<SetlistRow>}
 * @phpstan-type PendingArtist array{name: string, type: string, disambiguation: null, selection: string|null}
 * @phpstan-type PendingSong array{title: string, disambiguation: null, selection: string|null}
 * @phpstan-type PendingTour array{name: string, selection: string|null}
 * @phpstan-type PendingCatalog array{artists: array<string, PendingArtist>, songs: array<string, PendingSong>, lineup: array<array-key, string>, entries: array<string, array{song: string, artist?: string}>, tours?: array<string, PendingTour>, tourChoices?: array<array-key, string>}
 * @phpstan-type TourCatalog array{artists: array<string, PendingArtist>, songs: array<string, PendingSong>, lineup: array<array-key, string>, entries: array<string, array{song: string, artist?: string}>, tours: array<string, PendingTour>, tourChoices: array<array-key, string>}
 * @phpstan-type DraftBody array{concert: Concert}
 * @phpstan-type PendingBody array{concert: Concert, pendingCatalog: PendingCatalog}
 */
class MusicBrainzSaveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return DraftBody
     */
    private function draft(): array
    {
        return ['concert' => [
            'id' => (string) Str::uuid(), 'title' => 'Noche Ñ', 'artists' => ['Björk, guest', 'Björk, guest'],
            'venueId' => null, 'date' => ['year' => 2024, 'month' => null, 'day' => null], 'time' => '00:00:07', 'timezone' => null,
            'interested' => false, 'ticketsBought' => true, 'notes' => 'My notes',
            'catalog' => ['artistIds' => [null, null], 'tours' => []],
            'setlist' => [
                ['id' => (string) Str::uuid(), 'type' => 'song', 'title' => 'Canción', 'artist' => 'Mi artista', 'tape' => false],
                ['id' => (string) Str::uuid(), 'type' => 'note', 'title' => 'Encore', 'artist' => '', 'tape' => false],
            ],
        ]];
    }

    /**
     * @return PendingBody
     */
    private function pending(): array
    {
        $body = $this->draft();
        $body['concert']['artists'] = ['Soda Stereo'];
        $body['concert']['catalog']['artistIds'] = [null];
        $body['concert']['setlist'] = [$body['concert']['setlist'][0]];
        $body['concert']['setlist'][0]['artist'] = 'Soda Stereo';
        $artist = (string) Str::uuid();
        $song = (string) Str::uuid();
        $body['pendingCatalog'] = ['artists' => [$artist => ['name' => 'Soda Stereo', 'type' => 'group', 'disambiguation' => null, 'selection' => app(MusicBrainzSelection::class)->issue('artist', '3f8a5e5b-c24b-4068-9f1c-afad8829e06b')]], 'songs' => [$song => ['title' => 'Canción', 'disambiguation' => null, 'selection' => app(MusicBrainzSelection::class)->issue('work', '8b21973e-836a-441b-9e4d-4e67e7114d75')]], 'lineup' => ['0' => $artist], 'entries' => [$body['concert']['setlist'][0]['id'] => ['song' => $song, 'artist' => $artist]]];

        return $body;
    }

    public function test_pending_identities_are_atomic_reused_and_reload_without_sidecars(): void
    {
        $this->actingAs(User::factory()->create());
        $saved = $this->postJson('/api/v1/attendances', $this->pending())->assertOk()->json('data.concerts.0');
        expect(data_get($saved, 'setlist.0.artistCatalogId'))->toBe($this->stringAt($saved, 'catalog.artistIds.0'))
            ->and(data_get($saved, 'setlist.0.songCatalogId'))->not->toBeEmpty();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/attendances', $this->pending())->assertOk();
        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseCount('songs', 1);
        $this->assertDatabaseCount('attendances', 2);
        ProviderHttp::assertNothingSent();
    }

    public function test_invalid_references_and_proofs_leave_no_domain_rows(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['missing', 'expired', 'position', 'unused'] as $case) {
            $body = $this->pending();
            $key = array_key_first($body['pendingCatalog']['artists']);
            if ($case === 'missing') {
                $body['pendingCatalog']['lineup'][0] = (string) Str::uuid();
            }
            if ($case === 'expired') {
                $body['pendingCatalog']['artists'][$key]['selection'] = 'forged';
            }
            if ($case === 'position') {
                $body['pendingCatalog']['lineup'] = ['01' => $key];
            }
            if ($case === 'unused') {
                $body['pendingCatalog']['entries'] = [];
            }
            $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
            foreach (['artists', 'songs', 'events', 'attendances'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
        }
    }

    public function test_manual_song_creation_differs_from_unresolved_rows_and_empty_sidecars_are_compatible(): void
    {
        $this->actingAs(User::factory()->create());
        $body = $this->pending();
        foreach (['artists', 'songs'] as $map) {
            foreach ($body['pendingCatalog'][$map] as &$input) {
                $input['selection'] = null;
            } unset($input);
        }
        $this->postJson('/api/v1/attendances', $body)->assertOk();
        $this->assertDatabaseCount('songs', 1);
        $this->assertDatabaseCount('song_provider_ids', 0);
        $legacy = $this->draft();
        $legacy['pendingCatalog'] = ['artists' => [], 'songs' => [], 'lineup' => [], 'entries' => []];
        $this->postJson('/api/v1/attendances', $legacy)->assertOk();
        $this->assertDatabaseCount('songs', 1);
        $invalid = $this->draft();
        $invalid['pendingCatalog'] = null;
        $this->postJson('/api/v1/attendances', $invalid)->assertUnprocessable();
        ProviderHttp::assertNothingSent();
    }

    public function test_repeated_songs_remain_separate_rows_and_existing_provider_facts_are_read_only(): void
    {
        $artist = Artist::factory()->create(['name' => 'Saved name', 'disambiguation' => 'Keep']);
        ArtistProviderId::factory()->create(['artist_id' => $artist->id, 'provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => '3f8a5e5b-c24b-4068-9f1c-afad8829e06b']);
        $this->actingAs(User::factory()->create());
        $body = $this->pending();
        $row = $body['concert']['setlist'][0];
        $row['id'] = (string) Str::uuid();
        $body['concert']['setlist'][] = $row;
        $body['pendingCatalog']['entries'][$row['id']] = $body['pendingCatalog']['entries'][$body['concert']['setlist'][0]['id']];
        $saved = $this->postJson('/api/v1/attendances', $body)->assertOk()->json('data.concerts.0');
        expect($this->arrayAt($saved, 'setlist'))->toHaveCount(2)
            ->and(data_get($saved, 'setlist.1.songCatalogId'))->toBe($this->stringAt($saved, 'setlist.0.songCatalogId'))
            ->and($this->present($artist->fresh())->name)->toBe('Saved name')
            ->and($this->present($artist->fresh())->disambiguation)->toBe('Keep');
        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseCount('songs', 1);
    }

    public function test_late_resolved_duplicate_lineup_rolls_back_every_new_record(): void
    {
        $this->actingAs(User::factory()->create());
        $body = $this->pending();
        $key = array_key_first($body['pendingCatalog']['artists']);
        $body['concert']['artists'][] = 'Soda Stereo';
        $body['concert']['catalog']['artistIds'][] = null;
        $body['pendingCatalog']['lineup'][1] = $key;
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
        foreach (['artists', 'songs', 'artist_provider_ids', 'song_provider_ids', 'events', 'attendances'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_existing_event_accepts_pending_setlist_song_but_rejects_pending_lineup(): void
    {
        $this->actingAs(User::factory()->create());
        $original = $this->postJson('/api/v1/attendances', $this->draft())->assertOk()->json('data.concerts.0');
        $body = $this->pending();
        $body['concert']['catalog']['eventId'] = $this->stringAt($original, 'catalog.eventId');
        $body['concert']['artists'] = $this->arrayAt($original, 'artists');
        $body['concert']['catalog']['artistIds'] = $this->arrayAt($original, 'catalog.artistIds');
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
        $body['pendingCatalog']['lineup'] = [];
        $body['pendingCatalog']['artists'] = [];
        $entryId = $body['concert']['setlist'][0]['id'];
        unset($body['pendingCatalog']['entries'][$entryId]['artist']);
        $body['concert']['setlist'][0]['artist'] = '';
        $this->actingAs(User::factory()->create());
        $saved = $this->postJson('/api/v1/attendances', $body)->assertOk()->json('data.concerts.0');
        expect(data_get($saved, 'artists'))->toBe($this->arrayAt($original, 'artists'))
            ->and(data_get($saved, 'catalog.eventId'))->toBe($this->stringAt($original, 'catalog.eventId'))
            ->and(data_get($saved, 'setlist.0.songCatalogId'))->not->toBeEmpty();
        $this->assertDatabaseCount('events', 1);
    }

    /**
     * @param  array{concert: Concert, pendingCatalog?: PendingCatalog}|null  $body
     * @return array{concert: Concert, pendingCatalog: TourCatalog}
     */
    private function withTour(?array $body = null): array
    {
        $body ??= $this->draft();
        $key = (string) Str::uuid();
        $body['concert']['catalog']['tours'] = [['name' => 'The Eras Tour', 'catalogId' => null]];
        $body['pendingCatalog'] ??= ['artists' => [], 'songs' => [], 'lineup' => [], 'entries' => []];
        $body['pendingCatalog']['tours'] = [$key => ['name' => 'The Eras Tour',
            'selection' => app(MusicBrainzSelection::class)
                ->issue('series', '23aa9d94-ffc6-4160-a7f3-31cfba1ea646')]];
        $body['pendingCatalog']['tourChoices'] = ['0' => $key];

        return $body;
    }

    public function test_selected_tour_can_be_added_when_updating_an_existing_concert(): void
    {
        $this->actingAs(User::factory()->create());
        $draft = $this->draft();
        $saved = $this->postJson('/api/v1/attendances', $draft)->assertOk()->json('data.concerts.0');
        $this->assertDatabaseCount('tours', 0);
        $body = $this->withTour($draft);
        $body['concert']['id'] = $this->stringAt($saved, 'id');
        $updated = $this->patchJson('/api/v1/attendances/'.$this->stringAt($saved, 'id'), $body)
            ->assertOk()->json('data.concerts.0');
        $tour = Tour::query()->where('external_id', $this->stringAt($updated, 'catalog.tours.0.catalogId'))->firstOrFail();
        expect($tour->name)->toBe('The Eras Tour');
        $this->assertDatabaseHas('tour_provider_ids', ['provider' => 'musicbrainz',
            'resource_type' => 'series', 'external_provider_id' => '23aa9d94-ffc6-4160-a7f3-31cfba1ea646']);
        ProviderHttp::assertNothingSent();
    }

    public function test_selected_tour_reuses_one_provider_identity_across_accounts_and_preserves_saved_facts(): void
    {
        $this->actingAs(User::factory()->create());
        $saved = $this->postJson('/api/v1/attendances', $this->withTour())
            ->assertOk()->json('data.concerts.0');
        $tour = Tour::query()->where('external_id', $this->stringAt($saved, 'catalog.tours.0.catalogId'))->firstOrFail();
        expect($tour->external_id)->not->toBe('23aa9d94-ffc6-4160-a7f3-31cfba1ea646');
        $tour->update(['name' => 'Locally reviewed name']);
        $second = $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/attendances', $this->withTour())->assertOk()->json('data.concerts.0');
        expect(data_get($second, 'catalog.tours.0.catalogId'))->toBe($tour->external_id)
            ->and(data_get($second, 'catalog.tours.0.name'))->toBe('Locally reviewed name');
        $this->assertDatabaseCount('tours', 1);
        $this->assertDatabaseCount('tour_provider_ids', 1);
        $this->assertDatabaseHas('tour_provider_ids', ['provider' => 'musicbrainz',
            'resource_type' => 'series', 'external_provider_id' => '23aa9d94-ffc6-4160-a7f3-31cfba1ea646']);
        ProviderHttp::assertNothingSent();
    }

    public function test_old_sidecars_and_plain_manual_tours_still_save_without_new_maps(): void
    {
        $this->actingAs(User::factory()->create());
        $old = $this->pending();
        $old['concert']['catalog']['tours'] = [['name' => 'Manual Tour', 'catalogId' => null]];
        $this->postJson('/api/v1/attendances', $old)->assertOk();
        $empty = $this->draft();
        $empty['pendingCatalog'] = ['artists' => [], 'songs' => [], 'lineup' => [], 'entries' => [],
            'tours' => [], 'tourChoices' => []];
        $this->postJson('/api/v1/attendances', $empty)->assertOk();
        $this->assertDatabaseCount('tours', 1);
        $this->assertDatabaseCount('tour_provider_ids', 0);
        ProviderHttp::assertNothingSent();
    }

    public function test_invalid_tour_maps_or_proofs_create_no_domain_records(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['half', 'unused', 'position', 'name', 'forged', 'wrong-kind', 'expired'] as $case) {
            $body = $this->withTour();
            $key = array_key_first($body['pendingCatalog']['tours']);
            if ($case === 'half') {
                unset($body['pendingCatalog']['tourChoices']);
            }
            if ($case === 'unused') {
                $body['pendingCatalog']['tourChoices'] = [];
            }
            if ($case === 'position') {
                $body['pendingCatalog']['tourChoices'] = ['01' => $key];
            }
            if ($case === 'name') {
                $body['concert']['catalog']['tours'][0]['name'] = 'Different';
            }
            if ($case === 'forged') {
                $body['pendingCatalog']['tours'][$key]['selection'] = 'forged';
            }
            if ($case === 'wrong-kind') {
                $body['pendingCatalog']['tours'][$key]['selection'] = app(MusicBrainzSelection::class)
                    ->issue('artist', '3f8a5e5b-c24b-4068-9f1c-afad8829e06b');
            }
            if ($case === 'expired') {
                $this->travel(25)->hours();
            }
            $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
            if ($case === 'expired') {
                $this->travelBack();
            }
            foreach (['artists', 'songs', 'tours', 'tour_provider_ids', 'events', 'attendances'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
        }
    }

    public function test_pending_tour_rejects_event_and_cross_namespace_keys_and_rolls_back_late_failures(): void
    {
        $this->actingAs(User::factory()->create());
        $event = Event::factory()->create();
        $body = $this->withTour();
        $body['concert']['catalog']['eventId'] = $event->external_id;
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
        $body = $this->withTour($this->pending());
        $tourKey = array_key_first($body['pendingCatalog']['tours']);
        $artistKey = array_key_first($body['pendingCatalog']['artists']);
        $body['pendingCatalog']['tours'][$artistKey] = $body['pendingCatalog']['tours'][$tourKey];
        unset($body['pendingCatalog']['tours'][$tourKey]);
        $body['pendingCatalog']['tourChoices'][0] = $artistKey;
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
        $body = $this->withTour($this->pending());
        $key = array_key_first($body['pendingCatalog']['artists']);
        $body['concert']['artists'][] = 'Soda Stereo';
        $body['concert']['catalog']['artistIds'][] = null;
        $body['pendingCatalog']['lineup'][1] = $key;
        $this->postJson('/api/v1/attendances', $body)->assertUnprocessable();
        foreach (['artists', 'songs', 'tours', 'tour_provider_ids', 'attendances'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        ProviderHttp::assertNothingSent();
    }

    public function test_distinct_same_name_series_and_null_selection_tour_are_preserved(): void
    {
        $this->actingAs(User::factory()->create());
        $body = $this->withTour();
        $second = (string) Str::uuid();
        $body['pendingCatalog']['tours'][$second] = ['name' => 'The Eras Tour', 'selection' => app(MusicBrainzSelection::class)->issue('series', (string) Str::uuid())];
        $body['pendingCatalog']['tourChoices'][1] = $second;
        $body['concert']['catalog']['tours'][] = ['name' => 'The Eras Tour', 'catalogId' => null];
        $saved = $this->postJson('/api/v1/attendances', $body)->assertOk()->json('data.concerts.0');
        expect($this->stringAt($saved, 'catalog.tours.1.catalogId'))->not->toBe($this->stringAt($saved, 'catalog.tours.0.catalogId'));
        $this->assertDatabaseCount('tour_provider_ids', 2);
        $manual = $this->withTour();
        $key = array_key_first($manual['pendingCatalog']['tours']);
        $manual['pendingCatalog']['tours'][$key]['selection'] = null;
        $this->postJson('/api/v1/attendances', $manual)->assertOk();
        $this->assertDatabaseCount('tours', 3);
        $this->assertDatabaseCount('tour_provider_ids', 2);
        ProviderHttp::assertNothingSent();
    }
}
