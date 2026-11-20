<?php

namespace App\Http\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The catalog records a submitted concert points at; null ids are still to be created.
 */
class ConcertCatalogDraftData extends Data
{
    use CopiesWithChanges;

    /**
     * @param  list<string|null>  $artistIds
     * @param  list<TourChoiceDraftData>  $tours
     */
    public function __construct(
        public readonly array $artistIds,
        #[DataCollectionOf(TourChoiceDraftData::class)]
        public readonly array $tours,
        public readonly Optional|string $eventId = new Optional,
    ) {}

    public function hasEvent(): bool
    {
        return is_string($this->eventId);
    }
}
