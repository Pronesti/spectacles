<?php

namespace App\Http;

use App\Models\Venue;
use App\Models\VenueProviderId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenueDraftResolver
{
    public function resolve(array $concert, ?array $venue, ?array $binding): ?Venue
    {
        if ($binding === null) {
            if ($venue === null) {
                return $concert['venueId'] === null ? null : Venue::where('external_id', $concert['venueId'])->firstOrFail();
            }
            if (isset($venue['catalogId'])) {
                return Venue::where('external_id', $venue['catalogId'])->firstOrFail();
            }

            return Venue::query()->create($this->fields($venue));
        }
        if ($venue === null || isset($venue['catalogId']) || isset($concert['catalog']['eventId'])) {
            throw ValidationException::withMessages(['pendingCatalog.venue' => 'Choose either a saved Venue or a provider Venue.']);
        }
        $value = app(CatalogProviderSelection::class)->read($binding['selection'], 'venue', 'pendingCatalog.venue.selection');
        $refs = [$value['ref'], ...$value['linkedRefs']];
        $sources = array_column($value['venueSources'] ?? [], 'url', 'externalId');
        usort($refs, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        $known = $this->known($refs, $sources);
        if ($known !== null) {
            return $known;
        }
        try {
            return DB::transaction(function () use ($venue, $refs, $sources): Venue {
                $parent = Venue::query()->create($this->fields($venue));
                foreach ($refs as $ref) {
                    $parent->providerIds()->create([...$this->tuple($ref), 'source_url' => $this->sourceUrl($ref, $sources)]);
                }

                return $parent;
            });
        } catch (UniqueConstraintViolationException $failure) {
            $winner = $this->known($refs, $sources);
            if ($winner === null) {
                throw $failure;
            }

            return $winner;
        }
    }

    private function known(array $refs, array $sources): ?Venue
    {
        $parents = [];
        foreach ($refs as $ref) {
            $row = VenueProviderId::where($this->tuple($ref))->first();
            if ($row !== null) {
                $parents[$row->venue_id] = $row->venue;
            }
        }
        abort_if(count($parents) > 1, 409, 'These identities belong to different saved Venues. Review the Venue selections.');
        $parent = array_values($parents)[0] ?? null;
        // A confirmed association can add a new explicit handle without changing shared Venue facts.
        if ($parent !== null) {
            foreach ($refs as $ref) {
                $existing = VenueProviderId::where($this->tuple($ref))->first();
                if ($existing !== null && $existing->source_url === null && $this->sourceUrl($ref, $sources) !== null) {
                    $existing->update(['source_url' => $this->sourceUrl($ref, $sources)]);
                }
                if ($existing === null) {
                    try {
                        DB::transaction(fn () => $parent->providerIds()->create([...$this->tuple($ref), 'source_url' => $this->sourceUrl($ref, $sources)]));
                    } catch (UniqueConstraintViolationException $failure) {
                        $winner = VenueProviderId::where($this->tuple($ref))->first();
                        if ($winner === null) {
                            throw $failure;
                        }
                        abort_if($winner->venue_id !== $parent->id, 409, 'The Venue association changed. Review it again.');
                    }
                }
            }
        }

        return $parent;
    }

    private function sourceUrl(array $ref, array $sources): ?string
    {
        $url = $sources[$ref['externalId']] ?? null;

        return $url !== null && $ref['provider'] === 'setlist_fm' && app(SetlistFmUrl::class)->id($url, 'venue') === $ref['externalId'] ? $url : null;
    }

    private function tuple(array $ref): array
    {
        return ['provider' => $ref['provider'], 'resource_type' => $ref['resourceType'], 'provider_resource_id' => $ref['externalId']];
    }

    private function fields(array $value): array
    {
        return ['name' => $value['name'], 'city' => $value['city'], 'region' => $value['region'] ?? null, 'country_code' => $value['countryCode'] ?? null, 'address' => $value['address'] ?? null, 'aliases' => $value['aliases'] ?? [], 'image' => $value['image'] ?? null, 'lat' => $value['lat'], 'lng' => $value['lng'], 'timezone' => $value['timezone'] ?? null];
    }
}
