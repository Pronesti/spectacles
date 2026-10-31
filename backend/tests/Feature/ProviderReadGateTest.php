<?php

namespace Tests\Feature;

use App\Http\CatalogProviderUnavailable;
use App\Http\ProviderReadGate;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProviderReadGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.setlistfm.enabled' => true, 'services.setlistfm.html_daily_limit' => 2, 'services.setlistfm.html_interval_ms' => 1000, 'services.setlistfm.cache_seconds' => 10]);
    }

    public function test_cached_public_data_and_independent_channels(): void
    {
        $gate = app(ProviderReadGate::class);
        $calls = 0;
        $fetch = function () use (&$calls): array {
            $calls++;

            return ['public' => true];
        };
        $this->assertSame(['public' => true], $gate->run('setlist_html', 'search', $fetch));
        $this->assertSame(['public' => true], $gate->run('setlist_html', 'search', $fetch));
        config(['services.venue_images.enabled' => true, 'services.venue_images.wikidata_daily_limit' => 2]);
        $gate->run('wikidata', 'search', $fetch);
        $this->assertSame(2, $calls);
    }

    public function test_pacing_and_quota_return_recoverable_429_without_fetch(): void
    {
        $gate = app(ProviderReadGate::class);
        $calls = 0;
        $fetch = function () use (&$calls): array {
            $calls++;

            return [];
        };
        $gate->run('setlist_html', 'one', $fetch);
        try {
            $gate->run('setlist_html', 'two', $fetch);
            $this->fail('Pacing ignored.');
        } catch (CatalogProviderUnavailable $e) {
            $this->assertSame(429, $e->status);
        }
        $this->travel(2)->seconds();
        $gate->run('setlist_html', 'two', $fetch);
        $this->travel(2)->seconds();
        try {
            $gate->run('setlist_html', 'three', $fetch);
            $this->fail('Quota ignored.');
        } catch (CatalogProviderUnavailable $e) {
            $this->assertSame(429, $e->status);
            $this->assertGreaterThan(1, $e->retryAfter);
        }
        $this->assertSame(2, $calls);
    }

    public function test_failures_are_not_cached_and_lock_contention_never_fetches(): void
    {
        $gate = app(ProviderReadGate::class);
        try {
            $gate->run('setlist_html', 'failed', fn () => throw new CatalogProviderUnavailable);
        } catch (CatalogProviderUnavailable) {
            $this->addToAssertionCount(1);
        }
        $this->travel(2)->seconds();
        $this->assertSame(['fresh' => true], $gate->run('setlist_html', 'failed', fn () => ['fresh' => true]));
        $lock = Cache::lock('catalog-provider:gate:setlist_html', 10);
        $this->assertTrue($lock->get());
        try {
            $gate->run('setlist_html', 'blocked', fn () => $this->fail('Contended channel fetched.'));
        } catch (CatalogProviderUnavailable $e) {
            $this->assertSame(429, $e->status);
        } finally {
            $lock->release();
        }
    }

    public function test_chained_html_reads_may_wait_for_short_spacing_without_skipping_quota(): void
    {
        config(['services.setlistfm.html_interval_ms' => 20]);
        $gate = app(ProviderReadGate::class);
        $calls = 0;
        $fetch = function () use (&$calls): array {
            $calls++;

            return ['public' => true];
        };
        $gate->run('setlist_html', 'first', $fetch, true);
        $gate->run('setlist_html', 'second', $fetch, true);
        try {
            $gate->run('setlist_html', 'third', $fetch, true);
            $this->fail('Quota ignored by chained reads.');
        } catch (CatalogProviderUnavailable $failure) {
            $this->assertSame(429, $failure->status);
        }
        $this->assertSame(2, $calls);
    }

    public function test_zero_quota_is_unavailable_and_errors_are_not_cached(): void
    {
        config(['services.setlistfm.html_daily_limit' => 0]);
        $this->expectException(CatalogProviderUnavailable::class);
        app(ProviderReadGate::class)->run('setlist_html', 'none', fn () => $this->fail('Unconfigured provider called.'));
    }
}
