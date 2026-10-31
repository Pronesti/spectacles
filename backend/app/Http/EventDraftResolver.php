<?php

namespace App\Http;

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
    public function resolve(array $concert, ?Venue $venue, ?array $binding): Event
    {
        if (isset($concert['catalog']['eventId'])) {
            return Event::where('external_id', $concert['catalog']['eventId'])->firstOrFail();
        }
        $value = $binding === null ? null : app(CatalogProviderSelection::class)->read($binding['selection'], 'performance', 'pendingCatalog.event.selection');
        if ($value !== null) {
            $this->validateGroup($concert, $venue, $value);
            $existing = $this->existing($value['ref']);
            if ($existing !== null) {
                return $this->compatible($existing, $concert, $venue);
            }
        }
        try {
            return DB::transaction(function () use ($concert, $venue, $value): Event {
                $event = Event::query()->create(['title' => $concert['title'], 'type' => $concert['eventType'] ?? 'concert', 'venue_id' => $venue?->id, ...$concert['date'], 'local_start_time' => $concert['time'] ?: null, 'timezone' => $concert['timezone']]);
                $used = [];
                foreach ($concert['artists'] as $position => $name) {
                    $uuid = $concert['catalog']['artistIds'][$position];
                    $artist = $uuid === null ? Artist::query()->create(['name' => $name]) : Artist::where('external_id', $uuid)->firstOrFail();
                    if (in_array($artist->id, $used, true)) {
                        throw ValidationException::withMessages(['concert.artists' => 'The same Artist cannot appear twice in a lineup.']);
                    }
                    $used[] = $artist->id;
                    $event->artistLinks()->create(['artist_id' => $artist->id, 'position' => $position + 1]);
                }
                $used = [];
                foreach ($concert['catalog']['tours'] as $choice) {
                    $tour = $choice['catalogId'] === null ? Tour::query()->create(['name' => $choice['name']]) : Tour::where('external_id', $choice['catalogId'])->firstOrFail();
                    if (! in_array($tour->id, $used, true)) {
                        $event->tourLinks()->create(['tour_id' => $tour->id]);
                        $used[] = $tour->id;
                    }
                }
                if ($value !== null) {
                    $event->providerReferences()->create($this->tuple($value['ref']));
                    $this->attachWebsiteArtist($value, $event);
                }

                return $event;
            });
        } catch (UniqueConstraintViolationException $failure) {
            $winner = $value === null ? null : $this->existing($value['ref']);
            if ($winner === null) {
                throw $failure;
            }

            return $this->compatible($winner, $concert, $venue);
        }
    }

    private function tuple(array $ref): array
    {
        return ['provider' => $ref['provider'], 'resource_type' => $ref['resourceType'], 'provider_resource_id' => $ref['externalId']];
    }

    private function existing(array $ref): ?Event
    {
        return EventProviderReference::where($this->tuple($ref))->first()?->event;
    }

    private function validateGroup(array $concert, ?Venue $venue, array $value): void
    {
        $matches = count($concert['artists']) === 1 && $concert['date'] === $value['date'] && ($concert['eventType'] ?? 'concert') === 'concert';
        $artistId = $concert['catalog']['artistIds'][0] ?? null;
        $artist = $artistId === null ? null : Artist::where('external_id', $artistId)->first();
        $matches = $matches && $artist !== null && $artist->providerIds()->where($this->tuple($value['artist']['ref']))->exists();
        if ($value['tour'] === null) {
            $matches = $matches && $concert['catalog']['tours'] === [];
        } else {
            $tour = $concert['catalog']['tours'][0] ?? null;
            $ref = $value['tour']['ref'];
            $matches = $matches && count($concert['catalog']['tours']) === 1 && $tour['catalogId'] !== null && TourProviderId::where(['provider' => $ref['provider'], 'resource_type' => $ref['resourceType'], 'external_provider_id' => $ref['externalId']])->whereHas('tour', fn ($q) => $q->where('external_id', $tour['catalogId']))->exists();
        }
        $venueRefs = [$value['venue']['ref'], ...$value['venue']['linkedRefs']];
        $venueMatches = false;
        foreach ($venueRefs as $ref) {
            $venueMatches = $venueMatches || $venue !== null && $venue->providerIds()->where($this->tuple($ref))->exists();
        }
        if (! $matches || ! $venueMatches) {
            throw ValidationException::withMessages(['pendingCatalog.event.selection' => 'The Artist, Tour, Venue or date changed. Reselect the performance or detach it for manual creation.']);
        }
    }

    private function compatible(Event $event, array $concert, ?Venue $venue): Event
    {
        $event->loadMissing(['artistLinks.artist', 'tourLinks.tour']);
        $same = $event->title === $concert['title'] && $event->type === ($concert['eventType'] ?? 'concert') && $event->venue_id === $venue?->id && ['year' => $event->year, 'month' => $event->month, 'day' => $event->day] === $concert['date'] && ($event->local_start_time ?: null) === ($concert['time'] ?: null) && $event->timezone === $concert['timezone'];
        $same = $same && $event->artistLinks->map(fn ($link) => $link->artist->external_id)->all() === $concert['catalog']['artistIds'];
        $actualTours = $event->tourLinks->map(fn ($link) => $link->tour->external_id)->all();
        $requestedTours = array_column($concert['catalog']['tours'], 'catalogId');
        sort($actualTours);
        sort($requestedTours);
        abort_unless($same && $actualTours === $requestedTours, 409, 'This performance is now a saved Event with different details. Review the existing Event or create a detached manual Event.');

        return $event;
    }

    private function attachWebsiteArtist(array $value, Event $event): void
    {
        if (! str_starts_with($value['artist']['url'], 'https://www.setlist.fm/')) {
            return;
        }
        $id = app(SetlistFmUrl::class)->id($value['artist']['url'], 'artist');
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
