<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\EventProviderReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'provider', 'resource_type', 'provider_resource_id'])]
class EventProviderReference extends Model
{
    /** @use HasFactory<EventProviderReferenceFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'event_provider_references';

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
