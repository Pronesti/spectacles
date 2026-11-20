<?php

namespace App\Http\Controllers;

use App\Http\Data\MusicBrainzLocalMatchData;
use App\Http\Data\MusicBrainzSearchData;
use App\Http\Data\MusicBrainzSearchResultData;
use App\Http\Data\MusicBrainzSearchRowData;
use App\Http\Data\MusicBrainzTourScopeData;
use App\Http\MusicBrainzClient;
use App\Http\MusicBrainzSelection;
use App\Http\MusicBrainzUnavailable;
use App\Http\Requests\MusicBrainzSearchRequest;
use App\Models\ArtistProviderId;
use App\Models\SongProviderId;
use App\Models\TourProviderId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class MusicBrainzController extends Controller
{
    public function artists(MusicBrainzSearchRequest $request): JsonResponse
    {
        return $this->search($request, 'artist');
    }

    public function works(MusicBrainzSearchRequest $request): JsonResponse
    {
        return $this->search($request, 'work');
    }

    public function tours(MusicBrainzSearchRequest $request): JsonResponse
    {
        return $this->search($request, 'series');
    }

    /**
     * @param  'artist'|'work'|'series'  $kind
     */
    private function search(MusicBrainzSearchRequest $request, string $kind): JsonResponse
    {
        $search = MusicBrainzSearchData::from($request->validated());
        try {
            $scopeAvailable = true;
            if ($kind === 'series') {
                $localMbids = ArtistProviderId::query()->where('provider', 'musicbrainz')->where('resource_type', 'artist')
                    ->whereHas('artist', fn ($artist) => $artist->whereIn('external_id', array_map(strtolower(...), $search->artistUuids)))
                    ->get(['provider_resource_id'])->map(fn (ArtistProviderId $id): string => $id->provider_resource_id)->all();
                $scope = MusicBrainzTourScopeData::normalized($search->q, [...$localMbids, ...$search->artistMbids]);
                $scopeAvailable = $scope->artistMbids !== [];
                $rows = app(MusicBrainzClient::class)->tours($scope);
            } else {
                $rows = app(MusicBrainzClient::class)->search($kind, $search->q);
            }
            $ids = array_map(fn (MusicBrainzSearchRowData $row): string => $row->id, $rows);
            $matches = match ($kind) {
                'artist' => $this->localMatches(ArtistProviderId::query(), 'artist', $kind, 'provider_resource_id', $ids),
                'work' => $this->localMatches(SongProviderId::query(), 'song', $kind, 'provider_resource_id', $ids),
                'series' => $this->localMatches(TourProviderId::query(), 'tour', $kind, 'external_provider_id', $ids),
            };
            $results = array_map(function (MusicBrainzSearchRowData $row) use ($matches, $kind): MusicBrainzSearchResultData {
                $match = $matches->get($row->id);
                $local = match (true) {
                    $match instanceof ArtistProviderId => new MusicBrainzLocalMatchData($match->artist->external_id, $match->artist->name, $match->artist->type, $match->artist->disambiguation),
                    $match instanceof SongProviderId => new MusicBrainzLocalMatchData($match->song->external_id, $match->song->title, null, $match->song->disambiguation),
                    $match instanceof TourProviderId => new MusicBrainzLocalMatchData($match->tour->external_id, $match->tour->name, null, null),
                    default => null,
                };

                return MusicBrainzSearchResultData::fromRow($row, $local, app(MusicBrainzSelection::class)->issue($kind, $row->id));
            }, $rows);

            return response()->json(['data' => $results, ...($kind === 'series' ? ['meta' => ['scope_available' => $scopeAvailable]] : [])])->header('Cache-Control', 'private, no-store');
        } catch (MusicBrainzUnavailable $failure) {
            $response = response()->json(['message' => $failure->getMessage()], $failure->status)->header('Cache-Control', 'private, no-store');

            return $failure->status === 429 ? $response->header('Retry-After', (string) $failure->retryAfter) : $response;
        }
    }

    /**
     * The saved MusicBrainz identities among these ids, keyed by id, with their catalog record loaded.
     *
     * @template TReference of Model
     *
     * @param  Builder<TReference>  $references
     * @param  list<string>  $ids
     * @return Collection<array-key, TReference>
     */
    private function localMatches(Builder $references, string $relation, string $kind, string $column, array $ids): Collection
    {
        return $references->with($relation)->where('provider', 'musicbrainz')->where('resource_type', $kind)->whereIn($column, $ids)->get()->keyBy($column);
    }
}
