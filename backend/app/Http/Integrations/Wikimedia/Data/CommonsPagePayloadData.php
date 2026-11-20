<?php

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CommonsPagePayloadData extends Data
{
    /**
     * @param  list<CommonsImageInfoPayloadData>|null  $imageinfo
     */
    public function __construct(
        #[DataCollectionOf(CommonsImageInfoPayloadData::class)]
        public readonly ?array $imageinfo = null,
    ) {}
}
