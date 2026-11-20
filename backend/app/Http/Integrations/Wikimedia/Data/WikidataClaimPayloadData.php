<?php

declare(strict_types=1);

namespace App\Http\Integrations\Wikimedia\Data;

use Spatie\LaravelData\Data;

class WikidataClaimPayloadData extends Data
{
    public function __construct(
        public readonly ?string $rank = null,
        public readonly ?WikidataSnakPayloadData $mainsnak = null,
    ) {}

    /**
     * The claim's value when it is a plain string, such as a Commons file name.
     */
    public function stringValue(): ?string
    {
        $value = $this->mainsnak?->datavalue?->value;

        return is_string($value) ? $value : null;
    }
}
