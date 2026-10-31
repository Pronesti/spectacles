<?php

namespace App\Http\Resources;

use App\Models\TourArtist;

/** @mixin TourArtist */
class TourArtistResource extends PublicJsonApiResource
{
    /** @var list<string> */
    public $attributes = [
        'created_at',
        'updated_at',
    ];

    /** @var array<string, class-string<PublicJsonApiResource>> */
    public $relationships = [
        'tour' => TourResource::class,
        'artist' => ArtistResource::class,
    ];
}
