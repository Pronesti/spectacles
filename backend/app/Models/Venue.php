<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'street', 'house_number', 'postal_code', 'city', 'region', 'country_code', 'lat', 'lng', 'timezone', 'address', 'aliases', 'image'])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'venues';

    protected $attributes = ['aliases' => '[]'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['lat' => 'decimal:7', 'lng' => 'decimal:7', 'aliases' => 'array', 'image' => 'array'];
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<VenueProviderId, $this> */
    public function providerIds(): HasMany
    {
        return $this->hasMany(VenueProviderId::class);
    }
}
