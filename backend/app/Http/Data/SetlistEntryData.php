<?php

namespace App\Http\Data;

use App\Models\SetlistEntry;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A setlist entry; catalog ids are omitted, never null, when unknown.
 */
class SetlistEntryData extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $artist,
        public bool $tape,
        public string|Optional $songCatalogId,
        public string|Optional $artistCatalogId,
    ) {}

    public static function fromEntry(SetlistEntry $entry): self
    {
        $artists = $entry->artistLinks->map(fn ($link) => $link->artist);

        return new self(
            id: $entry->external_id,
            type: $entry->type,
            title: $entry->display_text ?? $entry->song?->title ?? 'Unidentified song',
            artist: $artists->pluck('name')->join(' · '),
            tape: $entry->is_tape,
            songCatalogId: $entry->song?->external_id ?? Optional::create(),
            artistCatalogId: $artists->count() === 1 ? $artists->first()->external_id : Optional::create(),
        );
    }
}
