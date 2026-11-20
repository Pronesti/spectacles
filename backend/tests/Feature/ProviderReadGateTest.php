<?php

declare(strict_types=1);

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
        expect($public->file)->toBe('Public.jpg')
            ->and($gate->run('wikidata', 'search', WikidataImageFileData::class, $fetch))->toEqual(new WikidataImageFileData('Public.jpg'));
        config(['services.venue_images.commons_daily_limit' => 2]);
        $gate->run('commons', 'search', WikidataImageFileData::class, $fetch);
        expect($calls)->toBe(2);
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
            expect($e->status)->toBe(429);
        }
        $this->travel(2)->seconds();
        $gate->run('wikidata', 'two', WikidataImageFileData::class, $fetch);
        $this->travel(2)->seconds();
        try {
            $gate->run('wikidata', 'three', WikidataImageFileData::class, $fetch);
            $this->fail('Quota ignored.');
        } catch (CatalogProviderUnavailable $e) {
            expect($e->status)->toBe(429)
                ->and($e->retryAfter)->toBeGreaterThan(1);
        }
        expect($calls)->toBe(2);
    }

    public function test_failures_are_not_cached_and_lock_contention_never_fetches(): void
    {
        $gate = app(ProviderReadGate::class);
        try {
            $gate->run('wikidata', 'failed', WikidataImageFileData::class, fn (): never => throw new CatalogProviderUnavailable);
        } catch (CatalogProviderUnavailable) {
            $this->addToAssertionCount(1);
        }
        $this->travel(2)->seconds();
        $fresh = $gate->run('wikidata', 'failed', WikidataImageFileData::class, fn (): WikidataImageFileData => new WikidataImageFileData('Fresh.jpg'));
        expect($fresh->file)->toBe('Fresh.jpg');
        $lock = Cache::lock('catalog-provider:gate:wikidata', 10);
        expect($lock->get())->toBeTrue();
        try {
            $gate->run('wikidata', 'blocked', WikidataImageFileData::class, fn (): never => $this->fail('Contended channel fetched.'));
        } catch (CatalogProviderUnavailable $e) {
            expect($e->status)->toBe(429);
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
            expect($failure->status)->toBe(429);
        }
        expect($calls)->toBe(2);
    }

    public function test_zero_quota_is_unavailable_and_errors_are_not_cached(): void
    {
        config(['services.venue_images.wikidata_daily_limit' => 0]);
        expect(fn () => app(ProviderReadGate::class)->run('wikidata', 'none', WikidataImageFileData::class, fn (): never => $this->fail('Unconfigured provider called.')))->toThrow(CatalogProviderUnavailable::class);
    }
}
