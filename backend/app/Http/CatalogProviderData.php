<?php

namespace App\Http;

use App\Http\Data\ArtistValuesData;
use App\Http\Data\CalendarDateData;
use App\Http\Data\ProviderArtistData;
use App\Http\Data\ProviderEntityInputData;
use App\Http\Data\ProviderEntityValuesInputData;
use App\Http\Data\ProviderPerformanceData;
use App\Http\Data\ProviderPerformanceInputData;
use App\Http\Data\ProviderRefData;
use App\Http\Data\ProviderRefInputData;
use App\Http\Data\ProviderTourData;
use App\Http\Data\ProviderVenueData;
use App\Http\Data\TourValuesData;
use App\Http\Data\VenueImageData;
use App\Http\Data\VenueImageInputData;
use App\Http\Data\VenueSourceData;
use App\Http\Data\VenueSourceInputData;
use App\Http\Data\VenueValuesData;
use Illuminate\Support\Str;
use Spatie\LaravelData\Optional;

class CatalogProviderData
{
    /**
     * @return ($nullable is true ? string|null : string)
     */
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

    public static function ref(?ProviderRefInputData $value): ProviderRefData
    {
        $provider = $value?->provider;
        $kind = $value?->resourceType;
        $id = $value?->externalId;
        $valid = $provider === 'musicbrainz' ? in_array($kind, ['artist', 'work', 'series', 'place'], true) && is_string($id) && Str::isUuid($id)
            : $provider === 'setlist_fm' && in_array($kind, ['artist', 'tour', 'venue', 'setlist'], true) && is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id);
        if (! $valid || $provider === null) {
            throw new CatalogProviderUnavailable;
        }

