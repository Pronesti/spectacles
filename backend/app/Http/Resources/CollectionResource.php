<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $venues = [];
        $concerts = [];
        foreach ($this->resource as $attendance) {
            $event = $attendance->event;
            $venue = $event->venue;
            if ($venue !== null) {
                $venues[$venue->external_id] = [
                    'id' => $venue->external_id, 'catalogId' => $venue->external_id,
                    'name' => $venue->name, 'city' => $venue->city ?? '', 'neighborhood' => '',
                    'lat' => $venue->lat === null ? null : (float) $venue->lat,
                    'lng' => $venue->lng === null ? null : (float) $venue->lng, 'timezone' => $venue->timezone,
                ];
            }
            $member = $attendance->members->firstWhere('user_id', $request->user()->id);
            $concerts[] = [
                'id' => $attendance->external_id, 'title' => $event->title, 'eventType' => $event->type,
                'artists' => $event->artistLinks->map(fn ($link) => $link->artist->name)->all(),
                'venueId' => $venue?->external_id,
                'date' => ['year' => $event->year, 'month' => $event->month, 'day' => $event->day],
                'time' => $event->local_start_time ?? '', 'timezone' => $event->timezone,
                'interested' => $member->interested, 'ticketsBought' => $member->tickets_bought,
                'notes' => $attendance->notes ?? '',
                'catalog' => [
                    'eventId' => $event->external_id,
                    'artistIds' => $event->artistLinks->map(fn ($link) => $link->artist->external_id)->all(),
                    'tours' => $event->tourLinks->map(fn ($link) => ['name' => $link->tour->name, 'catalogId' => $link->tour->external_id])->all(),
                ],
                'setlist' => $attendance->entries->map(function ($entry): array {
                    $artists = $entry->artistLinks->map(fn ($link) => $link->artist);

                    return array_filter([
                        'id' => $entry->external_id, 'type' => $entry->type,
                        'title' => $entry->display_text ?? $entry->song?->title ?? 'Unidentified song',
                        'artist' => $artists->pluck('name')->join(' · '), 'tape' => $entry->is_tape,
                        'songCatalogId' => $entry->song?->external_id,
                        'artistCatalogId' => $artists->count() === 1 ? $artists->first()->external_id : null,
                    ], fn ($value) => $value !== null);
                })->all(),
            ];
        }

        return ['version' => 1, 'concerts' => $concerts, 'venues' => array_values($venues)];
    }
}
