<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name'])]
class Tour extends Model
{
    /** @use HasFactory<TourFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'tours';

    protected static function booted(): void
    {
        static::saving(function (Tour $tour): void {
            $tour->search_name = mb_strtolower(Str::ascii($tour->name));
        });
    }

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
