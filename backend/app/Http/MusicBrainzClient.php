<?php

namespace App\Http;

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MusicBrainzClient
{
    public function search(string $kind, string $query): array
    {
        try {
            return $this->cachedSearch($kind, $query);
        } catch (QueryException $failure) {
            throw new MusicBrainzUnavailable;
        }
    }

    public function tours(string $query, array $artistIds): array
    {
        $artistIds = array_values(array_unique(array_map('strtolower', $artistIds)));
        sort($artistIds);
        if ($artistIds !== [] && (! config('services.musicbrainz.enabled') || trim((string) config('services.musicbrainz.user_agent')) === '')) {
            throw new MusicBrainzUnavailable;
        }
        $sets = [];
        $fetched = false;
        try {
            foreach ($artistIds as $artistId) {
                if (! Str::isUuid($artistId)) {
                    throw new MusicBrainzUnavailable;
                }
                $key = 'musicbrainz:v2:artist-tours:'.$artistId;
                $cached = Cache::get($key);
                if (! is_array($cached)) {
                    if ($fetched) {
                        throw new MusicBrainzUnavailable(429, 2);
                    }
                    $fetched = true;
                    $cached = $this->cachedResponse($key, 'artist/'.$artistId, ['inc' => 'series-rels', 'fmt' => 'json'], function (array $body) use ($artistId): array {
                        if (! is_string($body['id'] ?? null) || strtolower($body['id']) !== $artistId || ! isset($body['relations']) || ! is_array($body['relations']) || ! array_is_list($body['relations']) || count($body['relations']) > 10000) {
                            throw new MusicBrainzUnavailable;
                        }
                        $rows = [];
                        foreach ($body['relations'] as $relation) {
                            if (! is_array($relation)) {
                                throw new MusicBrainzUnavailable;
                            }
                            if (($relation['type-id'] ?? null) !== 'f03d59f3-97db-424b-885e-e8be69d65225') {
                                continue;
                            }
                            $series = $relation['series'] ?? null;
                            if (($relation['target-type'] ?? null) !== 'series' || ! is_array($series) || ! is_string($series['id'] ?? null) || ! Str::isUuid($series['id']) || $this->text($series['name'] ?? null) === null || ! is_string($series['type'] ?? null)) {
                                throw new MusicBrainzUnavailable;
                            }
                            if (strcasecmp(trim($series['type']), 'Tour') !== 0) {
                                continue;
                            }
                            $id = strtolower($series['id']);
                            $rows[$id] = ['id' => $id, 'kind' => 'series', 'label' => $series['name'], 'artistType' => null, 'disambiguation' => $this->text($series['disambiguation'] ?? null), 'area' => null, 'country' => null, 'writers' => []];
                        }

                        return array_values($rows);
                    });
                }
                $sets = [...$sets, ...$cached];
            }
        } catch (QueryException $failure) {
            throw new MusicBrainzUnavailable;
        }
        $rows = [];
        $terms = preg_split('/\s+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($sets as $row) {
            foreach ($terms as $term) {
                if (! str_contains(mb_strtolower($row['label']), $term)) {
                    continue 2;
                }
            }
            $rows[$row['id']] = $row;
        }
        uasort($rows, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']) ?: strcmp($a['id'], $b['id']));

        return array_slice(array_values($rows), 0, 10);
    }

    private function cachedSearch(string $kind, string $query): array
    {
        $resources = ['artist' => ['artists', 'name'], 'work' => ['works', 'title'], 'series' => ['series', 'name']];
        if (! isset($resources[$kind])) {
            throw new \InvalidArgumentException('Unsupported kind.');
        }
        $key = 'musicbrainz:v1:'.$kind.':'.hash('sha256', trim($query));

        return $this->cachedResponse($key, $kind, ['query' => $this->query($kind, $query), 'fmt' => 'json', 'limit' => 10], function (array $body) use ($kind, $resources): array {
            [$plural, $labelField] = $resources[$kind];
            if (! isset($body[$plural]) || ! is_array($body[$plural]) || ! array_is_list($body[$plural])) {
                throw new MusicBrainzUnavailable;
            }
            $rows = [];
            foreach ($body[$plural] as $raw) {
                if (! is_array($raw) || ! is_string($raw['id'] ?? null) || ! Str::isUuid($raw['id'])) {
                    continue;
                }
                if ($kind === 'series' && (! is_string($raw['type'] ?? null) || strcasecmp(trim($raw['type']), 'Tour') !== 0)) {
                    continue;
                }
                $id = strtolower($raw['id']);
                $label = $this->text($raw[$labelField] ?? null);
                if ($label === null || isset($rows[$id])) {
                    continue;
                }
                $type = strtolower($this->text($raw['type'] ?? null) ?? '');
                $writers = [];
                foreach (is_array($raw['relations'] ?? null) ? $raw['relations'] : [] as $relation) {
                    if (is_array($relation) && in_array($relation['type'] ?? null, ['composer', 'lyricist'], true)) {
                        $name = $this->text($relation['artist']['name'] ?? null);
                        if ($name !== null) {
                            $writers[] = $name;
                        }
                    }
                }
                $rows[$id] = ['id' => $id, 'kind' => $kind, 'label' => $label, 'artistType' => $kind === 'artist' && in_array($type, ['person', 'group', 'orchestra', 'choir', 'character', 'other'], true) ? $type : null, 'disambiguation' => $this->text($raw['disambiguation'] ?? null), 'area' => $kind !== 'series' && is_array($raw['area'] ?? null) ? $this->text($raw['area']['name'] ?? null) : null, 'country' => $kind === 'series' ? null : $this->text($raw['country'] ?? null), 'writers' => $kind === 'work' ? array_slice(array_values(array_unique($writers)), 0, 20) : []];
                if (count($rows) === 10) {
                    break;
                }
            }

            return array_values($rows);
        });
    }

    private function cachedResponse(string $key, string $path, array $parameters, callable $normalize): array
    {
        $userAgent = trim((string) config('services.musicbrainz.user_agent'));
        if (! config('services.musicbrainz.enabled') || $userAgent === '') {
            throw new MusicBrainzUnavailable;
        }
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $lock = Cache::lock('musicbrainz:v1:outbound', 10);
        if (! $lock->get()) {
            throw new MusicBrainzUnavailable(429, 2);
        }
        try {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
            $now = now()->getTimestampMs();
            $next = (int) Cache::get('musicbrainz:v1:next-start-ms', 0);
            if ($next > $now) {
                throw new MusicBrainzUnavailable(429, max(1, (int) ceil(($next - $now) / 1000)));
            }
            Cache::put('musicbrainz:v1:next-start-ms', $now + 1100, now()->addSeconds(10));
            try {
                $response = Http::acceptJson()->withUserAgent($userAgent)->connectTimeout(2)->timeout(5)->withoutRedirecting()->get('https://musicbrainz.org/ws/2/'.$path, $parameters);
            } catch (ConnectionException $failure) {
                throw new MusicBrainzUnavailable;
            }
            if ($response->status() === 429) {
                throw new MusicBrainzUnavailable(429, max(1, min(60, (int) $response->header('Retry-After') ?: 2)));
            }
            $body = $response->json();
            if (! $response->successful() || ! is_array($body)) {
                throw new MusicBrainzUnavailable;
            }
            $result = $normalize($body);
            Cache::put($key, $result, 900);

            return $result;
        } finally {
            $lock->release();
        }
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= 255 ? $value : null;
    }

    private function query(string $kind, string $query): string
    {
        $terms = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);
        $escaped = array_map(function (string $term): string {
            foreach (str_split('\\+-!(){}[]^"~*?:/&|') as $character) {
                $term = str_replace($character, '\\'.$character, $term);
            }

            return $term.'*';
        }, $terms);

        $literal = $kind.':('.implode(' AND ', $escaped).')';

        return $kind === 'series' ? $literal.' AND type:tour' : $literal;
    }
}
