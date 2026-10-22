<?php

namespace App\Http\Resources;

use App\Models\Tour;

/** @mixin Tour */
class TourResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'name',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'artistLinks' => TourArtistResource::class,
        'eventLinks' => EventTourResource::class,
        'providerIds' => TourProviderIdResource::class,
    ];
}
