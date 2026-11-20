<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * The `{data, meta}` envelope every catalog provider endpoint returns.
 */
class ProviderPageData extends Data
{
    /**
     * @param  list<mixed>  $data
     */
    public function __construct(
        public array $data,
        public ProviderPageMetaData $meta,
    ) {}

    /**
     * @param  list<mixed>  $items
     */
    public static function of(array $items, ?string $nextCursor, bool $partial): self
    {
        return new self($items, new ProviderPageMetaData($nextCursor, $partial));
    }
}
