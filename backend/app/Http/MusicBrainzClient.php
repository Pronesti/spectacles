<?php

namespace App\Http;

use App\Http\Data\MusicBrainzSearchRowData;
use App\Http\Data\MusicBrainzTourScopeData;
use App\Http\Integrations\MusicBrainz\Data\MusicBrainzEntityPayloadData;
use App\Http\Integrations\MusicBrainz\Data\MusicBrainzSearchPayloadData;
use App\Http\Integrations\MusicBrainz\MusicBrainzConnector;
use App\Http\Integrations\MusicBrainz\Requests\GetMusicBrainzResource;
use App\Http\Integrations\ProviderConnector;
use App\Http\Integrations\UpstreamPayload;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Saloon\Exceptions\Request\FatalRequestException;
use Spatie\LaravelData\Data;

class MusicBrainzClient
{
    /**
     * @return list<MusicBrainzSearchRowData>
     */
    public function search(string $kind, string $query): array
    {
        try {
            return $this->cachedSearch($kind, $query);
        } catch (QueryException $failure) {
            throw new MusicBrainzUnavailable;
        }
    }

    /**
     * @return list<MusicBrainzSearchRowData>
     */
    public function tours(MusicBrainzTourScopeData $scope): array
    {
        if ($scope->artistMbids !== [] && (! config('services.musicbrainz.enabled') || trim((string) config('services.musicbrainz.user_agent')) === '')) {
            throw new MusicBrainzUnavailable;
        }
        $sets = [];
        $fetched = false;
        try {
            foreach ($scope->artistMbids as $artistId) {
                $key = 'musicbrainz:v2:artist-tours:'.$artistId;
                $cached = $this->cached($key, MusicBrainzSearchRowData::class);
                if ($cached === null) {
                    if ($fetched) {
                        throw new MusicBrainzUnavailable(429, 2);
                    }
                    $fetched = true;
                    $cached = $this->cachedResponse($key, 'artist/'.$artistId, ['inc' => 'series-rels', 'fmt' => 'json'], MusicBrainzEntityPayloadData::class, MusicBrainzSearchRowData::class, function (MusicBrainzEntityPayloadData $artist) use ($artistId): array {
                        if ($artist->id === null || strtolower($artist->id) !== $artistId || $artist->relations === null || ! array_is_list($artist->relations) || count($artist->relations) > 10000) {
                            throw new MusicBrainzUnavailable;
                        }
                        $rows = [];
                        foreach ($artist->relations as $relation) {
                            if ($relation->typeId !== 'f03d59f3-97db-424b-885e-e8be69d65225') {
                                continue;
                            }
                            $series = $relation->series;
                            if ($relation->targetType !== 'series' || $series === null || $series->id === null || ! Str::isUuid($series->id) || $this->text($series->name) === null || $series->type === null) {
                                throw new MusicBrainzUnavailable;
                            }
                            if (strcasecmp(trim($series->type), 'Tour') !== 0) {
                                continue;
                            }
                            $id = strtolower($series->id);
                            $rows[$id] = new MusicBrainzSearchRowData($id, 'series', $series->name, null, $this->text($series->disambiguation), null, null, []);
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
        $terms = preg_split('/\s+/u', mb_strtolower(trim($scope->query)), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($sets as $row) {
            foreach ($terms as $term) {
                if (! str_contains(mb_strtolower($row->label), $term)) {
                    continue 2;
                }
            }
            $rows[$row->id] = $row;
        }
        uasort($rows, fn (MusicBrainzSearchRowData $a, MusicBrainzSearchRowData $b): int => strcasecmp($a->label, $b->label) ?: strcmp($a->id, $b->id));

        return array_slice(array_values($rows), 0, 10);
    }

    /**
     * @return list<MusicBrainzSearchRowData>
     */
    private function cachedSearch(string $kind, string $query): array
    {
        if (! in_array($kind, ['artist', 'work', 'series'], true)) {
            throw new \InvalidArgumentException('Unsupported kind.');
        }
        $key = 'musicbrainz:v1:'.$kind.':'.hash('sha256', trim($query));

        return $this->cachedResponse($key, $kind, ['query' => $this->query($kind, $query), 'fmt' => 'json', 'limit' => 10], MusicBrainzSearchPayloadData::class, MusicBrainzSearchRowData::class, function (MusicBrainzSearchPayloadData $page) use ($kind): array {
            $results = match ($kind) {
                'artist' => $page->artists,
                'work' => $page->works,
                'series' => $page->series,
            };
            if ($results === null || ! array_is_list($results)) {
                throw new MusicBrainzUnavailable;
            }
            $rows = [];
            foreach ($results as $result) {
                $entity = $this->searchResult($result);
                if ($entity === null || $entity->id === null || ! Str::isUuid($entity->id)) {
                    continue;
                }
                if ($kind === 'series' && ($entity->type === null || strcasecmp(trim($entity->type), 'Tour') !== 0)) {
                    continue;
                }
                $id = strtolower($entity->id);
                $label = $this->text($kind === 'work' ? $entity->title : $entity->name);
                if ($label === null || isset($rows[$id])) {
                    continue;
                }
                $type = strtolower($this->text($entity->type) ?? '');
                $writers = [];
                foreach ($entity->relations ?? [] as $relation) {
                    if (in_array($relation->type, ['composer', 'lyricist'], true)) {
                        $name = $this->text($relation->artist?->name);
                        if ($name !== null) {
                            $writers[] = $name;
                        }
                    }
                }
                $rows[$id] = new MusicBrainzSearchRowData(
                    id: $id,
                    kind: $kind,
                    label: $label,
                    artistType: $kind === 'artist' && in_array($type, ['person', 'group', 'orchestra', 'choir', 'character', 'other'], true) ? $type : null,
                    disambiguation: $this->text($entity->disambiguation),
                    area: $kind !== 'series' ? $this->text($entity->area?->name) : null,
                    country: $kind === 'series' ? null : $this->text($entity->country),
                    writers: $kind === 'work' ? array_slice(array_values(array_unique($writers)), 0, 20) : [],
                );
                if (count($rows) === 10) {
                    break;
                }
            }

            return array_values($rows);
        });
    }

    /**
     * A search result that does not fit the entity format is skipped, not fatal.
     */
    private function searchResult(mixed $result): ?MusicBrainzEntityPayloadData
    {
        try {
            return UpstreamPayload::parse(MusicBrainzEntityPayloadData::class, $result, fn (): MusicBrainzUnavailable => new MusicBrainzUnavailable);
        } catch (MusicBrainzUnavailable) {
            return null;
        }
    }

    /**
     * Reads a public MusicBrainz resource and caches what `$normalize` makes of it.
     *
     * @template TPayload of Data
     * @template TData of Data
     *
     * @param  array<string, string|int>  $parameters
     * @param  class-string<TPayload>  $payloadClass
     * @param  class-string<TData>  $dataClass
     * @param  callable(TPayload): (TData|list<TData>)  $normalize
     * @return TData|list<TData>
     */
    public function publicRead(string $key, string $path, array $parameters, string $payloadClass, string $dataClass, callable $normalize): Data|array
    {
        try {
            return $this->cachedResponse($key, $path, $parameters, $payloadClass, $dataClass, $normalize);
        } catch (QueryException $failure) {
            throw new MusicBrainzUnavailable;
        }
    }

    /**
     * @template TPayload of Data
     * @template TData of Data
     *
     * @param  array<string, string|int>  $parameters
     * @param  class-string<TPayload>  $payloadClass
     * @param  class-string<TData>  $dataClass
     * @param  callable(TPayload): (TData|list<TData>)  $normalize
     * @return TData|list<TData>
     */
    private function cachedResponse(string $key, string $path, array $parameters, string $payloadClass, string $dataClass, callable $normalize): Data|array
    {
        $userAgent = trim((string) config('services.musicbrainz.user_agent'));
        if (! config('services.musicbrainz.enabled') || $userAgent === '') {
            throw new MusicBrainzUnavailable;
        }
        $cached = $this->cached($key, $dataClass);
        if ($cached !== null) {
            return $cached;
        }
        $lock = Cache::lock('musicbrainz:v1:outbound', 10);
        if (! $lock->get()) {
            throw new MusicBrainzUnavailable(429, 2);
        }
        try {
            $cached = $this->cached($key, $dataClass);
            if ($cached !== null) {
                return $cached;
            }
            $now = now()->getTimestampMs();
            $next = (int) Cache::get('musicbrainz:v1:next-start-ms', 0);
            if ($next > $now) {
                throw new MusicBrainzUnavailable(429, max(1, (int) ceil(($next - $now) / 1000)));
            }
            Cache::put('musicbrainz:v1:next-start-ms', $now + 1100, now()->addSeconds(10));
            try {
                $response = (new MusicBrainzConnector($userAgent))->send(new GetMusicBrainzResource($path, $parameters));
            } catch (FatalRequestException $failure) {
                throw new MusicBrainzUnavailable;
            }
            if ($response->status() === 429) {
                throw new MusicBrainzUnavailable(429, ProviderConnector::retryAfter($response, 60));
            }
            if (ProviderConnector::declaresOversizedBody($response)) {
                throw new MusicBrainzUnavailable;
            }
            $raw = ProviderConnector::boundedBody($response);
            if (! $response->successful() || $raw === null) {
                throw new MusicBrainzUnavailable;
            }
            $result = $normalize(UpstreamPayload::parse($payloadClass, json_decode($raw, true), fn (): MusicBrainzUnavailable => new MusicBrainzUnavailable));
            Cache::put($key, CachedData::toCache($result), 900);

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * @template TData of Data
     *
     * @param  class-string<TData>  $dataClass
     * @return TData|list<TData>|null
     */
    private function cached(string $key, string $dataClass): Data|array|null
    {
        $cached = Cache::get($key);

        return is_array($cached) ? CachedData::fromCache($dataClass, $cached) : null;
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
