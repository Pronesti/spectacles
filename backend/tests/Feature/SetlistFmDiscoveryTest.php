<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\ProviderHttp;
use Tests\TestCase;

class SetlistFmDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.setlistfm.enabled' => true, 'services.setlistfm.api_key' => 'test-server-secret', 'services.setlistfm.api_interval_ms' => 0, 'services.setlistfm.api_daily_limit' => 100, 'services.musicbrainz.enabled' => true, 'services.musicbrainz.user_agent' => 'SpectaclesOfflineTests/1.0']);
        $this->actingAs(User::factory()->create());
        $this->withHeaders(['Accept' => 'application/json']);
    }

    private function mbid(): string
    {
        return '55441123-89a2-49ab-ae3f-df7a3e86835a';
    }

    public function test_disabled_source_never_calls_a_provider(): void
    {
        config(['services.setlistfm.enabled' => false]);
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid())->assertStatus(503);
        ProviderHttp::assertNothingSent();
    }

    public function test_without_an_api_key_every_setlist_read_is_unavailable_without_requests(): void
    {
        config(['services.setlistfm.api_key' => '']);
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid())->assertStatus(503);
        $this->get('/api/v1/providers/setlistfm/performances?'.http_build_query(['period' => 'past', 'artistMbid' => $this->mbid()]))->assertStatus(503);
        $this->get('/api/v1/providers/setlistfm/venues/73d6ca11')->assertStatus(503);
        ProviderHttp::assertNothingSent();
    }

    public function test_venue_url_requires_fixed_origin_exact_id_and_no_navigation_query(): void
    {
        foreach (['https://evil.example/venue/arena-73d6ca11.html', 'https://www.setlist.fm/venue/arena-other.html', 'https://www.setlist.fm/venue/arena-73d6ca11.html?page=2'] as $url) {
            $this->get('/api/v1/providers/setlistfm/venues/73d6ca11?'.http_build_query(['venueUrl' => $url]))->assertUnprocessable();
        }
        ProviderHttp::assertNothingSent();
    }

    public function test_website_tour_ids_are_rejected_instead_of_ignored(): void
    {
        $this->get('/api/v1/providers/setlistfm/performances?'.http_build_query(['period' => 'past', 'venueId' => '73d6ca11', 'venueUrl' => 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html', 'tourId' => '2bde703a', 'tourName' => 'Drama y luz']))->assertUnprocessable();
        ProviderHttp::assertNothingSent();
    }

    public function test_inbound_auth_and_query_allowlists(): void
    {
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid().'&url=https://evil.example')->assertUnprocessable();
        $this->get('/api/v1/providers/setlistfm/tours')->assertUnprocessable();
        $this->get('/api/v1/providers/setlistfm/performances?period=past')->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->get('/api/v1/providers/setlistfm/tours?artistMbid='.$this->mbid())->assertUnauthorized();
        ProviderHttp::assertNothingSent();
    }
}
