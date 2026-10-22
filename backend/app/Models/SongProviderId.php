<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\SongProviderIdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['song_id', 'provider', 'resource_type', 'provider_resource_id'])]
class SongProviderId extends Model
{
    /** @use HasFactory<SongProviderIdFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'song_provider_ids';

    /** @return BelongsTo<Song, $this> */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
