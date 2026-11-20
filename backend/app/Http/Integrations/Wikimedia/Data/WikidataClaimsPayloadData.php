<?php

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

/**
 * The entity claims this app reads: P18, the image property.
 */
class WikidataClaimsPayloadData extends Data
{
    /**
     * @param  list<WikidataClaimPayloadData>|null  $images
     */
    public function __construct(
        #[MapInputName('P18')]
        #[DataCollectionOf(WikidataClaimPayloadData::class)]
        public readonly ?array $images = null,
    ) {}
}
