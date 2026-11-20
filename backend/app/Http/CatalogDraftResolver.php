<?php

namespace App\Http;

use App\Http\Data\ConcertDraftData;
use App\Http\Data\PendingCatalogData;
use App\Http\Data\PendingIdentityData;
use App\Http\Data\ProviderRefData;
use App\Http\Data\SetlistEntryDraftData;
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
    public function resolve(ConcertDraftData $concert, ?PendingCatalogData $pending): ConcertDraftData
    {
        if ($pending === null) {
            return $concert;
        }
        $jobs = [];
        foreach (['artists' => ['artist', $pending->artists], 'songs' => ['work', $pending->songs], 'tours' => ['series', $pending->pendingTours()]] as $map => [$kind, $drafts]) {
            foreach ($drafts as $key => $draft) {
                $path = 'pendingCatalog.'.$map.'.'.$key.'.selection';
                $ref = $draft->selection === null ? null : ($kind === 'series'
                    ? app(CatalogProviderSelection::class)->tour($draft->selection, $path)
                    : new ProviderRefData('musicbrainz', $kind, app(MusicBrainzSelection::class)->read($draft->selection, $kind, $path)));
                $jobs[] = new PendingIdentityData($map, $key, $kind, $ref, $draft);
            }
        }
        usort($jobs, fn (PendingIdentityData $a, PendingIdentityData $b): int => strcmp($a->sortKey(), $b->sortKey()));
        $resolved = [];
        foreach ($jobs as $job) {
            $resolved[$job->map][$job->key] = $this->identity($job->kind, $job->draft->attributes(), $job->ref)->external_id;
        }
        $artistIds = $concert->catalog->artistIds;
        foreach ($pending->lineup as $position => $key) {
            $artistIds[$position] = $resolved['artists'][$key];
        }
        $tours = $concert->catalog->tours;
        foreach ($pending->pendingTourChoices() as $position => $key) {
            $tours[$position] = $tours[$position]->copyWith(catalogId: $resolved['tours'][$key]);
        }
        $setlist = array_map(function (SetlistEntryDraftData $row) use ($pending, $resolved): SetlistEntryDraftData {
            $refs = $pending->entries[$row->id] ?? null;
            if ($refs === null) {
                return $row;
            }

            return $row->copyWith(
                songCatalogId: is_string($refs->song) ? $resolved['songs'][$refs->song] : $row->songCatalogId,
                artistCatalogId: is_string($refs->artist) ? $resolved['artists'][$refs->artist] : $row->artistCatalogId,
            );
        }, $concert->setlist);

        return $concert->copyWith(catalog: $concert->catalog->copyWith(artistIds: $artistIds, tours: $tours), setlist: $setlist);
    }

    /**
     * @param  array<string, string|null>  $attributes
     */
    private function identity(string $kind, array $attributes, ?ProviderRefData $ref): Artist|Song|Tour
    {
        [$model, $reference, $foreignKey, $relation] = match ($kind) {
            'artist' => [Artist::class, ArtistProviderId::class, 'artist_id', 'artist'],
            'work' => [Song::class, SongProviderId::class, 'song_id', 'song'],
            'series' => [Tour::class, TourProviderId::class, 'tour_id', 'tour'],
        };
        if ($ref === null) {
            return $model::query()->create($attributes);
        }
        $tuple = $ref->tuple($kind === 'series' ? 'external_provider_id' : 'provider_resource_id');
        $existing = $reference::query()->where($tuple)->first();
        if ($existing !== null) {
            return $existing->{$relation};
        }
        try {
            return DB::transaction(function () use ($model, $reference, $foreignKey, $tuple, $attributes): Artist|Song|Tour {
                $parent = $model::query()->create($attributes);
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
