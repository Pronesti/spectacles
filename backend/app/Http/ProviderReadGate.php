<?php

namespace App\Http;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Sleep;
use Spatie\LaravelData\Data;

class ProviderReadGate
{
    /**
     * Spends one setlist.fm API request from the shared quota and pacing.
     *
     * @template TPayload of Data
     *
     * @param  callable(): TPayload  $fetch
     * @return TPayload
     */
    public function apiRequest(callable $fetch): Data
    {
        $dailyLimit = min(1439, Config::integer('services.setlistfm.api_daily_limit', 100));
        if (! Config::get('services.setlistfm.enabled', false) || $dailyLimit < 1) {
            throw new CatalogProviderUnavailable;
        }
        $interval = max(500, Config::integer('services.setlistfm.api_interval_ms', 1000));
        $lock = Cache::lock('catalog-provider:gate:setlist_api', 30);
        if (! $lock->get()) {
            throw new CatalogProviderUnavailable(429, 1);
        }
        try {
            $nextKey = 'catalog-provider:next:setlist_api';
            $next = $this->cachedNumber($nextKey);
            $now = now()->getTimestampMs();
            if ($next > $now && $next - $now <= 2000) {
                Sleep::usleep(($next - $now) * 1000);
            }
            $now = now('UTC');
            $quotaKey = 'catalog-provider:quota:setlist_api:'.$now->format('Y-m-d');
            $used = $this->cachedNumber($quotaKey);
            if ($used >= $dailyLimit) {
                throw new CatalogProviderUnavailable(429, max(1, (int) ceil($now->diffInSeconds($now->copy()->addDay()->startOfDay()))));
            }
            if ($next > $now->getTimestampMs()) {
                throw new CatalogProviderUnavailable(429, max(1, (int) ceil(($next - $now->getTimestampMs()) / 1000)));
            }
            if (! Cache::put($quotaKey, $used + 1, $now->copy()->addDay()->startOfDay()) || ! Cache::put($nextKey, $now->getTimestampMs() + $interval, $now->copy()->addMilliseconds($interval + 1000))) {
                throw new CatalogProviderUnavailable;
            }
            try {
                return $fetch();
            } finally {
                $finished = now();
                if (! Cache::put($nextKey, $finished->getTimestampMs() + $interval, $finished->copy()->addMilliseconds($interval + 1000))) {
                    throw new CatalogProviderUnavailable;
                }
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Reads through the channel's cache, quota and pacing, caching what `$fetch` returns.
     *
     * @template TData of Data
     *
     * @param  class-string<TData>  $dataClass
     * @param  callable(): TData  $fetch
     * @return TData
     */
    public function run(string $channel, string $cacheKey, string $dataClass, callable $fetch, bool $waitForSpacing = false): Data
    {
        try {
            return $this->read($channel, $cacheKey, $dataClass, $fetch, $waitForSpacing);
        } catch (QueryException $failure) {
            throw new CatalogProviderUnavailable;
        }
    }

    /**
     * @template TData of Data
     *
     * @param  class-string<TData>  $dataClass
     * @param  callable(): TData  $fetch
     * @return TData
     */
    private function read(string $channel, string $cacheKey, string $dataClass, callable $fetch, bool $waitForSpacing = false): Data
    {
        [$group, $prefix] = match ($channel) {
            'setlist_api' => ['setlistfm', 'api'],
            'wikidata' => ['venue_images', 'wikidata'], 'commons' => ['venue_images', 'commons'],
            default => throw new \InvalidArgumentException('Unknown provider channel.'),
        };
        $dailyLimit = Config::integer('services.'.$group.'.'.$prefix.'_daily_limit', 0);
        if (! Config::get('services.'.$group.'.enabled', false) || $dailyLimit < 1) {
            throw new CatalogProviderUnavailable;
        }
        $key = 'catalog-provider:v2:'.$channel.':'.hash('sha256', $cacheKey);
        $seconds = max(0, min(900, Config::integer('services.'.$group.'.cache_seconds', 0)));
        if ($seconds > 0 && is_array($cached = Cache::get($key))) {
            return CachedData::fromCache($dataClass, $cached);
        }
        if ($channel === 'setlist_api') {
            return $this->fetchAndCache($key, $seconds, $fetch);
        }
        $lock = Cache::lock('catalog-provider:gate:'.$channel, 10);
        if (! $lock->get()) {
            throw new CatalogProviderUnavailable(429, 2);
        }
        try {
            if ($seconds > 0 && is_array($cached = Cache::get($key))) {
                return CachedData::fromCache($dataClass, $cached);
            }
            $now = now()->getTimestampMs();
            $nextKey = 'catalog-provider:next:'.$channel;
            $next = $this->cachedNumber($nextKey);
            if ($waitForSpacing && $next > $now && $next - $now <= 2000) {
                usleep(($next - $now) * 1000);
                $now = now()->getTimestampMs();
            }
            if ($next > $now) {
                throw new CatalogProviderUnavailable(429, max(1, (int) ceil(($next - $now) / 1000)));
            }
            $day = now('UTC')->format('Y-m-d');
            $quotaKey = 'catalog-provider:quota:'.$channel.':'.$day;
            $used = $this->cachedNumber($quotaKey);
            if ($used >= $dailyLimit) {
                throw new CatalogProviderUnavailable(429, max(1, (int) now('UTC')->diffInSeconds(now('UTC')->addDay()->startOfDay())));
            }
            $interval = max(0, Config::integer('services.'.$group.'.'.$prefix.'_interval_ms', 1000));
            Cache::put($nextKey, $now + $interval, now()->addMilliseconds($interval + 1000));
            Cache::put($quotaKey, $used + 1, now('UTC')->addDay()->startOfDay());
        } finally {
            $lock->release();
        }

        return $this->fetchAndCache($key, $seconds, $fetch);
    }

    /**
     * @template TData of Data
     *
     * @param  callable(): TData  $fetch
     * @return TData
     */
    private function fetchAndCache(string $key, int $seconds, callable $fetch): Data
    {
        $result = $fetch();
        if ($seconds > 0) {
            Cache::put($key, CachedData::toCache($result), $seconds);
        }

        return $result;
    }

    /**
     * Reads a cached counter or timestamp; stores such as Redis return numbers as strings.
     */
    private function cachedNumber(string $key): int
    {
        $value = Cache::get($key, 0);

        return is_numeric($value) ? (int) $value : 0;
    }
}
