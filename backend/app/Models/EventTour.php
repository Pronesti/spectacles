<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\EventTourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'tour_id'])]
class EventTour extends Model
{
    /** @use HasFactory<EventTourFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'event_tours';

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
