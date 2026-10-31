<?php

namespace App\Http\Controllers;

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
        try {
            $scopeAvailable = true;
            if ($kind === 'series') {
                $ids = ArtistProviderId::query()->where('provider', 'musicbrainz')->where('resource_type', 'artist')
                    ->whereHas('artist', fn ($artist) => $artist->whereIn('external_id', array_map('strtolower', $request->validated('artist_uuids', []))))
                    ->pluck('provider_resource_id')->all();
                $ids = array_values(array_unique(array_map('strtolower', [...$ids, ...$request->validated('artist_mbids', [])])));
                $scopeAvailable = $ids !== [];
                $rows = app(MusicBrainzClient::class)->tours($request->validated('q'), $ids);
            } else {
                $rows = app(MusicBrainzClient::class)->search($kind, $request->validated('q'));
            }
            [$reference, $relation] = match ($kind) {
                'artist' => [ArtistProviderId::class, 'artist'],
                'work' => [SongProviderId::class, 'song'],
                'series' => [TourProviderId::class, 'tour'],
            };
            $matches = $reference::query()->with($relation)->where('provider', 'musicbrainz')->where('resource_type', $kind)->whereIn($kind === 'series' ? 'external_provider_id' : 'provider_resource_id', array_column($rows, 'id'))->get()->keyBy($kind === 'series' ? 'external_provider_id' : 'provider_resource_id');
            foreach ($rows as &$row) {
                $model = $matches->get($row['id'])?->{$relation};
                $row['local'] = $model === null ? null : ['id' => $model->external_id, 'label' => $kind === 'work' ? $model->title : $model->name, 'artistType' => $kind === 'artist' ? $model->type : null, 'disambiguation' => $kind === 'series' ? null : $model->disambiguation];
                $row['selection'] = app(MusicBrainzSelection::class)->issue($kind, $row['id']);
            }

            return response()->json(['data' => $rows, ...($kind === 'series' ? ['meta' => ['scope_available' => $scopeAvailable]] : [])])->header('Cache-Control', 'private, no-store');
        } catch (MusicBrainzUnavailable $failure) {
            $response = response()->json(['message' => $failure->getMessage()], $failure->status)->header('Cache-Control', 'private, no-store');

            return $failure->status === 429 ? $response->header('Retry-After', (string) $failure->retryAfter) : $response;
        }
    }
}