        return new ProviderRefData($provider, $kind, $provider === 'musicbrainz' ? strtolower($id) : $id);
    }

    /**
     * Validates an untrusted provider entity and returns the Data class for its kind.
     */
    public static function entity(?ProviderEntityInputData $value): ProviderArtistData|ProviderTourData|ProviderVenueData
    {
        $ref = self::ref($value?->ref);
        $values = $value->values ?? new ProviderEntityValuesInputData;
        $values = match ($ref->resourceType) {
            'tour', 'series' => new TourValuesData(self::text($values->name)),
            'artist' => new ArtistValuesData(self::text($values->name), self::text($values->type, 255, true), self::text($values->disambiguation, 255, true)),
            'place', 'venue' => self::venueValues($values),
            default => throw new CatalogProviderUnavailable,
        };
        $url = self::text($value?->url, 2048);
        if ($ref->provider === 'setlist_fm') {
            if (app(SetlistFmUrl::class)->id($url, $ref->resourceType) !== $ref->externalId) {
                throw new CatalogProviderUnavailable;
            }
        } elseif ($ref->resourceType === 'artist' && str_starts_with($url, 'https://www.setlist.fm/')) {
            app(SetlistFmUrl::class)->id($url, 'artist');
        } elseif ($url !== 'https://musicbrainz.org/'.$ref->resourceType.'/'.$ref->externalId) {
            throw new CatalogProviderUnavailable;
        }
        if ($values instanceof TourValuesData) {
            return new ProviderTourData($ref, $values, $url);
        }
        if ($values instanceof ArtistValuesData) {
            return new ProviderArtistData($ref, $values, $url);
        }

        $refs = $value->linkedRefs ?? [];
        if (! array_is_list($refs) || count($refs) > 5) {
            throw new CatalogProviderUnavailable;
        }
        $seen = [$ref->key() => true];
        $linkedRefs = [];
        foreach ($refs as $linked) {
            $linked = self::ref($linked);
            if (! in_array($linked->resourceType, ['place', 'venue'], true) || isset($seen[$linked->key()])) {
                throw new CatalogProviderUnavailable;
            }
            $seen[$linked->key()] = true;
            $linkedRefs[] = $linked;
        }
        $isKnownSetlistVenue = fn (string $venueId): bool => array_any([$ref, ...$linkedRefs], fn (ProviderRefData $identity): bool => $identity->provider === 'setlist_fm' && $identity->resourceType === 'venue' && $identity->externalId === $venueId);
        $venueUrl = $value->venueUrl ?? ($ref->provider === 'setlist_fm' ? $url : null);
        if ($venueUrl !== null) {
            $venueId = app(SetlistFmUrl::class)->id($venueUrl, 'venue');
            if (parse_url($venueUrl, PHP_URL_QUERY) !== null || ! $isKnownSetlistVenue($venueId)) {
                throw new CatalogProviderUnavailable;
            }
        }
        $sources = $value->venueSources ?? [];
        if (! array_is_list($sources) || count($sources) > 6) {
            throw new CatalogProviderUnavailable;
        }
        if ($venueUrl !== null) {
            $sources[] = new VenueSourceInputData(app(SetlistFmUrl::class)->id($venueUrl, 'venue'), $venueUrl);
        }
        $urls = [];
        foreach ($sources as $source) {
            $sourceUrl = self::text($source->url, 2048);
            $sourceId = app(SetlistFmUrl::class)->id($sourceUrl, 'venue');
            if ($source->externalId !== $sourceId || parse_url($sourceUrl, PHP_URL_QUERY) !== null || ! $isKnownSetlistVenue($sourceId) || isset($urls[$sourceId]) && $urls[$sourceId] !== $sourceUrl) {
                throw new CatalogProviderUnavailable;
            }
            $urls[$sourceId] = $sourceUrl;
        }

        return new ProviderVenueData(
            ref: $ref,
            values: $values,
            url: $url,
            linkedRefs: $linkedRefs,
            venueUrl: $venueUrl ?? new Optional,
            venueSources: array_map(fn (int|string $id): VenueSourceData => new VenueSourceData((string) $id, $urls[$id]), array_keys($urls)),
        );
    }

    public static function venue(ProviderEntityInputData $value): ProviderVenueData
    {
        $entity = self::entity($value);

        return $entity instanceof ProviderVenueData ? $entity : throw new CatalogProviderUnavailable;
    }

    public static function tour(ProviderEntityInputData $value): ProviderTourData
    {
        $entity = self::entity($value);

        return $entity instanceof ProviderTourData ? $entity : throw new CatalogProviderUnavailable;
    }

    public static function venueValues(ProviderEntityValuesInputData $value): VenueValuesData
    {
        $lat = $value->lat;
        $lng = $value->lng;
        if (($lat === null) !== ($lng === null) || $lat !== null && (! is_numeric($lat) || ! is_numeric($lng) || ! is_finite((float) $lat) || ! is_finite((float) $lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180)) {
            throw new CatalogProviderUnavailable;
        }
        $aliases = $value->aliases ?? [];
        if (! array_is_list($aliases) || count($aliases) > 100) {
            throw new CatalogProviderUnavailable;
        }
        $aliases = array_map(fn ($alias): string => self::text($alias), $aliases);
        if (count(array_unique($aliases)) !== count($aliases)) {
            throw new CatalogProviderUnavailable;
        }
        $country = self::text($value->countryCode, 2, true);
        if ($country !== null && ! preg_match('/^[A-Z]{2}$/D', $country)) {
            throw new CatalogProviderUnavailable;
        }
        $zone = self::text($value->timezone, 255, true);
        if ($zone !== null && ! in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            throw new CatalogProviderUnavailable;
        }

        return new VenueValuesData(
            name: self::text($value->name),
            city: $value->city !== null && $value->city !== '' ? self::text($value->city) : '',
            region: self::text($value->region, 255, true),
            countryCode: $country,
            address: self::text($value->address, 2000, true),
            aliases: $aliases,
            lat: $lat === null ? null : (float) $lat,
            lng: $lng === null ? null : (float) $lng,
            timezone: $zone,
            image: self::image($value->image),
        );
    }

    public static function image(?VenueImageInputData $value): ?VenueImageData
    {
        if (! $value instanceof VenueImageInputData) {
            return null;
        }
        $attribution = function (?string $text, int $max): string {
            $text = self::text($text, $max);
            if (str_contains($text, '<') || str_contains($text, '>')) {
                throw new CatalogProviderUnavailable;
            }

            return $text;
        };
        $link = function (?string $url, array $hosts) use ($attribution): string {
            $url = $attribution($url, 2048);
            $parts = parse_url($url);
            if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! in_array($parts['host'] ?? '', $hosts, true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                throw new CatalogProviderUnavailable;
            }

            return $url;
        };
        $mediaHosts = ['upload.wikimedia.org', 'thumb.wikimedia.org'];

        return new VenueImageData(
            url: $link($value->url, $mediaHosts),
            thumbnailUrl: $link($value->thumbnailUrl, $mediaHosts),
            sourceUrl: $link($value->sourceUrl, ['commons.wikimedia.org']),
            credit: $attribution($value->credit, 2000),
            license: $attribution($value->license, 255),
            licenseUrl: $link($value->licenseUrl, ['creativecommons.org', 'commons.wikimedia.org']),
        );
    }

    public static function performance(ProviderPerformanceInputData $value): ProviderPerformanceData
    {
        $ref = self::ref($value->ref);
        $url = $value->url;
        if ($url === null || $ref->provider !== 'setlist_fm' || $ref->resourceType !== 'setlist' || app(SetlistFmUrl::class)->id($url, 'setlist') !== $ref->externalId) {
            throw new CatalogProviderUnavailable;
        }
        $date = $value->date;
        if ($date?->year === null || $date->month === null || $date->day === null || ! checkdate($date->month, $date->day, $date->year) || $date->year < 1 || $date->year > 9999) {
            throw new CatalogProviderUnavailable;
        }
        $artist = self::entity($value->artist);
        $venue = self::entity($value->venue);
        $tour = $value->tour instanceof ProviderEntityInputData ? self::entity($value->tour) : null;
        if (! $artist instanceof ProviderArtistData || $artist->ref->provider !== 'musicbrainz' || ! $venue instanceof ProviderVenueData || $tour !== null && ! $tour instanceof ProviderTourData) {
            throw new CatalogProviderUnavailable;
        }
        $time = $value->time;
        $zone = $value->timezone;
        if ($time !== null && (! preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $time)) || $zone !== null && ! in_array($zone, \DateTimeZone::listIdentifiers(), true)) {
            throw new CatalogProviderUnavailable;
        }

        return new ProviderPerformanceData(
            ref: $ref,
            url: $url,
            date: new CalendarDateData($date->year, $date->month, $date->day),
            artist: $artist,
            tour: $tour,
            venue: $venue,
            time: $time,
            timezone: $zone,
            tourName: $value->tourName instanceof Optional ? $value->tourName : self::text($value->tourName, 255, true),
        );
    }
}
