<?php

namespace App\Http\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * One page of setlist.fm API search results.
 */
class SetlistFmSearchResultData extends Data
{
    /**
     * @param  list<ProviderPerformanceData>  $items
     */
    public function __construct(
        #[DataCollectionOf(ProviderPerformanceData::class)]
        public readonly array $items,
        public readonly bool $more,
    ) {}
}
