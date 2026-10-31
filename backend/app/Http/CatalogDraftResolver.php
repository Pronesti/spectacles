<?php

namespace App\Http;

use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Song;
use App\Models\SongProviderId;
use App\Models\Tour;
use App\Models\TourProviderId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CatalogDraftResolver
{
    public function resolve(array $concert, array $pending): array
    {
        if ($pending === []) {
            return $concert;
        }
        $jobs = [];
        foreach (['artists' => 'artist', 'songs' => 'work', 'tours' => 'series'] as $map => $kind) {
            foreach ($pending[$map] ?? [] as $key => $input) {
                $path = 'pendingCatalog.'.$map.'.'.$key.'.selection';
                $ref = $input['selection'] === null ? null : ($kind === 'series'
                    ? app(CatalogProviderSelection::class)->tour($input['selection'], $path)['ref']
                    : ['provider' => 'musicbrainz', 'resourceType' => $kind, 'externalId' => app(MusicBrainzSelection::class)->read($input['selection'], $kind, $path)]);
                $mbid = $ref === null ? null : $ref['externalId'];
                $fields = $input;
                unset($fields['selection']);
                $jobs[] = ['map' => $map, 'key' => $key, 'kind' => $kind, 'mbid' => $mbid, 'ref' => $ref, 'fields' => $fields];
            }
        }
        usort($jobs, fn (array $a, array $b): int => strcmp(json_encode($a['ref'] ?? $a['key']), json_encode($b['ref'] ?? $b['key'])));
        $resolved = [];
        foreach ($jobs as $job) {
            $resolved[$job['map']][$job['key']] = $this->identity($job['kind'], $job['fields'], $job['ref'])->external_id;
        }
        foreach ($pending['lineup'] as $position => $key) {
            $concert['catalog']['artistIds'][$position] = $resolved['artists'][$key];
        }
        foreach ($pending['tourChoices'] ?? [] as $position => $key) {
            $concert['catalog']['tours'][$position]['catalogId'] = $resolved['tours'][$key];
        }
        foreach ($concert['setlist'] as &$row) {
            $refs = $pending['entries'][$row['id']] ?? [];
            if (isset($refs['song'])) {
                $row['songCatalogId'] = $resolved['songs'][$refs['song']];
            }
            if (isset($refs['artist'])) {
                $row['artistCatalogId'] = $resolved['artists'][$refs['artist']];
            }
        }

        return $concert;
    }

    private function identity(string $kind, array $fields, ?array $ref): Artist|Song|Tour
    {
        [$model, $reference, $foreignKey, $relation] = match ($kind) {
            'artist' => [Artist::class, ArtistProviderId::class, 'artist_id', 'artist'],
            'work' => [Song::class, SongProviderId::class, 'song_id', 'song'],
            'series' => [Tour::class, TourProviderId::class, 'tour_id', 'tour'],
        };
        if ($ref === null) {
            return $model::query()->create($fields);
        }
        $tuple = ['provider' => $ref['provider'], 'resource_type' => $ref['resourceType'], ($kind === 'series' ? 'external_provider_id' : 'provider_resource_id') => $ref['externalId']];
        $existing = $reference::query()->where($tuple)->first();
        if ($existing !== null) {
            return $existing->{$relation};
        }
        try {
            return DB::transaction(function () use ($model, $reference, $foreignKey, $tuple, $fields): Artist|Song|Tour {
                $parent = $model::query()->create($fields);
                $reference::query()->create([$foreignKey => $parent->id, ...$tuple]);

                return $parent;
            });
        } catch (UniqueConstraintViolationException $failure) {
            $winner = $reference::query()->where($tuple)->first();
            if ($winner === null) {
                throw $failure;
            }

            return $winner->{$relation};
        }
    }
}
