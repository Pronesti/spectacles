<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Tour extends Model
{
    /** @use HasFactory<TourFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'tours';

    /** @return HasMany<TourArtist, $this> */
    public function artistLinks(): HasMany
    {
        return $this->hasMany(TourArtist::class);
    }

    /** @return HasMany<EventTour, $this> */
    public function eventLinks(): HasMany
    {
        return $this->hasMany(EventTour::class);
    }

    /** @return HasMany<TourProviderId, $this> */
    public function providerIds(): HasMany
    {
        return $this->hasMany(TourProviderId::class);
    }
}
