<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\SetlistEntryArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['setlist_entry_id', 'artist_id'])]
class SetlistEntryArtist extends Model
{
    /** @use HasFactory<SetlistEntryArtistFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'setlist_entry_artists';

    /** @return BelongsTo<SetlistEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(SetlistEntry::class, 'setlist_entry_id');
    }

    /** @return BelongsTo<Artist, $this> */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
