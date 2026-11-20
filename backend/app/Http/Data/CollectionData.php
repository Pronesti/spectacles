<?php

namespace App\Http\Data;

use App\Models\Attendance;
use App\Models\User;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * The viewer's whole concert collection, in the frontend workspace format.
 */
class CollectionData extends Data
{
    /**
     * @param  list<ConcertData>  $concerts
     * @param  list<CollectionVenueData>  $venues
     */
    public function __construct(
        public int $version,
        #[DataCollectionOf(ConcertData::class)]
        public array $concerts,
        #[DataCollectionOf(CollectionVenueData::class)]
        public array $venues,
    ) {}

    /**
     * @param  iterable<Attendance>  $attendances
     */
    public static function fromAttendances(iterable $attendances, User $viewer): self
    {
        $concerts = [];
        $venues = [];
        foreach ($attendances as $attendance) {
            $venue = $attendance->event->venue;
            if ($venue !== null) {
                $venues[$venue->external_id] = CollectionVenueData::fromVenue($venue);
            }
            $concerts[] = ConcertData::fromAttendance($attendance, $viewer);
        }

        return new self(1, $concerts, array_values($venues));
    }
}
