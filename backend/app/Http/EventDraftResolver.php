<?php

namespace App\Http;

use App\Http\Data\ConcertDraftData;
use App\Http\Data\PendingSelectionData;
use App\Http\Data\ProviderPerformanceData;
use App\Http\Data\ProviderRefData;
use App\Http\Data\TourChoiceDraftData;
use App\Models\Artist;
use App\Models\ArtistProviderId;
use App\Models\Event;
use App\Models\EventProviderReference;
use App\Models\Tour;
use App\Models\TourProviderId;
use App\Models\Venue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventDraftResolver
{
    public function resolve(ConcertDraftData $concert, ?Venue $venue, ?PendingSelectionData $binding): Event
    {
        if (is_string($concert->catalog->eventId)) {
            return Event::where('external_id', $concert->catalog->eventId)->firstOrFail();
        }
        $value = $binding === null ? null : app(CatalogProviderSelection::class)->performance($binding->selection, 'pendingCatalog.event.selection');
        if ($value !== null) {
            $this->validateGroup($concert, $venue, $value);
            $existing = $this->existing($value->ref);
            if ($existing !== null) {
                return $this->compatible($existing, $concert, $venue);
            }
        }
        try {
            return DB::transaction(function () use ($concert, $venue, $value): Event {
                $event = Event::query()->create(['title' => $concert->title, 'type' => $concert->resolvedEventType(), 'venue_id' => $venue?->id, 'year' => $concert->date->year, 'month' => $concert->date->month, 'day' => $concert->date->day, 'local_start_time' => $concert->time ?: null, 'timezone' => $concert->timezone]);
                $used = [];
                foreach ($concert->artists as $position => $name) {
                    $uuid = $concert->catalog->artistIds[$position];
                    $artist = $uuid === null ? Artist::query()->create(['name' => $name]) : Artist::where('external_id', $uuid)->firstOrFail();
                    if (in_array($artist->id, $used, true)) {
                        throw ValidationException::withMessages(['concert.artists' => 'The same Artist cannot appear twice in a lineup.']);
                    }
                    $used[] = $artist->id;
                    $event->artistLinks()->create(['artist_id' => $artist->id, 'position' => $position + 1]);
                }
                $used = [];
                foreach ($concert->catalog->tours as $choice) {
                    $tour = $choice->catalogId === null ? Tour::query()->create(['name' => $choice->name]) : Tour::where('external_id', $choice->catalogId)->firstOrFail();
                    if (! in_array($tour->id, $used, true)) {
                        $event->tourLinks()->create(['tour_id' => $tour->id]);
                        $used[] = $tour->id;
                    }
                }
                if ($value !== null) {
                    $event->providerReferences()->create($value->ref->tuple());
                    $this->attachWebsiteArtist($value, $event);
                }

                return $event;
            });
        } catch (UniqueConstraintViolationException $failure) {
            $winner = $value === null ? null : $this->existing($value->ref);
            if ($winner === null) {
                throw $failure;
            }

            return $this->compatible($winner, $concert, $venue);
        }
    }

    private function existing(ProviderRefData $ref): ?Event
    {
        return EventProviderReference::where($ref->tuple())->first()?->event;
    }

    private function validateGroup(ConcertDraftData $concert, ?Venue $venue, ProviderPerformanceData $value): void
    {
        $tours = $concert->catalog->tours;
        $matches = count($concert->artists) === 1 && $concert->date->toArray() === $value->date->toArray() && $concert->resolvedEventType() === 'concert';
        $artistId = $concert->catalog->artistIds[0] ?? null;
        $artist = $artistId === null ? null : Artist::where('external_id', $artistId)->first();
        $matches = $matches && $artist !== null && $artist->providerIds()->where($value->artist->ref->tuple())->exists();
        if ($value->tour === null) {
            if ($value->namedTour() !== null) {
                $matches = $matches && count($tours) === 1 && $tours[0]->catalogId === null && $tours[0]->name === $value->namedTour();
            } else {
                $matches = $matches && $tours === [];
            }
        } else {
            $matches = $matches && count($tours) === 1 && $tours[0]->catalogId !== null && TourProviderId::where($value->tour->ref->tuple('external_provider_id'))->whereHas('tour', fn ($q) => $q->where('external_id', $tours[0]->catalogId))->exists();
        }
        $venueMatches = false;
        foreach ($value->venue->identities() as $ref) {
            $venueMatches = $venueMatches || $venue !== null && $venue->providerIds()->where($ref->tuple())->exists();
        }
        if (! $matches || ! $venueMatches) {
            throw ValidationException::withMessages(['pendingCatalog.event.selection' => 'The Artist, Tour, Venue or date changed. Reselect the performance or detach it for manual creation.']);
        }
    }

    private function compatible(Event $event, ConcertDraftData $concert, ?Venue $venue): Event
    {
        $event->loadMissing(['artistLinks.artist', 'tourLinks.tour']);
        $same = $event->title === $concert->title && $event->type === $concert->resolvedEventType() && $event->venue_id === $venue?->id && ['year' => $event->year, 'month' => $event->month, 'day' => $event->day] === $concert->date->toArray() && ($event->local_start_time ?: null) === ($concert->time ?: null) && $event->timezone === $concert->timezone;
        $same = $same && $event->artistLinks->map(fn ($link) => $link->artist->external_id)->all() === $concert->catalog->artistIds;
        $actualTours = $event->tourLinks->map(fn ($link) => $link->tour->external_id)->all();
        $requestedTours = array_map(fn (TourChoiceDraftData $choice): ?string => $choice->catalogId, $concert->catalog->tours);
        sort($actualTours);
        sort($requestedTours);
        abort_unless($same && $actualTours === $requestedTours, 409, 'This performance is now a saved Event with different details. Review the existing Event or create a detached manual Event.');

        return $event;
    }

    private function attachWebsiteArtist(ProviderPerformanceData $value, Event $event): void
    {
        if (! str_starts_with($value->artist->url, 'https://www.setlist.fm/')) {
            return;
        }
        $id = app(SetlistFmUrl::class)->id($value->artist->url, 'artist');
        $artistId = $event->artistLinks()->firstOrFail()->artist_id;
        $tuple = ['provider' => 'setlist_fm', 'resource_type' => 'artist', 'provider_resource_id' => $id];
        $known = ArtistProviderId::where($tuple)->first();
        if ($known === null) {
            try {
                DB::transaction(fn () => ArtistProviderId::create(['artist_id' => $artistId, ...$tuple]));
            } catch (UniqueConstraintViolationException $failure) {
                $known = ArtistProviderId::where($tuple)->first();
                if ($known === null) {
                    throw $failure;
                }
            }
        }
        abort_if($known !== null && $known->artist_id !== $artistId, 409, 'The Artist identity already belongs to another saved Artist.');
    }
}
