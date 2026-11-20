<?php

namespace App\Http;

use App\Http\Data\SetlistFmSearchQueryData;
use App\Http\Integrations\ProviderConnector;
use App\Http\Integrations\SetlistFm\Data\SetlistFmSearchPayloadData;
use App\Http\Integrations\SetlistFm\Requests\GetSetlistFmApiResource;
use App\Http\Integrations\SetlistFm\SetlistFmApiConnector;
use Spatie\LaravelData\Data;

class SetlistFmClient
{
    public function usesApi(): bool
    {
        return trim((string) config('services.setlistfm.api_key', '')) !== '';
    }

    /**
     * Reads one setlist.fm API document as its payload Data class.
     *
     * @template TPayload of Data
     *
     * @param  class-string<TPayload>  $payloadClass
     * @return TPayload
     */
    public function api(string $path, string $payloadClass, ?SetlistFmSearchQueryData $query = null): Data
    {
        if (! preg_match('~^(?:search/setlists|setlist/[a-zA-Z0-9_-]{1,128}|venue/[a-zA-Z0-9_-]{1,128})$~D', $path) || ! config('services.setlistfm.enabled') || ! $this->usesApi()) {
            throw new CatalogProviderUnavailable;
        }
        try {
            return app(ProviderReadGate::class)->apiRequest(function () use ($path, $payloadClass, $query): Data {
                $connector = new SetlistFmApiConnector(config('services.setlistfm.user_agent') ?: 'Spectacles/1.0', (string) config('services.setlistfm.api_key'));
                $response = $connector->send(new GetSetlistFmApiResource($path, $query));
                if ($response->status() === 429) {
                    throw new CatalogProviderUnavailable(429, ProviderConnector::retryAfter($response, 3600));
                }
                if ($response->status() === 404 && $path === 'search/setlists') {
                    return new SetlistFmSearchPayloadData(setlist: [], total: 0, page: $query->p ?? 1, itemsPerPage: 20);
                }
                if ($response->status() !== 200 || ProviderConnector::declaresOversizedBody($response)) {
                    throw new CatalogProviderUnavailable(reason: in_array($response->status(), [401, 403], true) ? 'setlistfm_api_auth' : null);
                }
                $body = ProviderConnector::boundedBody($response);
                if ($body === null) {
                    throw new CatalogProviderUnavailable;
                }
                $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($decoded) || array_is_list($decoded)) {
                    throw new CatalogProviderUnavailable;
                }

                return UntrustedPayload::parse($payloadClass, $decoded, fn (): CatalogProviderUnavailable => new CatalogProviderUnavailable);
            });
        } catch (CatalogProviderUnavailable $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw new CatalogProviderUnavailable;
        }
    }
}
