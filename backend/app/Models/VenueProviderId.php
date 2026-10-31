<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\VenueProviderIdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venue_id', 'provider', 'resource_type', 'provider_resource_id', 'source_url'])]
class VenueProviderId extends Model
{
    /** @use HasFactory<VenueProviderIdFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'venue_provider_ids';

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
