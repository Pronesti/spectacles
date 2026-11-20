<?php

namespace App\Http\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A concert as the viewer submits it for saving.
 */
class ConcertDraftData extends Data
{
    use CopiesWithChanges;

    /**
     * @param  list<string>  $artists
     * @param  list<SetlistEntryDraftData>  $setlist
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly array $artists,
        public readonly ?string $venueId,
        public readonly CalendarDateData $date,
        public readonly ?string $time,
        public readonly ?string $timezone,
        public readonly bool $interested,
        public readonly bool $ticketsBought,
        public readonly ?string $notes,
        public readonly ConcertCatalogDraftData $catalog,
        #[DataCollectionOf(SetlistEntryDraftData::class)]
        public readonly array $setlist,
        public readonly Optional|string $eventType = new Optional,
    ) {}

    public function resolvedEventType(): string
    {
        return is_string($this->eventType) ? $this->eventType : 'concert';
    }

    /**
     * @return array<string, SetlistEntryDraftData>
     */
    public function setlistById(): array
    {
        $rows = [];
        foreach ($this->setlist as $row) {
            $rows[$row->id] = $row;
        }

        return $rows;
    }
}
