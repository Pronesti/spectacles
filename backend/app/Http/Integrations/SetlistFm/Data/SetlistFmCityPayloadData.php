<?php

namespace App\Http\Integrations\SetlistFm\Data;

use Spatie\LaravelData\Data;

class SetlistFmCityPayloadData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $state = null,
        public readonly ?SetlistFmCountryPayloadData $country = null,
        public readonly ?SetlistFmCoordinatesPayloadData $coords = null,
    ) {}
}
