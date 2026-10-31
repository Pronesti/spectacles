<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\TourArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tour_id', 'artist_id'])]
class TourArtist extends Model
{
    /** @use HasFactory<TourArtistFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'tour_artists';

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
