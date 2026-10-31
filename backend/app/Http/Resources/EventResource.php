<?php

namespace App\Http\Resources;

use App\Models\Event;

/** @mixin Event */
class EventResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'title',
        'type',
        'year',
        'month',
        'day',
        'local_start_time',
        'timezone',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'venue' => VenueResource::class,
        'artistLinks' => EventArtistResource::class,
        'tourLinks' => EventTourResource::class,
        'attendances' => AttendanceResource::class,
        'providerReferences' => EventProviderReferenceResource::class,
    ];
}
