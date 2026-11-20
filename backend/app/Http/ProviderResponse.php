<?php

namespace App\Http;

use App\Http\Data\ProviderPageData;
use Illuminate\Http\JsonResponse;

class ProviderResponse
{
    /**
     * Renders a provider read, or its unavailability, as an uncached JSON response.
     */
    public static function from(callable $fetch, bool $page = false): JsonResponse
    {
        try {
            $value = $fetch();
            $body = $page ? $value : ProviderPageData::of([$value], null, false);

            return response()->json($body)->header('Cache-Control', 'private, no-store');
        } catch (CatalogProviderUnavailable|MusicBrainzUnavailable $failure) {
            $response = response()->json(['message' => $failure->getMessage(), ...($failure instanceof CatalogProviderUnavailable && $failure->reason !== null ? ['reason' => $failure->reason] : [])], $failure->status)->header('Cache-Control', 'private, no-store');

            return $failure->status === 429 ? $response->header('Retry-After', (string) $failure->retryAfter) : $response;
        }
    }
}
