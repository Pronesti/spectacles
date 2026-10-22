<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ArtistProviderIdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['artist_id', 'provider', 'resource_type', 'provider_resource_id'])]
class ArtistProviderId extends Model
{
    /** @use HasFactory<ArtistProviderIdFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'artist_provider_ids';

    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
