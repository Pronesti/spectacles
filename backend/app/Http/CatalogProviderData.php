<?php

namespace App\Http;

use Illuminate\Support\Str;

class CatalogProviderData
{
    public static function text(mixed $value, int $max = 255, bool $nullable = false): ?string
    {
        if ($nullable && $value === null) {
            return null;
        }
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value)) {
            throw new CatalogProviderUnavailable;
        }

        return trim($value);
    }

    public static function ref(array $value): array
    {
        $provider = $value['provider'] ?? null;
        $kind = $value['resourceType'] ?? null;
        $id = $value['externalId'] ?? null;
        $valid = $provider === 'musicbrainz' ? in_array($kind, ['artist', 'work', 'series', 'place'], true) && is_string($id) && Str::isUuid($id)
            : $provider === 'setlist_fm' && in_array($kind, ['artist', 'tour', 'venue', 'setlist'], true) && is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id);
        if (! $valid) {
            throw new CatalogProviderUnavailable;
        }

        return ['provider' => $provider, 'resourceType' => $kind, 'externalId' => $provider === 'musicbrainz' ? strtolower($id) : $id];
    }

    public static function entity(array $value): array
    {
        $ref = self::ref($value['ref'] ?? []);
        $values = $value['values'] ?? [];
        if (! is_array($values)) {
            throw new CatalogProviderUnavailable;
        }
        $values = match ($ref['resourceType']) {
            'tour', 'series' => ['name' => self::text($values['name'] ?? null)],
            'artist' => ['name' => self::text($values['name'] ?? null), 'type' => self::text($values['type'] ?? null, 255, true), 'disambiguation' => self::text($values['disambiguation'] ?? null, 255, true)],
            'place', 'venue' => self::venueValues($values),
            default => throw new CatalogProviderUnavailable,
        };
        $url = self::text($value['url'] ?? null, 2048);
        if ($ref['provider'] === 'setlist_fm') {
            if (app(SetlistFmUrl::class)->id($url, $ref['resourceType']) !== $ref['externalId']) {
                throw new CatalogProviderUnavailable;
            }
        } elseif ($ref['resourceType'] === 'artist' && str_starts_with($url, 'https://www.setlist.fm/')) {
            app(SetlistFmUrl::class)->id($url, 'artist');
        } elseif ($url !== 'https://musicbrainz.org/'.$ref['resourceType'].'/'.$ref['externalId']) {
            throw new CatalogProviderUnavailable;
        }
        $result = ['ref' => $ref, 'values' => $values, 'url' => $url];
        if (in_array($ref['resourceType'], ['place', 'venue'], true)) {
            $refs = $value['linkedRefs'] ?? [];
            if (! is_array($refs) || ! array_is_list($refs) || count($refs) > 5) {
                throw new CatalogProviderUnavailable;
            }
            $seen = [json_encode($ref) => true];
            $result['linkedRefs'] = [];
            foreach ($refs as $linked) {
                $linked = self::ref($linked);
                if (! in_array($linked['resourceType'], ['place', 'venue'], true) || isset($seen[json_encode($linked)])) {
                    throw new CatalogProviderUnavailable;
                }
                $seen[json_encode($linked)] = true;
                $result['linkedRefs'][] = $linked;
            }
            $venueUrl = $value['venueUrl'] ?? ($ref['provider'] === 'setlist_fm' ? $url : null);
            if ($venueUrl !== null) {
                $venueId = app(SetlistFmUrl::class)->id($venueUrl, 'venue');
                if (parse_url($venueUrl, PHP_URL_QUERY) !== null || ! array_any([$ref, ...$result['linkedRefs']], fn ($identity) => $identity['provider'] === 'setlist_fm' && $identity['resourceType'] === 'venue' && $identity['externalId'] === $venueId)) {
                    throw new CatalogProviderUnavailable;
                }
                $result['venueUrl'] = $venueUrl;
            }
            $sources = $value['venueSources'] ?? [];
            if (! is_array($sources) || ! array_is_list($sources) || count($sources) > 6) {
                throw new CatalogProviderUnavailable;
            }
            if ($venueUrl !== null) {
                $sources[] = ['externalId' => app(SetlistFmUrl::class)->id($venueUrl, 'venue'), 'url' => $venueUrl];
            }
            $urls = [];
            foreach ($sources as $source) {
                if (! is_array($source)) {
                    throw new CatalogProviderUnavailable;
                }
                $sourceUrl = self::text($source['url'] ?? null, 2048);
                $sourceId = app(SetlistFmUrl::class)->id($sourceUrl, 'venue');
                if (($source['externalId'] ?? null) !== $sourceId || parse_url($sourceUrl, PHP_URL_QUERY) !== null || ! array_any([$ref, ...$result['linkedRefs']], fn ($identity) => $identity['provider'] === 'setlist_fm' && $identity['resourceType'] === 'venue' && $identity['externalId'] === $sourceId) || isset($urls[$sourceId]) && $urls[$sourceId] !== $sourceUrl) {
                    throw new CatalogProviderUnavailable;
                }
                $urls[$sourceId] = $sourceUrl;
            }
            $result['venueSources'] = array_map(fn ($id, $source) => ['externalId' => (string) $id, 'url' => $source], array_keys($urls), array_values($urls));
        }

        return $result;
    }

    public static function venueValues(array $value): array
    {
        $lat = $value['lat'] ?? null;
        $lng = $value['lng'] ?? null;
        if (($lat === null) !== ($lng === null) || $lat !== null && (! is_numeric($lat) || ! is_numeric($lng) || ! is_finite((float) $lat) || ! is_finite((float) $lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180)) {
            throw new CatalogProviderUnavailable;
        }
        $aliases = $value['aliases'] ?? [];
        if (! is_array($aliases) || ! array_is_list($aliases) || count($aliases) > 100) {
            throw new CatalogProviderUnavailable;
        }
        $aliases = array_map(fn ($alias) => self::text($alias), $aliases);
        if (count(array_unique($aliases)) !== count($aliases)) {
            throw new CatalogProviderUnavailable;
        }
        $country = self::text($value['countryCode'] ?? null, 2, true);
        if ($country !== null && ! preg_match('/^[A-Z]{2}$/D', $country)) {
            throw new CatalogProviderUnavailable;
        }
        $zone = self::text($value['timezone'] ?? null, 255, true);
        if ($zone !== null && ! in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            throw new CatalogProviderUnavailable;
        }

        return ['name' => self::text($value['name'] ?? null), 'city' => isset($value['city']) && $value['city'] !== '' ? self::text($value['city']) : '', 'region' => self::text($value['region'] ?? null, 255, true), 'countryCode' => $country, 'address' => self::text($value['address'] ?? null, 2000, true), 'aliases' => $aliases, 'lat' => $lat === null ? null : (float) $lat, 'lng' => $lng === null ? null : (float) $lng, 'timezone' => $zone, 'image' => self::image($value['image'] ?? null)];
    }

    public static function image(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value) || array_diff(array_keys($value), ['url', 'thumbnailUrl', 'sourceUrl', 'credit', 'license', 'licenseUrl']) !== []) {
            throw new CatalogProviderUnavailable;
        }
        $result = [];
        foreach (['url', 'thumbnailUrl', 'sourceUrl', 'credit', 'license', 'licenseUrl'] as $key) {
            $result[$key] = self::text($value[$key] ?? null, $key === 'credit' ? 2000 : ($key === 'license' ? 255 : 2048));
            if (str_contains($result[$key], '<') || str_contains($result[$key], '>')) {
                throw new CatalogProviderUnavailable;
            }
            if (in_array($key, ['url', 'thumbnailUrl', 'sourceUrl', 'licenseUrl'], true)) {
                $parts = parse_url($result[$key]);
                $hosts = match ($key) {
                    'url', 'thumbnailUrl' => ['upload.wikimedia.org', 'thumb.wikimedia.org'],
                    'sourceUrl' => ['commons.wikimedia.org'],
                    default => ['creativecommons.org', 'commons.wikimedia.org'],
                };
                if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! in_array($parts['host'] ?? '', $hosts, true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                    throw new CatalogProviderUnavailable;
                }
            }
        }

        return $result;
    }

    public static function performance(array $value): array
    {
        $ref = self::ref($value['ref'] ?? []);
        if ($ref['provider'] !== 'setlist_fm' || $ref['resourceType'] !== 'setlist' || app(SetlistFmUrl::class)->id($value['url'] ?? '', 'setlist') !== $ref['externalId']) {
            throw new CatalogProviderUnavailable;
        }
        $date = $value['date'] ?? [];
        if (! is_array($date) || array_diff(array_keys($date), ['year', 'month', 'day']) !== [] || ! is_int($date['year'] ?? null) || ! is_int($date['month'] ?? null) || ! is_int($date['day'] ?? null) || ! checkdate($date['month'], $date['day'], $date['year']) || $date['year'] < 1 || $date['year'] > 9999) {
            throw new CatalogProviderUnavailable;
        }
        $artist = self::entity($value['artist'] ?? []);
        $venue = self::entity($value['venue'] ?? []);
        $tour = ($value['tour'] ?? null) === null ? null : self::entity($value['tour']);
        if ($artist['ref']['provider'] !== 'musicbrainz' || $artist['ref']['resourceType'] !== 'artist' || ! in_array($venue['ref']['resourceType'], ['venue', 'place'], true) || $tour !== null && ! in_array($tour['ref']['resourceType'], ['tour', 'series'], true)) {
            throw new CatalogProviderUnavailable;
        }
        $time = $value['time'] ?? null;
        $zone = $value['timezone'] ?? null;
        if ($time !== null && (! is_string($time) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $time)) || $zone !== null && ! in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            throw new CatalogProviderUnavailable;
        }

        return ['ref' => $ref, 'url' => $value['url'], 'date' => $date, 'artist' => $artist, 'tour' => $tour, 'venue' => $venue, 'time' => $time, 'timezone' => $zone];
    }
}
