<?php

declare(strict_types=1);

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * A `Special:EntityData` response, keyed by entity ID.
 */
class WikidataEntitiesPayloadData extends Data
{
    /**
     * @param  array<string, WikidataEntityPayloadData>|null  $entities
     */
    public function __construct(
        #[DataCollectionOf(WikidataEntityPayloadData::class)]
        public readonly ?array $entities = null,
    ) {}
}
