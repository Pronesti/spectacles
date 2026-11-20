<?php

namespace App\Http\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Catalog records a concert save may create, keyed by client-chosen UUIDs, and
 * the provider proofs that identify them.
 */
class PendingCatalogData extends Data
{
    /**
     * Each draft map and the provider kind its proofs name, in the order drafts are checked.
     */
    public const DRAFT_KINDS = ['artists' => 'artist', 'songs' => 'work', 'tours' => 'series'];

    /**
     * @param  array<string, PendingArtistData>  $artists
     * @param  array<string, PendingSongData>  $songs
     * @param  array<int|string, string>  $lineup  concert artist position to pending Artist key
     * @param  array<string, PendingEntryData>  $entries  setlist entry id to pending keys
     * @param  Optional|array<string, PendingTourData>  $tours
     * @param  Optional|array<int|string, string>  $tourChoices  concert tour position to pending Tour key
     */
    public function __construct(
        #[DataCollectionOf(PendingArtistData::class)]
        public readonly array $artists,
        #[DataCollectionOf(PendingSongData::class)]
        public readonly array $songs,
        public readonly array $lineup,
        #[DataCollectionOf(PendingEntryData::class)]
        public readonly array $entries,
        #[DataCollectionOf(PendingTourData::class)]
        public readonly Optional|array $tours = new Optional,
        public readonly Optional|array $tourChoices = new Optional,
        public readonly Optional|PendingSelectionData $venue = new Optional,
        public readonly Optional|PendingSelectionData $event = new Optional,
    ) {}

    /**
     * @return array<string, PendingTourData>
     */
    public function pendingTours(): array
    {
        return is_array($this->tours) ? $this->tours : [];
    }

    /**
     * @return array<int|string, string>
     */
    public function pendingTourChoices(): array
    {
        return is_array($this->tourChoices) ? $this->tourChoices : [];
    }

    public function venueSelection(): ?PendingSelectionData
    {
        return $this->venue instanceof PendingSelectionData ? $this->venue : null;
    }

    public function eventSelection(): ?PendingSelectionData
    {
        return $this->event instanceof PendingSelectionData ? $this->event : null;
    }

    /**
     * @param  'artists'|'songs'|'tours'  $map
     * @return array<int|string, PendingArtistData|PendingSongData|PendingTourData>
     */
    public function drafts(string $map): array
    {
        return match ($map) {
            'artists' => $this->artists,
            'songs' => $this->songs,
            'tours' => $this->pendingTours(),
        };
    }

    /**
     * Whether a draft in a map checked earlier already uses this key.
     *
     * @param  'artists'|'songs'|'tours'  $map
     */
    public function claimsKeyBefore(string $map, int|string $key): bool
    {
        foreach (array_keys(self::DRAFT_KINDS) as $earlier) {
            if ($earlier === $map) {
                return false;
            }
            if (array_key_exists($key, $this->drafts($earlier))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the lineup, the tour choices or a setlist entry refers to this draft.
     *
     * @param  'artists'|'songs'|'tours'  $map
     */
    public function refersTo(string $map, int|string $key): bool
    {
        $key = (string) $key;
        $references = match ($map) {
            'artists' => [...array_values($this->lineup), ...array_map(fn (PendingEntryData $refs): Optional|string => $refs->artist, array_values($this->entries))],
            'songs' => array_map(fn (PendingEntryData $refs): Optional|string => $refs->song, array_values($this->entries)),
            'tours' => array_values($this->pendingTourChoices()),
        };

        return in_array($key, $references, true);
    }
}
