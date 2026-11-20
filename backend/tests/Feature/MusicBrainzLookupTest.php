<?php

namespace Tests\Feature;

use App\Http\MusicBrainzSelection;
use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Tour;
use App\Models\TourProviderId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RecordedRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\PendingRequest;
use Tests\Support\ProviderHttp;
use Tests\TestCase;

class MusicBrainzLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.musicbrainz.enabled' => true, 'services.musicbrainz.user_agent' => 'SpectaclesTests/1.0 (offline tests)', 'cache.default' => 'array']);
        Cache::flush();
    }

    public function test_authenticated_normalized_cached_reads_and_shared_gate(): void
    {
        $this->withHeader('Accept', 'application/json')->get('/api/v1/musicbrainz/artists?q=Soda')->assertUnauthorized();
        ProviderHttp::fake(['musicbrainz.org/*' => ProviderHttp::response(['artists' => [['id' => '3f8a5e5b-c24b-4068-9f1c-afad8829e06b', 'name' => 'Soda Stereo', 'type' => 'Group'], ['id' => 'bad', 'name' => 'Invalid']], 'works' => []])]);
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        $this->get('/api/v1/musicbrainz/artists?q=Soda')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.artistType', 'group')->assertJsonPath('data.0.local', null);
        $this->get('/api/v1/musicbrainz/artists?q=Soda')->assertOk();
        $this->get('/api/v1/musicbrainz/works?q=Eres')->assertStatus(429)->assertHeader('Retry-After');
        ProviderHttp::assertSentCount(1);
        $this->assertDatabaseCount('artists', 0);
    }

    public function test_invalid_inputs_and_missing_configuration_make_no_requests(): void
    {
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        foreach (['q=x', 'q=Soda&url=bad', 'q='.str_repeat('x', 201)] as $query) {
            $this->get('/api/v1/musicbrainz/artists?'.$query)->assertUnprocessable();
        }
        config(['services.musicbrainz.enabled' => false]);
        $this->get('/api/v1/musicbrainz/artists?q=Soda')->assertStatus(503);
        ProviderHttp::assertNothingSent();
    }

    public function test_work_writers_do_not_include_performers_and_query_is_literal(): void
    {
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        ProviderHttp::fake(['musicbrainz.org/*' => ProviderHttp::response(['works' => [['id' => '8b21973e-836a-441b-9e4d-4e67e7114d75', 'title' => 'Eres', 'relations' => [['type' => 'composer', 'artist' => ['name' => 'Writer']], ['type' => 'performer', 'artist' => ['name' => 'Not a writer']]]]]])]);
        $this->get('/api/v1/musicbrainz/works?q=AC%2FDC')->assertOk()->assertJsonPath('data.0.writers', ['Writer']);
        ProviderHttp::assertSent(fn (RecordedRequest $request): bool => $request['query'] === 'work:(AC\\/DC*)');
    }

    public function test_cached_hits_attach_current_local_matches_and_refresh_proofs(): void
    {
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        $mbid = '3f8a5e5b-c24b-4068-9f1c-afad8829e06b';
        ProviderHttp::fake(['musicbrainz.org/*' => ProviderHttp::response(['artists' => [['id' => $mbid, 'name' => 'Provider label']]])]);
        $first = $this->get('/api/v1/musicbrainz/artists?q=Soda')->assertOk()->json('data.0');
        $artist = Artist::factory()->create(['name' => 'Saved local label']);
        ArtistProviderId::factory()->create(['artist_id' => $artist->id, 'provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => $mbid]);
        $second = $this->get('/api/v1/musicbrainz/artists?q=Soda')->assertOk()->json('data.0');
        expect(data_get($second, 'local.id'))->toBe($artist->external_id)
            ->and(data_get($second, 'local.label'))->toBe('Saved local label')
            ->and($this->stringAt($second, 'selection'))->not->toBe($this->stringAt($first, 'selection'));
        ProviderHttp::assertSentCount(1);
    }

    public function test_connection_failures_are_reported_as_unavailable(): void
    {
        ProviderHttp::fake(['musicbrainz.org/*' => ProviderHttp::response()->throw(fn (PendingRequest $pending): FatalRequestException => new FatalRequestException(new \RuntimeException('Connection refused'), $pending))]);

        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');

        $this->get('/api/v1/musicbrainz/artists?q=Soda')->assertServiceUnavailable();
        ProviderHttp::assertSentCount(1);
    }

    public function test_upstream_failures_are_safe_not_cached_and_retry_after_is_bounded(): void
    {
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        ProviderHttp::fakeSequence()->push('private debug', 503)->push(['wrong' => []], 200)->push('private debug', 429, ['Retry-After' => '99999']);
        foreach ([503, 200, 429] as $status) {
            Cache::flush();
            $result = $this->get('/api/v1/musicbrainz/artists?q=Fail')->assertStatus($status === 429 ? 429 : 503);
            $content = $result->getContent();
            expect($content)->toBeString()->not->toContain('private debug');
            if ($status === 429) {
                $result->assertHeader('Retry-After', '60');
            }
            expect(Cache::get('musicbrainz:v1:artist:'.hash('sha256', 'Fail')))->toBeNull();
        }
    }

    public function test_empty_success_is_cached_and_a_held_global_lock_does_not_sleep(): void
    {
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        ProviderHttp::fake(['musicbrainz.org/*' => ProviderHttp::response(['artists' => []])]);
        $this->get('/api/v1/musicbrainz/artists?q=Empty')->assertOk()->assertJsonPath('data', []);
        $this->get('/api/v1/musicbrainz/artists?q=Empty')->assertOk();
        ProviderHttp::assertSentCount(1);
        Cache::flush();
        $lock = Cache::lock('musicbrainz:v1:outbound', 10);
        $lock->get();
        try {
            $this->get('/api/v1/musicbrainz/artists?q=Other')->assertStatus(429);
        } finally {
            $lock->release();
        }
        ProviderHttp::assertSentCount(1);
    }

    public function test_nonempty_get_bodies_are_rejected_and_proofs_expire_or_reject_wrong_kinds(): void
    {
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        $this->json('GET', '/api/v1/musicbrainz/artists?q=Soda', ['extra' => true])->assertUnprocessable();
        ProviderHttp::assertNothingSent();
        $proofs = app(MusicBrainzSelection::class);
        $proof = $proofs->issue('artist', '3f8a5e5b-c24b-4068-9f1c-afad8829e06b');
        foreach ([['forged', 'artist'], [$proof, 'work']] as [$value, $kind]) {
            try {
                $proofs->read($value, $kind, 'field');
                $this->fail('Invalid proof accepted');
            } catch (ValidationException $error) {
                expect($error->errors())->toHaveKey('field');
            }
        }
        $this->travel(25)->hours();
        expect(fn () => $proofs->read($proof, 'artist', 'field'))->toThrow(ValidationException::class);
    }

    public function test_literal_prefix_terms_preserve_unicode_and_escape_query_operators(): void
    {
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        ProviderHttp::fake(['musicbrainz.org/*' => ProviderHttp::response(['artists' => []])]);
        foreach (['AC/DC', 'WOS OR *:*', 'Zoé', '🎵', '"quoted"', 'back\\slash'] as $query) {
            Cache::flush();
            $status = mb_strlen($query) < 2 ? 422 : 200;
            $this->get('/api/v1/musicbrainz/artists?q='.rawurlencode($query))->assertStatus($status);
        }
        ProviderHttp::assertSent(fn (RecordedRequest $request): bool => is_string($query = $request['query']) && str_contains($query, 'WOS* AND OR* AND \\*\\:\\**'));
        ProviderHttp::assertSent(fn (RecordedRequest $request): bool => $request['query'] === 'artist:(Zoé*)');
    }

    /**
     * @param  list<array{id: string, name: string, type: string}>  $series
     * @return array{id: string, relations: list<array{target-type: string, type-id: string, series: array{id: string, name: string, type: string}}>}
     */
    private function tourRelations(string $artist, array $series): array
    {
        return ['id' => $artist, 'relations' => array_map(fn (array $row): array => ['target-type' => 'series', 'type-id' => 'f03d59f3-97db-424b-885e-e8be69d65225', 'series' => $row], $series)];
    }

    public function test_tour_lookup_requires_artist_scope_and_exact_relationship_ownership(): void
    {
        $this->withHeader('Accept', 'application/json')->get('/api/v1/musicbrainz/tours?q=Eras')->assertUnauthorized();
        $artist = '00000000-0000-4000-8000-000000000001';
        $tour = '23aa9d94-ffc6-4160-a7f3-31cfba1ea646';
        $body = $this->tourRelations($artist, [
            ['id' => $tour, 'name' => 'The Eras Tour', 'type' => 'Tour'],
            ['id' => '00000000-0000-4000-8000-000000000002', 'name' => 'Eras Festival', 'type' => 'Festival'],
        ]);
        $body['relations'][] = ['target-type' => 'series', 'type-id' => 'd1a845d1-8c03-3191-9454-e4e8d37fa5e0', 'series' => ['id' => '00000000-0000-4000-8000-000000000003', 'name' => 'Eras unrelated', 'type' => 'Tour']];
        ProviderHttp::fake(['musicbrainz.org/*' => ProviderHttp::response($body)]);
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        $this->get('/api/v1/musicbrainz/tours?q=Eras')->assertOk()->assertJsonPath('meta.scope_available', false)->assertJsonCount(0, 'data');
        ProviderHttp::assertNothingSent();
        $result = $this->get('/api/v1/musicbrainz/tours?'.http_build_query(['q' => 'Eras', 'artist_mbids' => [$artist]]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tour);
        ProviderHttp::assertSent(fn (RecordedRequest $request): bool => str_contains($request->url(), '/ws/2/artist/'.$artist) && $request['inc'] === 'series-rels' && ! isset($request['query']));
        expect(app(MusicBrainzSelection::class)->read($this->stringAt($result->json(), 'data.0.selection'), 'series', 'selection'))->toBe($tour);
        $this->assertDatabaseCount('tours', 0);
        $this->assertDatabaseCount('tour_artists', 0);
    }

    public function test_malformed_ownership_targets_fail_instead_of_becoming_cached_empty_results(): void
    {
        $a = '00000000-0000-4000-8000-000000000001';
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        $url = '/api/v1/musicbrainz/tours?'.http_build_query(['q' => 'Tour', 'artist_mbids' => [$a]]);
        foreach ([['target-type' => 'artist'], ['series' => ['id' => 'bad', 'name' => 'Tour', 'type' => 'Tour']], ['series' => ['id' => $a, 'name' => str_repeat('x', 256), 'type' => 'Tour']]] as $patch) {
            Cache::flush();
            $body = $this->tourRelations($a, [['id' => $a, 'name' => 'Tour', 'type' => 'Tour']]);
            $body['relations'][0] = array_replace($body['relations'][0], $patch);
            ProviderHttp::fake(['musicbrainz.org/*' => ProviderHttp::response($body)]);
            $this->get($url)->assertStatus(503);
            expect(Cache::get('musicbrainz:v2:artist-tours:'.$a))->toBeNull();
        }
    }

    public function test_scoped_tour_cache_warms_one_artist_per_retry_and_preserves_union_and_identity(): void
    {
        $a = '00000000-0000-4000-8000-000000000001';
        $b = '00000000-0000-4000-8000-000000000002';
        $t1 = '00000000-0000-4000-8000-000000000011';
        $t2 = '00000000-0000-4000-8000-000000000012';
        $localArtist = Artist::factory()->create();
        ArtistProviderId::factory()->create(['artist_id' => $localArtist->id, 'provider' => 'musicbrainz', 'resource_type' => 'artist', 'provider_resource_id' => $a]);
        ProviderHttp::fake([
            'musicbrainz.org/ws/2/artist/'.$a.'*' => ProviderHttp::response($this->tourRelations($a, [['id' => $t1, 'name' => 'Same Tour', 'type' => 'Tour']])),
            'musicbrainz.org/ws/2/artist/'.$b.'*' => ProviderHttp::response($this->tourRelations($b, [['id' => $t1, 'name' => 'Same Tour', 'type' => 'Tour'], ['id' => $t2, 'name' => 'Same Tour', 'type' => 'Tour']])),
        ]);
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        $url = '/api/v1/musicbrainz/tours?'.http_build_query(['q' => 'Tour', 'artist_uuids' => [$localArtist->external_id], 'artist_mbids' => [$b]]);
        $this->get($url)->assertStatus(429)->assertHeader('Retry-After');
        ProviderHttp::assertSentCount(1);
        $this->get($url)->assertStatus(429);
        ProviderHttp::assertSentCount(1);
        $this->travel(2)->seconds();
        $this->get($url)->assertOk()->assertJsonCount(2, 'data');
        ProviderHttp::assertSentCount(2);
        $this->get('/api/v1/musicbrainz/tours?'.http_build_query(['q' => 'Same', 'artist_mbids' => [$a]]))->assertOk()->assertJsonCount(1, 'data');
        $tour = Tour::factory()->create(['name' => 'Saved spelling']);
        TourProviderId::factory()->create(['tour_id' => $tour->id, 'provider' => 'musicbrainz', 'resource_type' => 'series', 'external_provider_id' => $t1]);
        $this->get($url)->assertOk()->assertJsonPath('data.0.local.id', $tour->external_id)->assertJsonPath('data.0.local.label', 'Saved spelling');
        ProviderHttp::assertSentCount(2);
        $this->assertDatabaseCount('tour_artists', 0);
    }

    public function test_scoped_tour_failures_and_invalid_scope_are_not_definitive_empty_or_cached(): void
    {
        $a = '00000000-0000-4000-8000-000000000001';
        $this->actingAs(User::factory()->create())->withHeader('Accept', 'application/json');
        $url = '/api/v1/musicbrainz/tours?'.http_build_query(['q' => 'Tour', 'artist_mbids' => [$a]]);
        ProviderHttp::fakeSequence()->push(['id' => $a])->push(['id' => '00000000-0000-4000-8000-000000000002', 'relations' => []])->push('private debug', 503)->push('private debug', 429, ['Retry-After' => '99999'])->push(['id' => $a, 'relations' => []]);
        foreach ([503, 503, 503, 429, 200] as $status) {
            Cache::flush();
            $response = $this->get($url)->assertStatus($status);
            $content = $response->getContent();
            expect($content)->toBeString()->not->toContain('private debug');
            if ($status !== 200) {
                expect(Cache::get('musicbrainz:v2:artist-tours:'.$a))->toBeNull();
            }
        }
        $this->get($url)->assertOk()->assertJsonCount(0, 'data');
        ProviderHttp::assertSentCount(5);
        $this->get('/api/v1/musicbrainz/artists?q=Taylor')->assertStatus(429);
        foreach ([['artist_mbids' => ['bad']], ['artist_mbids' => array_fill(0, 101, $a)], ['artist_uuids' => array_fill(0, 60, $a), 'artist_mbids' => array_fill(0, 41, $a)]] as $scope) {
            $this->get('/api/v1/musicbrainz/tours?'.http_build_query(['q' => 'Tour', ...$scope]))->assertUnprocessable();
        }
        $manual = Artist::factory()->create();
        $this->get('/api/v1/musicbrainz/tours?'.http_build_query(['q' => 'Tour', 'artist_uuids' => [$manual->external_id]]))->assertOk()->assertJsonPath('meta.scope_available', false);
        config(['services.musicbrainz.enabled' => false]);
        $this->get($url)->assertStatus(503);
        ProviderHttp::assertSentCount(5);
    }
}
