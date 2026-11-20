<?php

namespace App\Http;

use App\Http\Data\CommonsImageData;
use App\Http\Data\VenueImageData;
use App\Http\Data\VenueImageInputData;
use App\Http\Data\WikidataImageFileData;
use App\Http\Integrations\MusicBrainz\Data\MusicBrainzRelationPayloadData;
use App\Http\Integrations\ProviderConnector;
use App\Http\Integrations\Wikimedia\CommonsConnector;
use App\Http\Integrations\Wikimedia\Data\CommonsImageInfoResponsePayloadData;
use App\Http\Integrations\Wikimedia\Data\WikidataClaimPayloadData;
use App\Http\Integrations\Wikimedia\Data\WikidataEntitiesPayloadData;
use App\Http\Integrations\Wikimedia\Requests\GetCommonsImageInfo;
use App\Http\Integrations\Wikimedia\Requests\GetWikidataEntity;
use App\Http\Integrations\Wikimedia\WikidataConnector;
use Illuminate\Support\Facades\Config;
use Saloon\Http\Request;
use Spatie\LaravelData\Data;

class VenueImageClient
{
    public function __construct(private readonly ProviderReadGate $gate) {}

    /**
     * @param  array<MusicBrainzRelationPayloadData>  $relations
     */
    public function find(array $relations): ?VenueImageData
    {
        if (! config('services.venue_images.enabled')) {
            return null;
        }
        try {
            $file = null;
            foreach ($relations as $relation) {
                $url = $relation->url->resource ?? '';
                if ($relation->type === 'wikidata' && preg_match('~^https://www\.wikidata\.org/wiki/(Q[1-9][0-9]*)$~D', $url, $match)) {
                    $id = $match[1];
                    $data = $this->gate->run('wikidata', $id, WikidataImageFileData::class, function () use ($id): WikidataImageFileData {
                        $entities = $this->document(WikidataConnector::class, new GetWikidataEntity($id), WikidataEntitiesPayloadData::class);
                        $claims = ($entities->entities[$id] ?? null)?->claims->images ?? [];
                        usort($claims, fn (WikidataClaimPayloadData $a, WikidataClaimPayloadData $b): int => ($b->rank === 'preferred') <=> ($a->rank === 'preferred'));
                        foreach ($claims as $claim) {
                            if ($claim->rank !== 'deprecated' && $claim->stringValue() !== null) {
                                return new WikidataImageFileData(CatalogProviderData::text($claim->stringValue(), 255));
                            }
                        }

                        return new WikidataImageFileData(null);
                    });
                    $file = $data->file;
                    break;
                }
                if ($relation->type === 'image' && preg_match('~^https://commons\.wikimedia\.org/wiki/File:([^?#]+)$~D', $url, $match)) {
                    $file = rawurldecode($match[1]);
                    break;
                }
            }
            if ($file === null || mb_strlen($file) > 255) {
                return null;
            }
            $result = $this->gate->run('commons', $file, CommonsImageData::class, function () use ($file): CommonsImageData {
                $pages = $this->document(CommonsConnector::class, new GetCommonsImageInfo($file), CommonsImageInfoResponsePayloadData::class)->query->pages ?? [];
                if (count($pages) !== 1) {
                    return new CommonsImageData(null);
                }
                $info = array_values($pages)[0]->imageinfo[0] ?? null;
                $metadata = $info?->extmetadata;

                return new CommonsImageData(CatalogProviderData::image(new VenueImageInputData(
                    url: $info?->url,
                    thumbnailUrl: $info?->thumburl,
                    sourceUrl: $info?->descriptionurl,
                    credit: trim(html_entity_decode(strip_tags($metadata?->artist->value ?? ''), ENT_QUOTES | ENT_HTML5)),
                    license: trim(strip_tags($metadata?->licenseShortName->value ?? '')),
                    licenseUrl: $metadata?->licenseUrl?->value,
                )));
            });

            return $result->image;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @template TPayload of Data
     *
     * @param  class-string<ProviderConnector>  $connector
     * @param  class-string<TPayload>  $payloadClass
     * @return TPayload
     */
    private function document(string $connector, Request $request, string $payloadClass): Data
    {
        $agent = trim(Config::string('services.musicbrainz.user_agent', ''));
        if ($agent === '') {
            throw new CatalogProviderUnavailable;
        }
        $response = (new $connector($agent))->send($request);
        if (! $response->successful() || ProviderConnector::declaresOversizedBody($response)) {
            throw new CatalogProviderUnavailable;
        }
        $body = ProviderConnector::boundedBody($response);

        return UntrustedPayload::parse($payloadClass, $body === null ? null : json_decode($body, true), fn (): CatalogProviderUnavailable => new CatalogProviderUnavailable);
    }
}
