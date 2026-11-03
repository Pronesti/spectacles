<?php

namespace Tests\Feature;

use App\Http\CatalogProviderUnavailable;
use App\Http\SetlistFmApi;
use App\Http\SetlistFmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetlistFmApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.setlistfm.enabled' => true, 'services.setlistfm.api_key' => 'test-server-secret', 'services.setlistfm.api_interval_ms' => 0, 'services.setlistfm.api_daily_limit' => 100, 'services.setlistfm.cache_seconds' => 0]);
        Http::preventStrayRequests();
    }

    public static function performance(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => '63de4613',
            'url' => 'https://www.setlist.fm/setlist/the-beatles/1964/hollywood-bowl-hollywood-ca-63de4613.html',
            'eventDate' => '23-08-1964',
            'artist' => ['mbid' => 'b10bbbfc-cf9e-42e0-be17-e2c3e1d2600d', 'name' => 'The Beatles', 'url' => 'https://www.setlist.fm/setlists/the-beatles-23d6a88b.html'],
            'venue' => ['id' => '6bd6ca6e', 'name' => 'Hollywood Bowl', 'url' => 'https://www.setlist.fm/venue/hollywood-bowl-hollywood-ca-usa-6bd6ca6e.html', 'city' => ['name' => 'Hollywood', 'country' => ['code' => 'US'], 'coords' => ['lat' => 34.1, 'long' => -118.3]]],
            'tour' => ['name' => 'North American Tour 1964'],
            'sets' => ['set' => [['song' => [['name' => 'Yesterday']]]]],
        ], $overrides);
    }

    public function test_api_sends_key_only_in_headers_and_normalizes_actual_identities(): void
    {
        Http::fake(['https://api.setlist.fm/rest/1.0/search/setlists*' => Http::response(['setlist' => [self::performance()], 'total' => 1, 'page' => 1, 'itemsPerPage' => 20])]);
        $result = app(SetlistFmApi::class)->search(['artistMbid' => self::performance()['artist']['mbid'], 'p' => 1]);
        $this->assertSame('63de4613', $result['items'][0]['ref']['externalId']);
        $this->assertSame('North American Tour 1964', $result['items'][0]['tourName']);
        $this->assertNull($result['items'][0]['tour']);
        $this->assertNull($result['items'][0]['venue']['values']['lat']);
        $this->assertArrayNotHasKey('sets', $result['items'][0]);
        Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'test-server-secret') && $r->hasHeader('Accept', 'application/json') && ! str_contains($r->url(), 'test-server-secret'));
        Http::assertSentCount(1);
    }

    public function test_empty_search_404_is_a_legitimate_empty_result(): void
    {
        Http::fake(['https://api.setlist.fm/*' => Http::response('', 404)]);
        $this->assertSame(['items' => [], 'more' => false], app(SetlistFmApi::class)->search(['p' => 1]));
    }

    public function test_rate_limit_remains_actionable_without_retry_or_website_fallback(): void
    {
        Http::fake(['https://api.setlist.fm/*' => Http::response('', 429, ['Retry-After' => '7'])]);
        try {
            app(SetlistFmApi::class)->detail('63de4613');
            $this->fail('Expected rate limit');
        } catch (CatalogProviderUnavailable $failure) {
            $this->assertSame(429, $failure->status);
            $this->assertSame(7, $failure->retryAfter);
        }
        Http::assertSentCount(1);
    }

    public function test_missing_key_never_makes_a_request(): void
    {
        config(['services.setlistfm.api_key' => '']);
        try {
            app(SetlistFmClient::class)->api('search/setlists');
            $this->fail('Expected missing key to be unavailable');
        } catch (CatalogProviderUnavailable) {
            Http::assertNothingSent();
        }
    }

    public function test_api_error_does_not_expose_the_secret_or_raw_provider_body(): void
    {
        Http::fake(['https://api.setlist.fm/*' => Http::response('test-server-secret internal error', 403)]);
        try {
            app(SetlistFmApi::class)->detail('63de4613');
            $this->fail('Expected authentication error');
        } catch (CatalogProviderUnavailable $failure) {
            $this->assertSame('setlistfm_api_auth', $failure->reason);
            $this->assertStringNotContainsString('test-server-secret', $failure->getMessage());
        }
    }

    public function test_mismatched_detail_identity_is_rejected(): void
    {
        Http::fake(['https://api.setlist.fm/*' => Http::response(self::performance())]);
        $this->expectException(CatalogProviderUnavailable::class);
        app(SetlistFmApi::class)->detail('different');
    }

    public function test_another_artist_in_search_is_rejected(): void
    {
        Http::fake(['https://api.setlist.fm/*' => Http::response(['setlist' => [self::performance()], 'total' => 1, 'page' => 1, 'itemsPerPage' => 20])]);
        $this->expectException(CatalogProviderUnavailable::class);
        app(SetlistFmApi::class)->search(['artistMbid' => '55441123-89a2-49ab-ae3f-df7a3e86835a']);
    }

    public function test_challenge_html_is_rejected(): void
    {
        Http::fake(['https://api.setlist.fm/*' => Http::response('<html>Challenge</html>')]);
        $this->expectException(CatalogProviderUnavailable::class);
        app(SetlistFmApi::class)->detail('63de4613');
    }
}
