<?php

namespace Tests\Feature;

use App\Http\CatalogProviderUnavailable;
use App\Http\Data\SetlistFmSearchQueryData;
use App\Http\Integrations\SetlistFm\Data\SetlistFmSearchPayloadData;
use App\Http\Integrations\SetlistFm\Data\SetlistFmSetlistPayloadData;
use App\Http\Integrations\SetlistFm\Data\SetlistFmVenuePayloadData;
use App\Http\SetlistFmApi;
use App\Http\SetlistFmClient;
use Carbon\Carbon;
use Illuminate\Http\Client\Request as RecordedRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Saloon\Http\Faking\MockResponse;
use Tests\Support\ProviderHttp;
use Tests\TestCase;

class SetlistFmApiTest extends TestCase
{
    public const string ARTIST_MBID = 'b10bbbfc-cf9e-42e0-be17-e2c3e1d2600d';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Sleep::fake(syncWithCarbon: true);
        config(['services.setlistfm.enabled' => true, 'services.setlistfm.api_key' => 'test-server-secret', 'services.setlistfm.api_interval_ms' => 0, 'services.setlistfm.api_daily_limit' => 100, 'services.setlistfm.cache_seconds' => 0]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<array-key, mixed>
     */
    public static function performance(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => '63de4613',
            'url' => 'https://www.setlist.fm/setlist/the-beatles/1964/hollywood-bowl-hollywood-ca-63de4613.html',
            'eventDate' => '23-08-1964',
            'artist' => ['mbid' => self::ARTIST_MBID, 'name' => 'The Beatles', 'url' => 'https://www.setlist.fm/setlists/the-beatles-23d6a88b.html'],
            'venue' => ['id' => '6bd6ca6e', 'name' => 'Hollywood Bowl', 'url' => 'https://www.setlist.fm/venue/hollywood-bowl-hollywood-ca-usa-6bd6ca6e.html', 'city' => ['name' => 'Hollywood', 'country' => ['code' => 'US'], 'coords' => ['lat' => 34.1, 'long' => -118.3]]],
            'tour' => ['name' => 'North American Tour 1964'],
            'sets' => ['set' => [['song' => [['name' => 'Yesterday']]]]],
        ], $overrides);
    }

    public function test_api_sends_key_only_in_headers_and_normalizes_actual_identities(): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/rest/1.0/search/setlists*' => ProviderHttp::response(['setlist' => [self::performance()], 'total' => 1, 'page' => 1, 'itemsPerPage' => 20])]);
        $result = app(SetlistFmApi::class)->search(new SetlistFmSearchQueryData(p: 1, artistMbid: self::ARTIST_MBID));
        expect($result->items[0]->ref->externalId)->toBe('63de4613')
            ->and($result->items[0]->tourName)->toBe('North American Tour 1964')
            ->and($result->items[0]->tour)->toBeNull()
            ->and($result->items[0]->venue->values->lat)->toBeNull()
            ->and($result->items[0]->toArray())->not->toHaveKey('sets');
        ProviderHttp::assertSent(fn (RecordedRequest $r): bool => $r->hasHeader('x-api-key', 'test-server-secret') && $r->hasHeader('Accept', 'application/json') && ! str_contains($r->url(), 'test-server-secret'));
        ProviderHttp::assertSentCount(1);
    }

    public function test_empty_search_404_is_a_legitimate_empty_result(): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response('', 404)]);
        expect(app(SetlistFmApi::class)->search(new SetlistFmSearchQueryData(p: 1))->toArray())->toBe(['items' => [], 'more' => false]);
    }

    public function test_rate_limit_remains_actionable_without_retry_or_website_fallback(): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response('', 429, ['Retry-After' => '7'])]);
        try {
            app(SetlistFmApi::class)->detail('63de4613');
            $this->fail('Expected rate limit');
        } catch (CatalogProviderUnavailable $failure) {
            expect($failure->status)->toBe(429)
                ->and($failure->retryAfter)->toBe(7);
        }
        ProviderHttp::assertSentCount(1);
    }

    public function test_missing_key_never_makes_a_request(): void
    {
        config(['services.setlistfm.api_key' => '']);
        try {
            app(SetlistFmClient::class)->api('search/setlists', SetlistFmSearchPayloadData::class);
            $this->fail('Expected missing key to be unavailable');
        } catch (CatalogProviderUnavailable) {
            ProviderHttp::assertNothingSent();
        }
    }

    public function test_api_error_does_not_expose_the_secret_or_raw_provider_body(): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response('test-server-secret internal error', 403)]);
        try {
            app(SetlistFmApi::class)->detail('63de4613');
            $this->fail('Expected authentication error');
        } catch (CatalogProviderUnavailable $failure) {
            expect($failure->reason)->toBe('setlistfm_api_auth')
                ->and($failure->getMessage())->not->toContain('test-server-secret');
        }
    }

    public function test_mismatched_detail_identity_is_rejected(): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response(self::performance())]);
        expect(fn () => app(SetlistFmApi::class)->detail('different'))->toThrow(CatalogProviderUnavailable::class);
    }

    public function test_another_artist_in_search_is_rejected(): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response(['setlist' => [self::performance()], 'total' => 1, 'page' => 1, 'itemsPerPage' => 20])]);
        expect(fn () => app(SetlistFmApi::class)->search(new SetlistFmSearchQueryData(p: 1, artistMbid: '55441123-89a2-49ab-ae3f-df7a3e86835a')))->toThrow(CatalogProviderUnavailable::class);
    }

    public function test_challenge_html_is_rejected(): void
    {
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response('<html>Challenge</html>')]);
        expect(fn () => app(SetlistFmApi::class)->detail('63de4613'))->toThrow(CatalogProviderUnavailable::class);
    }

    protected function tearDown(): void
    {
        Sleep::fake(false);
        parent::tearDown();
    }

    public function test_every_api_endpoint_obeys_two_requests_per_second_even_with_unsafe_configuration(): void
    {
        $this->freezeTime();
        Sleep::fake(syncWithCarbon: true);
        config(['services.setlistfm.api_interval_ms' => 0, 'services.setlistfm.api_daily_limit' => 999999]);
        $starts = [];
        ProviderHttp::fake(function () use (&$starts): MockResponse {
            $starts[] = now()->getTimestampMs();

            return ProviderHttp::response(['ok' => true]);
        });
        $client = app(SetlistFmClient::class);
        $client->api('search/setlists', SetlistFmSearchPayloadData::class);
        $client->api('setlist/63de4613', SetlistFmSetlistPayloadData::class);
        $client->api('venue/6bd6ca6e', SetlistFmVenuePayloadData::class);
        expect($starts[1] - $starts[0])->toBeGreaterThanOrEqual(500)
            ->and($starts[2] - $starts[1])->toBeGreaterThanOrEqual(500)
            ->and($starts[2] - $starts[0])->toBeGreaterThanOrEqual(1000);
        ProviderHttp::assertSentCount(3);
    }

    public function test_daily_hard_ceiling_allows_1439_but_never_the_1440th_request(): void
    {
        $this->freezeTime();
        config(['services.setlistfm.api_daily_limit' => 999999]);
        $key = 'catalog-provider:quota:setlist_api:'.now('UTC')->format('Y-m-d');
        Cache::put($key, 1438, now('UTC')->addDay()->startOfDay());
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response(['ok' => true])]);
        app(SetlistFmClient::class)->api('search/setlists', SetlistFmSearchPayloadData::class);
        expect(Cache::get($key))->toBe(1439);
        try {
            app(SetlistFmClient::class)->api('venue/6bd6ca6e', SetlistFmVenuePayloadData::class);
            $this->fail('The 1440th daily request must not leave the app.');
        } catch (CatalogProviderUnavailable $failure) {
            expect($failure->status)->toBe(429)
                ->and($failure->retryAfter)->toBeGreaterThan(1);
        }
        ProviderHttp::assertSentCount(1);
    }

    public function test_failed_http_requests_consume_the_shared_daily_budget(): void
    {
        config(['services.setlistfm.api_daily_limit' => 1]);
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response('', 500)]);
        try {
            app(SetlistFmClient::class)->api('setlist/63de4613', SetlistFmSetlistPayloadData::class);
            $this->fail('Expected provider failure.');
        } catch (CatalogProviderUnavailable $failure) {
            expect($failure->status)->toBe(503);
        }
        try {
            app(SetlistFmClient::class)->api('search/setlists', SetlistFmSearchPayloadData::class);
            $this->fail('A failed request must still consume quota.');
        } catch (CatalogProviderUnavailable $failure) {
            expect($failure->status)->toBe(429);
        }
        ProviderHttp::assertSentCount(1);
    }

    public function test_api_cache_hits_do_not_double_count_or_spend_requests(): void
    {
        config(['services.setlistfm.api_daily_limit' => 1, 'services.setlistfm.cache_seconds' => 900]);
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response(['setlist' => [self::performance()], 'total' => 1, 'page' => 1, 'itemsPerPage' => 20])]);
        $api = app(SetlistFmApi::class);
        $first = $api->search(new SetlistFmSearchQueryData(p: 1));
        expect($api->search(new SetlistFmSearchQueryData(p: 1))->toArray())->toBe($first->toArray())
            ->and(Cache::get('catalog-provider:quota:setlist_api:'.now('UTC')->format('Y-m-d')))->toBe(1);
        ProviderHttp::assertSentCount(1);
    }

    public function test_shared_api_lock_rejects_a_competing_worker_before_http(): void
    {
        $lock = Cache::lock('catalog-provider:gate:setlist_api', 30);
        expect($lock->get())->toBeTrue();
        try {
            app(SetlistFmClient::class)->api('search/setlists', SetlistFmSearchPayloadData::class);
            $this->fail('A competing worker must not make a request.');
        } catch (CatalogProviderUnavailable $failure) {
            expect($failure->status)->toBe(429);
        } finally {
            $lock->release();
        }
        ProviderHttp::assertNothingSent();
    }

    public function test_api_daily_budget_resets_at_utc_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 23:59:59 UTC'));
        config(['services.setlistfm.api_daily_limit' => 999999]);
        Cache::put('catalog-provider:quota:setlist_api:2026-11-02', 1439, now('UTC')->addDay()->startOfDay());
        ProviderHttp::fake(['https://api.setlist.fm/*' => ProviderHttp::response(['ok' => true])]);
        try {
            app(SetlistFmClient::class)->api('search/setlists', SetlistFmSearchPayloadData::class);
            $this->fail('Exhausted day must be blocked.');
        } catch (CatalogProviderUnavailable $failure) {
            expect($failure->status)->toBe(429)
                ->and($failure->retryAfter)->toBe(1);
        }
        $this->travel(1)->seconds();
        app(SetlistFmClient::class)->api('venue/6bd6ca6e', SetlistFmVenuePayloadData::class);
        expect(Cache::get('catalog-provider:quota:setlist_api:2026-11-03'))->toBe(1);
        ProviderHttp::assertSentCount(1);
    }

    public function test_transport_holds_the_shared_lock_and_spaces_calls_after_a_slow_response(): void
    {
        $this->freezeTime();
        config(['services.setlistfm.api_interval_ms' => -100]);
        $starts = [];
        $overlapped = [];
        ProviderHttp::fake(function () use (&$starts, &$overlapped): MockResponse {
            $starts[] = now()->getTimestampMs();
            $competing = Cache::lock('catalog-provider:gate:setlist_api', 30);
            $acquired = $competing->get();
            $overlapped[] = $acquired;
            if ($acquired) {
                $competing->release();
            }
            $this->travel(3)->seconds();

            return ProviderHttp::response(['ok' => true]);
        });
        app(SetlistFmClient::class)->api('search/setlists', SetlistFmSearchPayloadData::class);
        app(SetlistFmClient::class)->api('venue/6bd6ca6e', SetlistFmVenuePayloadData::class);
        expect($overlapped)->toBe([false, false])
            ->and($starts[1] - $starts[0])->toBeGreaterThanOrEqual(3500);
        $lock = Cache::lock('catalog-provider:gate:setlist_api', 30);
        expect($lock->get())->toBeTrue();
        $lock->release();
    }

    public function test_a_disabled_budget_cannot_be_bypassed_by_direct_client_calls(): void
    {
        config(['services.setlistfm.api_daily_limit' => 0]);
        $this->expectException(CatalogProviderUnavailable::class);
        try {
            app(SetlistFmClient::class)->api('search/setlists', SetlistFmSearchPayloadData::class);
        } finally {
            ProviderHttp::assertNothingSent();
        }
    }

    public function test_cache_reservation_failure_stops_the_http_request(): void
    {
        $lock = Cache::lock('catalog-provider:gate:setlist_api', 30);
        Cache::shouldReceive('lock')->once()->andReturn($lock);
        Cache::shouldReceive('get')->twice()->andReturn(0);
        Cache::shouldReceive('put')->once()->andReturn(false);
        try {
            app(SetlistFmClient::class)->api('search/setlists', SetlistFmSearchPayloadData::class);
            $this->fail('Unrecorded requests must not be sent.');
        } catch (CatalogProviderUnavailable $failure) {
            expect($failure->status)->toBe(503);
        }
        ProviderHttp::assertNothingSent();
    }
}
