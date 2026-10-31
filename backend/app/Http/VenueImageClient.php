<?php

namespace App\Http;

use Illuminate\Support\Facades\Http;

class VenueImageClient
{
    public function __construct(private ProviderReadGate $gate) {}

    public function find(array $relations): ?array
    {
        if (! config('services.venue_images.enabled')) {
            return null;
        }
        try {
            $file = null;
            foreach ($relations as $relation) {
                $url = $relation['url']['resource'] ?? '';
                if (($relation['type'] ?? null) === 'wikidata' && preg_match('~^https://www\.wikidata\.org/wiki/(Q[1-9][0-9]*)$~D', $url, $match)) {
                    $id = $match[1];
                    $data = $this->gate->run('wikidata', $id, function () use ($id): array {
                        $body = $this->json('https://www.wikidata.org/wiki/Special:EntityData/'.$id.'.json');
                        $claims = $body['entities'][$id]['claims']['P18'] ?? [];
                        usort($claims, fn ($a, $b) => (($b['rank'] ?? '') === 'preferred') <=> (($a['rank'] ?? '') === 'preferred'));
                        foreach ($claims as $claim) {
                            if (($claim['rank'] ?? '') !== 'deprecated' && is_string($claim['mainsnak']['datavalue']['value'] ?? null)) {
                                return ['file' => CatalogProviderData::text($claim['mainsnak']['datavalue']['value'], 255)];
                            }
                        }

                        return ['file' => null];
                    });
                    $file = $data['file'];
                    break;
                }
                if (($relation['type'] ?? null) === 'image' && preg_match('~^https://commons\.wikimedia\.org/wiki/File:([^?#]+)$~D', $url, $match)) {
                    $file = rawurldecode($match[1]);
                    break;
                }
            }
            if ($file === null || mb_strlen($file) > 255) {
                return null;
            }
            $result = $this->gate->run('commons', $file, function () use ($file): array {
                $body = $this->json('https://commons.wikimedia.org/w/api.php', ['action' => 'query', 'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata', 'iiurlwidth' => 800, 'titles' => 'File:'.$file, 'format' => 'json']);
                $pages = $body['query']['pages'] ?? [];
                if (count($pages) !== 1) {
                    return ['image' => null];
                }
                $raw = array_values($pages)[0]['imageinfo'][0] ?? [];
                $metadata = $raw['extmetadata'] ?? [];
                $value = ['url' => $raw['url'] ?? null, 'thumbnailUrl' => $raw['thumburl'] ?? null, 'sourceUrl' => $raw['descriptionurl'] ?? null, 'credit' => trim(html_entity_decode(strip_tags($metadata['Artist']['value'] ?? ''), ENT_QUOTES | ENT_HTML5)), 'license' => trim(strip_tags($metadata['LicenseShortName']['value'] ?? '')), 'licenseUrl' => $metadata['LicenseUrl']['value'] ?? null];

                return ['image' => CatalogProviderData::image($value)];
            });

            return $result['image'];
        } catch (\Throwable $failure) {
            return null;
        }
    }

    private function json(string $url, array $query = []): array
    {
        $agent = trim((string) config('services.musicbrainz.user_agent'));
        if ($agent === '') {
            throw new CatalogProviderUnavailable;
        }
        $response = Http::acceptJson()->withUserAgent($agent)->connectTimeout(2)->timeout(5)->withoutRedirecting()->withOptions(['stream' => true])->get($url, $query);
        if (! $response->successful() || (int) $response->header('Content-Length') > 2097152) {
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
        $result = strlen($body) <= 2097152 ? json_decode($body, true) : null;
        if (! is_array($result)) {
            throw new CatalogProviderUnavailable;
        }

        return $result;
    }
}
