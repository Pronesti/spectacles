<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'type', 'venue_id', 'year', 'month', 'day', 'local_start_time', 'timezone'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'events';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'day' => 'integer'];
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<EventArtist, $this> */
    public function artistLinks(): HasMany
    {
        return $this->hasMany(EventArtist::class)
            ->orderByRaw('event_artists.position IS NULL')
            ->orderBy('event_artists.position')
            ->orderBy('event_artists.id');
    }

    /** @return HasMany<EventTour, $this> */
    public function tourLinks(): HasMany
    {
        return $this->hasMany(EventTour::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<EventProviderReference, $this> */
    public function providerReferences(): HasMany
    {
        return $this->hasMany(EventProviderReference::class);
    }
}
