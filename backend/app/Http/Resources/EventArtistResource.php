<?php

namespace App\Http\Resources;

use App\Models\EventArtist;

/** @mixin EventArtist */
class EventArtistResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'position',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'event' => EventResource::class,
        'artist' => ArtistResource::class,
    ];
}
