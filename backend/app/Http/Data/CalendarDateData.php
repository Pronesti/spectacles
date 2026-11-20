<?php

namespace App\Http\Data;

use App\Models\Event;
use Spatie\LaravelData\Data;

class CalendarDateData extends Data
{
    public function __construct(
        public ?int $year,
        public ?int $month,
        public ?int $day,
    ) {}

    public static function fromEvent(Event $event): self
    {
        return new self($event->year, $event->month, $event->day);
    }
}
