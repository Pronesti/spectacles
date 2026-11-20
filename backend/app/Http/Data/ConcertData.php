<?php

namespace App\Http\Data;

use App\Models\Attendance;
use App\Models\User;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * One attendance as the viewing member sees it in their collection.
 */
class ConcertData extends Data
{
    /**
     * @param  list<string>  $artists
     * @param  list<SetlistEntryData>  $setlist
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $eventType,
        public array $artists,
        public ?string $venueId,
        public CalendarDateData $date,
        public string $time,
        public ?string $timezone,
        public bool $interested,
        public bool $ticketsBought,
        public string $notes,
        public ConcertCatalogData $catalog,
        #[DataCollectionOf(SetlistEntryData::class)]
        public array $setlist,
    ) {}

    public static function fromAttendance(Attendance $attendance, User $viewer): self
    {
        $event = $attendance->event;
        $member = $attendance->members->firstOrFail('user_id', $viewer->id);

        return new self(
            id: $attendance->external_id,
            title: $event->title,
            eventType: $event->type,
            artists: array_values($event->artistLinks->map(fn ($link) => $link->artist->name)->all()),
            venueId: $event->venue?->external_id,
            date: CalendarDateData::fromEvent($event),
            time: $event->local_start_time ?? '',
            timezone: $event->timezone,
            interested: $member->interested,
            ticketsBought: $member->tickets_bought,
            notes: $attendance->notes ?? '',
            catalog: new ConcertCatalogData(
                eventId: $event->external_id,
                artistIds: array_values($event->artistLinks->map(fn ($link) => $link->artist->external_id)->all()),
                tours: array_values($event->tourLinks->map(fn ($link): CollectionTourData => new CollectionTourData($link->tour->name, $link->tour->external_id))->all()),
            ),
            setlist: array_values($attendance->entries->map(fn ($entry): SetlistEntryData => SetlistEntryData::fromEntry($entry))->all()),
        );
    }
}
