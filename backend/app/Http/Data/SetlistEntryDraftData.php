<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A submitted setlist entry; catalog ids are omitted when the entry has none.
 */
class SetlistEntryDraftData extends Data
{
    use CopiesWithChanges;

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $artist,
        public readonly bool $tape,
        public readonly Optional|string $songCatalogId = new Optional,
        public readonly Optional|string $artistCatalogId = new Optional,
    ) {}
}
