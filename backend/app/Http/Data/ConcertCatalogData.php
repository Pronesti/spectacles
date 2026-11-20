<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class ConcertCatalogData extends Data
{
    /**
     * @param  list<string>  $artistIds
     * @param  list<CollectionTourData>  $tours
     */
    public function __construct(
        public string $eventId,
        public array $artistIds,
        #[DataCollectionOf(CollectionTourData::class)]
        public array $tours,
    ) {}
}
