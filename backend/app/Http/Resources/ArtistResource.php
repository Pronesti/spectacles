<?php

namespace App\Http\Resources;

use App\Models\Artist;

/** @mixin Artist */
class ArtistResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'name',
        'type',
        'disambiguation',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'eventLinks' => EventArtistResource::class,
        'tourLinks' => TourArtistResource::class,
        'entryLinks' => SetlistEntryArtistResource::class,
        'providerIds' => ArtistProviderIdResource::class,
    ];
}
