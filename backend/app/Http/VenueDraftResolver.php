<?php

namespace App\Http;

use App\Http\Data\ConcertDraftData;
use App\Http\Data\PendingSelectionData;
use App\Http\Data\ProviderRefData;
use App\Http\Data\VenueDraftData;
use App\Models\Venue;
use App\Models\VenueProviderId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenueDraftResolver
{
    public function resolve(ConcertDraftData $concert, ?VenueDraftData $venue, ?PendingSelectionData $binding): ?Venue
    {
        if ($binding === null) {
            if ($venue === null) {
                return $concert->venueId === null ? null : Venue::where('external_id', $concert->venueId)->firstOrFail();
            }
            if (is_string($venue->catalogId)) {
                return Venue::where('external_id', $venue->catalogId)->firstOrFail();
            }

            return Venue::query()->create($venue->attributes());
        }
        if ($venue === null || $venue->isSaved() || $concert->catalog->hasEvent()) {
            throw ValidationException::withMessages(['pendingCatalog.venue' => 'Choose either a saved Venue or a provider Venue.']);
        }
        $value = app(CatalogProviderSelection::class)->venue($binding->selection, 'pendingCatalog.venue.selection');
        $refs = $value->identities();
        $sources = $value->sourceUrls();
        usort($refs, fn (ProviderRefData $a, ProviderRefData $b): int => strcmp($a->key(), $b->key()));
        $known = $this->known($refs, $sources);
        if ($known !== null) {
            return $known;
        }
        try {
            return DB::transaction(function () use ($venue, $refs, $sources): Venue {
                $parent = Venue::query()->create($venue->attributes());
                foreach ($refs as $ref) {
                    $parent->providerIds()->create([...$ref->tuple(), 'source_url' => $this->sourceUrl($ref, $sources)]);
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

    /**
     * @param  list<ProviderRefData>  $refs
     * @param  array<string, string>  $sources
     */
    private function known(array $refs, array $sources): ?Venue
    {
        $parents = [];
        foreach ($refs as $ref) {
            $row = VenueProviderId::where($ref->tuple())->first();
            if ($row !== null) {
                $parents[$row->venue_id] = $row->venue;
            }
        }
        abort_if(count($parents) > 1, 409, 'These identities belong to different saved Venues. Review the Venue selections.');
        $parent = array_values($parents)[0] ?? null;
        // A confirmed association can add a new explicit handle without changing shared Venue facts.
        if ($parent !== null) {
            foreach ($refs as $ref) {
                $existing = VenueProviderId::where($ref->tuple())->first();
                if ($existing !== null && $existing->source_url === null && $this->sourceUrl($ref, $sources) !== null) {
                    $existing->update(['source_url' => $this->sourceUrl($ref, $sources)]);
                }
                if ($existing === null) {
                    try {
                        DB::transaction(fn () => $parent->providerIds()->create([...$ref->tuple(), 'source_url' => $this->sourceUrl($ref, $sources)]));
                    } catch (UniqueConstraintViolationException $failure) {
                        $winner = VenueProviderId::where($ref->tuple())->first();
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

    /**
     * @param  array<string, string>  $sources
     */
    private function sourceUrl(ProviderRefData $ref, array $sources): ?string
    {
        $url = $sources[$ref->externalId] ?? null;

        return $url !== null && $ref->provider === 'setlist_fm' && app(SetlistFmUrl::class)->id($url, 'venue') === $ref->externalId ? $url : null;
    }
}
