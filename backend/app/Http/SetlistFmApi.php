<?php

namespace App\Http;

use App\Http\Data\CalendarDateData;
use App\Http\Data\ProviderEntityInputData;
use App\Http\Data\ProviderEntityValuesInputData;
use App\Http\Data\ProviderPerformanceData;
use App\Http\Data\ProviderPerformanceInputData;
use App\Http\Data\ProviderRefInputData;
use App\Http\Data\ProviderVenueData;
use App\Http\Data\SetlistFmSearchQueryData;
use App\Http\Data\SetlistFmSearchResultData;
use App\Http\Integrations\SetlistFm\Data\SetlistFmSearchPayloadData;
use App\Http\Integrations\SetlistFm\Data\SetlistFmSetlistPayloadData;
use App\Http\Integrations\SetlistFm\Data\SetlistFmVenuePayloadData;

class SetlistFmApi
{
    public function __construct(private SetlistFmClient $client, private ProviderReadGate $gate) {}

    public function search(SetlistFmSearchQueryData $query): SetlistFmSearchResultData
    {
        $parameters = $query->parameters();

        return $this->gate->run('setlist_api', 'search:v1:'.json_encode($parameters), SetlistFmSearchResultData::class, function () use ($query, $parameters): SetlistFmSearchResultData {
            $page = $this->client->api('search/setlists', SetlistFmSearchPayloadData::class, $parameters);
            if ($page->setlist === null || ! array_is_list($page->setlist) || count($page->setlist) > 100 || $page->total === null || $page->total < 0 || $page->page !== $query->p || $page->itemsPerPage === null || $page->itemsPerPage < 1 || $page->itemsPerPage > 100 || count($page->setlist) > $page->itemsPerPage) {
                throw new CatalogProviderUnavailable;
            }
            $rows = [];
            foreach ($page->setlist as $setlist) {
                $value = $this->performance($setlist);
                if (is_string($query->artistMbid) && $value->artist->ref->externalId !== $query->artistMbid || is_string($query->venueId) && $value->venue->ref->externalId !== $query->venueId) {
                    throw new CatalogProviderUnavailable;
                }
                if (isset($rows[$value->ref->externalId])) {
                    throw new CatalogProviderUnavailable;
                }
                $rows[$value->ref->externalId] = $value;
            }

            return new SetlistFmSearchResultData(array_values($rows), $page->total > $page->page * $page->itemsPerPage);
        }, true);
    }

    public function detail(string $id): ProviderPerformanceData
    {
        return $this->gate->run('setlist_api', 'detail:v1:'.$id, ProviderPerformanceData::class, function () use ($id): ProviderPerformanceData {
            $value = $this->performance($this->client->api('setlist/'.$id, SetlistFmSetlistPayloadData::class));
            if ($value->ref->externalId !== $id) {
                throw new CatalogProviderUnavailable;
            }

            return $value;
        }, true);
    }

    public function venue(string $id): ProviderVenueData
    {
        return $this->gate->run('setlist_api', 'venue:v1:'.$id, ProviderVenueData::class, function () use ($id): ProviderVenueData {
            $value = CatalogProviderData::venue($this->venueInput($this->client->api('venue/'.$id, SetlistFmVenuePayloadData::class)));
            if ($value->ref->externalId !== $id) {
                throw new CatalogProviderUnavailable;
            }

            return $value;
        }, true);
    }

    /**
     * Maps an API venue to the Venue entity input the validator checks.
     */
    private function venueInput(?SetlistFmVenuePayloadData $venue): ProviderEntityInputData
    {
        $country = $venue?->city?->country?->code;

        return new ProviderEntityInputData(
            ref: new ProviderRefInputData('setlist_fm', 'venue', $venue?->id),
            url: $venue?->url,
            values: new ProviderEntityValuesInputData(name: $venue?->name, city: $venue?->city?->name ?? '', region: $venue?->city?->state, countryCode: $country === null ? null : strtoupper($country)),
        );
    }

    /**
     * Maps an API setlist to the performance input the validator checks.
     */
    private function performance(SetlistFmSetlistPayloadData $setlist): ProviderPerformanceData
    {
        if ($setlist->artist === null || $setlist->venue === null || $setlist->eventDate === null || ! preg_match('/^(\d{2})-(\d{2})-(\d{4})$/D', $setlist->eventDate, $parts)) {
            throw new CatalogProviderUnavailable;
        }
        $artist = $setlist->artist;

        return CatalogProviderData::performance(new ProviderPerformanceInputData(
            ref: new ProviderRefInputData('setlist_fm', 'setlist', $setlist->id),
            url: $setlist->url,
            date: new CalendarDateData((int) $parts[3], (int) $parts[2], (int) $parts[1]),
            artist: new ProviderEntityInputData(
                ref: new ProviderRefInputData('musicbrainz', 'artist', $artist->mbid),
                url: $artist->url,
                values: new ProviderEntityValuesInputData(name: $artist->name, disambiguation: ($artist->disambiguation ?? '') === '' ? null : $artist->disambiguation),
            ),
            venue: $this->venueInput($setlist->venue),
            tourName: $setlist->tour === null ? null : CatalogProviderData::text($setlist->tour->name),
        ));
    }
}
