<?php

namespace Tests\Feature;

use App\Http\CatalogProviderUnavailable;
use App\Http\Data\WikidataImageFileData;
use App\Http\ProviderReadGate;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProviderReadGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.venue_images.enabled' => true, 'services.venue_images.wikidata_daily_limit' => 2, 'services.venue_images.wikidata_interval_ms' => 1000, 'services.venue_images.cache_seconds' => 10]);
    }

    public function test_cached_public_data_and_independent_channels(): void
    {
        $gate = app(ProviderReadGate::class);
        $calls = 0;
        $fetch = function () use (&$calls): WikidataImageFileData {
            $calls++;

            return new WikidataImageFileData('Public.jpg');
        };
        $public = $gate->run('wikidata', 'search', WikidataImageFileData::class, $fetch);
        $this->assertInstanceOf(WikidataImageFileData::class, $public);
        $this->assertSame('Public.jpg', $public->file);
        $this->assertEquals(new WikidataImageFileData('Public.jpg'), $gate->run('wikidata', 'search', WikidataImageFileData::class, $fetch));
        config(['services.venue_images.commons_daily_limit' => 2]);
        $gate->run('commons', 'search', WikidataImageFileData::class, $fetch);
        $this->assertSame(2, $calls);
    }

    public function test_pacing_and_quota_return_recoverable_429_without_fetch(): void
    {
        $gate = app(ProviderReadGate::class);
        $calls = 0;
        $fetch = function () use (&$calls): WikidataImageFileData {
            $calls++;

            return new WikidataImageFileData(null);
        };
        $gate->run('wikidata', 'one', WikidataImageFileData::class, $fetch);
        try {
            $gate->run('wikidata', 'two', WikidataImageFileData::class, $fetch);
            $this->fail('Pacing ignored.');
        } catch (CatalogProviderUnavailable $e) {
            $this->assertSame(429, $e->status);
        }
        $this->travel(2)->seconds();
        $gate->run('wikidata', 'two', WikidataImageFileData::class, $fetch);
        $this->travel(2)->seconds();
        try {
            $gate->run('wikidata', 'three', WikidataImageFileData::class, $fetch);
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
            $gate->run('wikidata', 'failed', WikidataImageFileData::class, fn () => throw new CatalogProviderUnavailable);
        } catch (CatalogProviderUnavailable) {
            $this->addToAssertionCount(1);
        }
        $this->travel(2)->seconds();
        $fresh = $gate->run('wikidata', 'failed', WikidataImageFileData::class, fn () => new WikidataImageFileData('Fresh.jpg'));
        $this->assertInstanceOf(WikidataImageFileData::class, $fresh);
        $this->assertSame('Fresh.jpg', $fresh->file);
        $lock = Cache::lock('catalog-provider:gate:wikidata', 10);
        $this->assertTrue($lock->get());
        try {
            $gate->run('wikidata', 'blocked', WikidataImageFileData::class, fn () => $this->fail('Contended channel fetched.'));
        } catch (CatalogProviderUnavailable $e) {
            $this->assertSame(429, $e->status);
        } finally {
            $lock->release();
        }
    }

    public function test_chained_reads_may_wait_for_short_spacing_without_skipping_quota(): void
    {
        config(['services.venue_images.wikidata_interval_ms' => 20]);
        $gate = app(ProviderReadGate::class);
        $calls = 0;
        $fetch = function () use (&$calls): WikidataImageFileData {
            $calls++;

            return new WikidataImageFileData('Public.jpg');
        };
        $gate->run('wikidata', 'first', WikidataImageFileData::class, $fetch, true);
        $gate->run('wikidata', 'second', WikidataImageFileData::class, $fetch, true);
        try {
            $gate->run('wikidata', 'third', WikidataImageFileData::class, $fetch, true);
            $this->fail('Quota ignored by chained reads.');
        } catch (CatalogProviderUnavailable $failure) {
            $this->assertSame(429, $failure->status);
        }
        $this->assertSame(2, $calls);
    }

    public function test_zero_quota_is_unavailable_and_errors_are_not_cached(): void
    {
        config(['services.venue_images.wikidata_daily_limit' => 0]);
        $this->expectException(CatalogProviderUnavailable::class);
        app(ProviderReadGate::class)->run('wikidata', 'none', WikidataImageFileData::class, fn () => $this->fail('Unconfigured provider called.'));
    }
}
