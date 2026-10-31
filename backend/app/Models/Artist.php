<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'disambiguation'])]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'artists';

    /** @return HasMany<EventArtist, $this> */
    public function eventLinks(): HasMany
    {
        return $this->hasMany(EventArtist::class);
    }

    /** @return HasMany<TourArtist, $this> */
    public function tourLinks(): HasMany
    {
        return $this->hasMany(TourArtist::class);
    }

    /** @return HasMany<SetlistEntryArtist, $this> */
    public function entryLinks(): HasMany
    {
        return $this->hasMany(SetlistEntryArtist::class);
    }

    /** @return HasMany<ArtistProviderId, $this> */
    public function providerIds(): HasMany
    {
        return $this->hasMany(ArtistProviderId::class);
    }
}
