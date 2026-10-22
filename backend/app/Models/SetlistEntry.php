<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\SetlistEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['attendance_id', 'song_id', 'position', 'type', 'is_tape', 'display_text', 'notes'])]
class SetlistEntry extends Model
{
    /** @use HasFactory<SetlistEntryFactory> */
    use HasFactory, HasPublicId;

    protected $table = 'setlist_entries';

    protected $attributes = ['type' => 'song', 'is_tape' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => 'integer', 'is_tape' => 'boolean'];
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<Song, $this> */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    /** @return HasMany<SetlistEntryArtist, $this> */
    public function artistLinks(): HasMany
    {
        return $this->hasMany(SetlistEntryArtist::class);
    }
}
