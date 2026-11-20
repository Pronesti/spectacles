<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;

/**
 * The Commons file a Wikidata entity names as its image, if any.
 */
class WikidataImageFileData extends Data
{
    public function __construct(
        public readonly ?string $file,
    ) {}
}
