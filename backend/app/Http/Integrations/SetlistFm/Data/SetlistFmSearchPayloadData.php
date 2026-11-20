<?php

namespace App\Http\Integrations\SetlistFm\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * A `search/setlists` response page.
 */
class SetlistFmSearchPayloadData extends Data
{
    /**
     * @param  list<SetlistFmSetlistPayloadData>|null  $setlist
     */
    public function __construct(
        #[DataCollectionOf(SetlistFmSetlistPayloadData::class)]
        public readonly ?array $setlist = null,
        public readonly ?int $total = null,
        public readonly ?int $page = null,
        public readonly ?int $itemsPerPage = null,
    ) {}
}
