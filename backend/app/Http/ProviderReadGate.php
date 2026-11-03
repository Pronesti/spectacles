<?php

namespace App\Http;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

class ProviderReadGate
{
    public function apiRequest(callable $fetch): array
    {
        $settings = config('services.setlistfm', []);
        $dailyLimit = min(1439, (int) ($settings['api_daily_limit'] ?? 100));
        if (! ($settings['enabled'] ?? false) || $dailyLimit < 1) {
            throw new CatalogProviderUnavailable;
        }
        $interval = max(500, (int) ($settings['api_interval_ms'] ?? 1000));
        $lock = Cache::lock('catalog-provider:gate:setlist_api', 30);
        if (! $lock->get()) {
            throw new CatalogProviderUnavailable(429, 1);
        }
        try {
            $nextKey = 'catalog-provider:next:setlist_api';
            $next = (int) Cache::get($nextKey, 0);
            $now = now()->getTimestampMs();
            if ($next > $now && $next - $now <= 2000) {
                Sleep::usleep(($next - $now) * 1000);
            }
            $now = now('UTC');
            $quotaKey = 'catalog-provider:quota:setlist_api:'.$now->format('Y-m-d');
            $used = (int) Cache::get($quotaKey, 0);
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

    public function run(string $channel, string $cacheKey, callable $fetch, bool $waitForSpacing = false): array
    {
        try {
            return $this->read($channel, $cacheKey, $fetch, $waitForSpacing);
        } catch (QueryException $failure) {
            throw new CatalogProviderUnavailable;
        }
    }

    private function read(string $channel, string $cacheKey, callable $fetch, bool $waitForSpacing = false): array
    {
        [$group, $prefix] = match ($channel) {
            'setlist_api' => ['setlistfm', 'api'],
            'setlist_html' => ['setlistfm', 'html'],
            'wikidata' => ['venue_images', 'wikidata'], 'commons' => ['venue_images', 'commons'],
            default => throw new \InvalidArgumentException('Unknown provider channel.'),
        };
        $settings = config('services.'.$group, []);
        if (! ($settings['enabled'] ?? false) || ($settings[$prefix.'_daily_limit'] ?? 0) < 1) {
            throw new CatalogProviderUnavailable;
        }
        $key = 'catalog-provider:v2:'.$channel.':'.hash('sha256', $cacheKey);
        $seconds = max(0, min(900, (int) ($settings['cache_seconds'] ?? 0)));
        if ($seconds > 0 && is_array($cached = Cache::get($key))) {
            return $cached;
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
                return $cached;
            }
            $now = now()->getTimestampMs();
            $nextKey = 'catalog-provider:next:'.$channel;
            $next = (int) Cache::get($nextKey, 0);
            if ($waitForSpacing && $next > $now && $next - $now <= 2000) {
                usleep(($next - $now) * 1000);
                $now = now()->getTimestampMs();
            }
            if ($next > $now) {
                throw new CatalogProviderUnavailable(429, max(1, (int) ceil(($next - $now) / 1000)));
            }
            $day = now('UTC')->format('Y-m-d');
            $quotaKey = 'catalog-provider:quota:'.$channel.':'.$day;
            $used = (int) Cache::get($quotaKey, 0);
            if ($used >= $settings[$prefix.'_daily_limit']) {
                throw new CatalogProviderUnavailable(429, max(1, (int) now('UTC')->diffInSeconds(now('UTC')->addDay()->startOfDay())));
            }
            $interval = max(0, (int) ($settings[$prefix.'_interval_ms'] ?? 1000));
            Cache::put($nextKey, $now + $interval, now()->addMilliseconds($interval + 1000));
            Cache::put($quotaKey, $used + 1, now('UTC')->addDay()->startOfDay());
        } finally {
            $lock->release();
        }

        return $this->fetchAndCache($key, $seconds, $fetch);
    }

    private function fetchAndCache(string $key, int $seconds, callable $fetch): array
    {
        $result = $fetch();
        if (! is_array($result)) {
            throw new CatalogProviderUnavailable;
        }
        if ($seconds > 0) {
            Cache::put($key, $result, $seconds);
        }

        return $result;
    }
}
