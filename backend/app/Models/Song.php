<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\SongFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'disambiguation'])]
class Song extends Model
{
    /** @use HasFactory<SongFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'songs';

    /** @return HasMany<SetlistEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(SetlistEntry::class);
    }

    /** @return HasMany<SongProviderId, $this> */
    public function providerIds(): HasMany
    {
        return $this->hasMany(SongProviderId::class);
    }

    /** @return HasMany<SongLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(SongLink::class);
    }
}
