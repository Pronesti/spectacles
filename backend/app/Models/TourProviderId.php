<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\TourProviderIdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tour_id', 'provider', 'resource_type', 'provider_resource_id'])]
class TourProviderId extends Model
{
    /** @use HasFactory<TourProviderIdFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'tour_provider_ids';

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
