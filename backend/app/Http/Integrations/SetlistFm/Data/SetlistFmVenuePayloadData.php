<?php

declare(strict_types=1);

namespace App\Http\Integrations\SetlistFm\Data;

use Spatie\LaravelData\Data;

class SetlistFmVenuePayloadData extends Data
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $name = null,
        public readonly ?string $url = null,
        public readonly ?SetlistFmCityPayloadData $city = null,
    ) {}
}
