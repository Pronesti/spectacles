<?php

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CommonsQueryPayloadData extends Data
{
    /**
     * @param  array<string, CommonsPagePayloadData>|null  $pages
     */
    public function __construct(
        #[DataCollectionOf(CommonsPagePayloadData::class)]
        public readonly ?array $pages = null,
    ) {}
}
