<?php

namespace App\Http;

use Illuminate\Support\Facades\Http;

class SetlistFmClient
{
    public function usesApi(): bool
    {
        return trim((string) config('services.setlistfm.api_key', '')) !== '';
    }

    public function api(string $path, array $query = []): array
    {
        if (! preg_match('~^(?:search/setlists|setlist/[a-zA-Z0-9_-]{1,128}|venue/[a-zA-Z0-9_-]{1,128})$~D', $path) || ! config('services.setlistfm.enabled') || ! $this->usesApi()) {
            throw new CatalogProviderUnavailable;
        }
        try {
            return app(ProviderReadGate::class)->apiRequest(function () use ($path, $query): array {
                $response = Http::acceptJson()->withHeaders(['x-api-key' => config('services.setlistfm.api_key'), 'Accept-Language' => 'en'])
                    ->withUserAgent(config('services.setlistfm.user_agent') ?: 'Spectacles/1.0')
                    ->connectTimeout(2)->timeout(5)->withoutRedirecting()->withOptions(['stream' => true])
                    ->get('https://api.setlist.fm/rest/1.0/'.$path, $query);
                if ($response->status() === 429) {
                    throw new CatalogProviderUnavailable(429, max(1, min(3600, (int) $response->header('Retry-After') ?: 2)));
                }
                if ($response->status() === 404 && $path === 'search/setlists') {
                    return ['setlist' => [], 'total' => 0, 'page' => $query['p'] ?? 1, 'itemsPerPage' => 20];
                }
                if ($response->status() !== 200 || (int) $response->header('Content-Length') > 2097152) {
                    throw new CatalogProviderUnavailable(reason: in_array($response->status(), [401, 403], true) ? 'setlistfm_api_auth' : null);
                }
                $stream = $response->toPsrResponse()->getBody();
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }
                $body = '';
                while (! $stream->eof() && strlen($body) <= 2097152) {
                    $body .= $stream->read(min(8192, 2097153 - strlen($body)));
                }
                if (strlen($body) > 2097152) {
                    throw new CatalogProviderUnavailable;
                }
                $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($decoded) || array_is_list($decoded)) {
                    throw new CatalogProviderUnavailable;
                }

                return $decoded;
            });
        } catch (CatalogProviderUnavailable $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw new CatalogProviderUnavailable;
        }
    }

    public function artistUrl(string $mbid): string
    {
        if (! config('services.setlistfm.enabled') || config('services.setlistfm.html_daily_limit', 0) < 1 || trim((string) config('services.setlistfm.user_agent')) === '') {
            throw new CatalogProviderUnavailable;
        }
        try {
            $result = app(MusicBrainzClient::class)->publicRead('musicbrainz:setlistfm-artist:v1:'.$mbid, 'artist/'.$mbid, ['inc' => 'url-rels', 'fmt' => 'json'], function (array $body) use ($mbid): array {
                if (($body['id'] ?? null) !== $mbid || ! is_array($body['relations'] ?? null) || ! array_is_list($body['relations']) || count($body['relations']) > 1000) {
                    throw new MusicBrainzUnavailable;
                }
                $urls = [];
                foreach ($body['relations'] as $relation) {
                    if (! is_array($relation)) {
                        throw new MusicBrainzUnavailable;
                    }
                    if (($relation['type'] ?? null) === 'setlistfm' && ($relation['target-type'] ?? null) === 'url') {
                        $url = app(SetlistFmUrl::class)->resolve($relation['url']['resource'] ?? '');
                        $urls[app(SetlistFmUrl::class)->id($url, 'artist')] = $url;
                    }
                }
                if (count($urls) !== 1) {
                    throw new MusicBrainzUnavailable;
                }

                return ['url' => array_values($urls)[0]];
            });

            return $result['url'];
        } catch (MusicBrainzUnavailable $failure) {
            throw new CatalogProviderUnavailable($failure->status, $failure->retryAfter);
        }
    }

    public function html(string $path, array $query = []): string
    {
        $url = app(SetlistFmUrl::class)->resolve($path);

        return $this->request($url, $query)['body'];
    }

    private function request(string $url, array $query): array
    {
        $settings = config('services.setlistfm', []);
        if (! ($settings['enabled'] ?? false) || trim($settings['user_agent'] ?? '') === '') {
            throw new CatalogProviderUnavailable;
        }
        try {
            $client = Http::withUserAgent($settings['user_agent'])->withHeaders(['Accept-Language' => 'en'])->connectTimeout(2)->timeout(5)->withoutRedirecting()->withOptions(['stream' => true]);
            $response = $client->get($url, $query);
            if ($response->status() === 429) {
                throw new CatalogProviderUnavailable(429, max(1, min(3600, (int) $response->header('Retry-After') ?: 2)));
            }
            if ($response->status() === 202) {
                throw new CatalogProviderUnavailable(reason: 'setlistfm_not_ready');
            }
            if ($response->status() !== 200 || (int) $response->header('Content-Length') > 2097152) {
                throw new CatalogProviderUnavailable;
            }
            $stream = $response->toPsrResponse()->getBody();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $body = '';
            while (! $stream->eof() && strlen($body) <= 2097152) {
                $body .= $stream->read(min(8192, 2097153 - strlen($body)));
            }
            if (strlen($body) > 2097152) {
                throw new CatalogProviderUnavailable;
            }

            return ['body' => $body, 'status' => $response->status()];
        } catch (CatalogProviderUnavailable $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw new CatalogProviderUnavailable;
        }
    }
}
