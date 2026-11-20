<?php

namespace App\Http;

use App\Http\Data\ConcertDraftData;
use App\Http\Data\PendingArtistData;
use App\Http\Data\PendingCatalogData;
use App\Http\Data\PendingIdentityData;
use App\Http\Data\PendingSongData;
use App\Http\Data\PendingTourData;
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
        foreach (PendingCatalogData::DRAFT_KINDS as $map => $kind) {
            foreach ($pending->drafts($map) as $key => $draft) {
                $path = 'pendingCatalog.'.$map.'.'.$key.'.selection';
                $ref = $draft->selection === null ? null : ($kind === 'series'
                    ? app(CatalogProviderSelection::class)->tour($draft->selection, $path)
                    : new ProviderRefData('musicbrainz', $kind, app(MusicBrainzSelection::class)->read($draft->selection, $kind, $path)));
                $jobs[] = new PendingIdentityData($map, $key, $kind, $ref, $draft);
            }
        }
        usort($jobs, fn (PendingIdentityData $a, PendingIdentityData $b): int => strcmp($a->sortKey(), $b->sortKey()));
        $resolved = new ResolvedCatalogIds;
        foreach ($jobs as $job) {
            $resolved->record($job, $this->identity($job->kind, $job->draft, $job->ref)->external_id);
        }
        $artistIds = $concert->catalog->artistIds;
        foreach ($pending->lineup as $position => $key) {
            $artistIds[$position] = $resolved->artist($key);
        }
        $tours = $concert->catalog->tours;
        foreach ($pending->pendingTourChoices() as $position => $key) {
            $tours[$position] = $tours[$position]->copyWith(catalogId: $resolved->tour($key));
        }
        $setlist = array_map(function (SetlistEntryDraftData $row) use ($pending, $resolved): SetlistEntryDraftData {
            $refs = $pending->entries[$row->id] ?? null;
            if ($refs === null) {
                return $row;
            }

            return $row->copyWith(
                songCatalogId: is_string($refs->song) ? $resolved->song($refs->song) : $row->songCatalogId,
                artistCatalogId: is_string($refs->artist) ? $resolved->artist($refs->artist) : $row->artistCatalogId,
            );
        }, $concert->setlist);

        return $concert->copyWith(catalog: $concert->catalog->copyWith(artistIds: $artistIds, tours: $tours), setlist: $setlist);
    }

    /**
     * @param  'artist'|'work'|'series'  $kind
     */
    private function identity(string $kind, PendingArtistData|PendingSongData|PendingTourData $draft, ?ProviderRefData $ref): Artist|Song|Tour
    {
        [$model, $reference, $foreignKey] = match ($kind) {
            'artist' => [Artist::class, ArtistProviderId::class, 'artist_id'],
            'work' => [Song::class, SongProviderId::class, 'song_id'],
            'series' => [Tour::class, TourProviderId::class, 'tour_id'],
        };
        if ($ref === null) {
            return $model::query()->create($draft->attributes());
        }
        $tuple = $ref->tuple($kind === 'series' ? 'external_provider_id' : 'provider_resource_id');
        $existing = $reference::query()->where($tuple)->first();
        if ($existing !== null) {
            return $this->parent($existing);
        }
        try {
            return DB::transaction(function () use ($model, $reference, $foreignKey, $tuple, $draft): Artist|Song|Tour {
                $parent = $model::query()->create($draft->attributes());
                $reference::query()->create([$foreignKey => $parent->id, ...$tuple]);

                return $parent;
            });
        } catch (UniqueConstraintViolationException $failure) {
            $winner = $reference::query()->where($tuple)->first();
            if ($winner === null) {
                throw $failure;
            }

            return $this->parent($winner);
        }
    }

    /**
     * The catalog record a provider identity belongs to.
     */
    private function parent(ArtistProviderId|SongProviderId|TourProviderId $reference): Artist|Song|Tour
    {
        if ($reference instanceof ArtistProviderId) {
            return $reference->artist;
        }

        return $reference instanceof SongProviderId ? $reference->song : $reference->tour;
    }
}
