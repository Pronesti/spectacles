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
}
