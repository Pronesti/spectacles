<?php

namespace App\Http\Data;

use App\Models\Event;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * A saved Event that a provider performance already resolves to.
 */
class LocalEventData extends Data
{
    /**
     * @param  list<LocalArtistData>  $artists
     * @param  list<LocalTourData>  $tours
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $type,
        public CalendarDateData $date,
        public ?string $time,
        public ?string $timezone,
        public ?LocalVenueData $venue,
        #[DataCollectionOf(LocalArtistData::class)]
        public array $artists,
        #[DataCollectionOf(LocalTourData::class)]
        public array $tours,
    ) {}

    public static function fromEvent(Event $event): self
    {
        $event->loadMissing(['venue', 'artistLinks.artist', 'tourLinks.tour.artistLinks.artist']);

        return new self(
            id: $event->external_id,
            title: $event->title,
            type: $event->type,
            date: CalendarDateData::fromEvent($event),
            time: $event->local_start_time,
            timezone: $event->timezone,
            venue: $event->venue === null ? null : LocalVenueData::fromVenue($event->venue),
            artists: $event->artistLinks->map(fn ($link): LocalArtistData => LocalArtistData::fromArtist($link->artist))->all(),
            tours: $event->tourLinks->map(fn ($link): LocalTourData => LocalTourData::fromTour($link->tour))->all(),
        );
    }
}
