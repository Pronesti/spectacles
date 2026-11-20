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

    private function search(MusicBrainzSearchRequest $request, string $kind): JsonResponse
    {
        $search = MusicBrainzSearchData::from($request->validated());
        try {
            $scopeAvailable = true;
            if ($kind === 'series') {
                $localMbids = ArtistProviderId::query()->where('provider', 'musicbrainz')->where('resource_type', 'artist')
                    ->whereHas('artist', fn ($artist) => $artist->whereIn('external_id', array_map('strtolower', $search->artistUuids)))
                    ->pluck('provider_resource_id')->all();
                $scope = MusicBrainzTourScopeData::normalized($search->q, [...$localMbids, ...$search->artistMbids]);
                $scopeAvailable = $scope->artistMbids !== [];
                $rows = app(MusicBrainzClient::class)->tours($scope);
            } else {
                $rows = app(MusicBrainzClient::class)->search($kind, $search->q);
            }
            [$reference, $relation] = match ($kind) {
                'artist' => [ArtistProviderId::class, 'artist'],
                'work' => [SongProviderId::class, 'song'],
                'series' => [TourProviderId::class, 'tour'],
            };
            $matches = $reference::query()->with($relation)->where('provider', 'musicbrainz')->where('resource_type', $kind)->whereIn($kind === 'series' ? 'external_provider_id' : 'provider_resource_id', array_map(fn (MusicBrainzSearchRowData $row): string => $row->id, $rows))->get()->keyBy($kind === 'series' ? 'external_provider_id' : 'provider_resource_id');
            $results = array_map(function (MusicBrainzSearchRowData $row) use ($matches, $relation, $kind): MusicBrainzSearchResultData {
                $model = $matches->get($row->id)?->{$relation};
                $local = $model === null ? null : new MusicBrainzLocalMatchData($model->external_id, $kind === 'work' ? $model->title : $model->name, $kind === 'artist' ? $model->type : null, $kind === 'series' ? null : $model->disambiguation);

                return MusicBrainzSearchResultData::fromRow($row, $local, app(MusicBrainzSelection::class)->issue($kind, $row->id));
            }, $rows);

            return response()->json(['data' => $results, ...($kind === 'series' ? ['meta' => ['scope_available' => $scopeAvailable]] : [])])->header('Cache-Control', 'private, no-store');
        } catch (MusicBrainzUnavailable $failure) {
            $response = response()->json(['message' => $failure->getMessage()], $failure->status)->header('Cache-Control', 'private, no-store');

            return $failure->status === 429 ? $response->header('Retry-After', (string) $failure->retryAfter) : $response;
        }
    }
}
