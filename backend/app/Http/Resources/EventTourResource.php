<?php

namespace App\Http\Resources;

use App\Models\EventTour;

/** @mixin EventTour */
class EventTourResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'event' => EventResource::class,
        'tour' => TourResource::class,
    ];
}
