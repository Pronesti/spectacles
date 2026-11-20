<?php

namespace App\Http\Requests;

use App\Http\CatalogProviderData;
use App\Http\CatalogProviderUnavailable;
use App\Http\Data\ConcertDraftData;
use App\Http\Data\PendingCatalogData;
use App\Http\Data\VenueDraftData;
use App\Http\Data\VenueDraftSummaryData;
use App\Http\Data\VenueImageInputData;
use App\Http\UntrustedPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveConcertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...PendingCatalogRules::rules($this->exists('pendingCatalog')),
            'concert' => ['required', 'array:id,title,artists,venueId,date,time,timezone,interested,ticketsBought,notes,setlist,eventType,catalog'],
            'concert.id' => ['required', 'uuid'],
            'concert.title' => ['required', 'string', 'max:255'],
            'concert.artists' => ['present', 'array', 'max:100'],
            'concert.artists.*' => ['required', 'string', 'max:255'],
            'concert.venueId' => ['present', 'nullable', 'uuid'],
            'concert.eventType' => ['sometimes', 'string', Rule::in(['concert', 'festival', 'theatre', 'comedy', 'dance', 'other'])],
            'concert.date' => ['required', 'array:year,month,day'],
            'concert.date.year' => ['present', 'nullable', 'integer', 'between:1,9999'],
            'concert.date.month' => ['present', 'nullable', 'integer', 'between:1,12'],
            'concert.date.day' => ['present', 'nullable', 'integer', 'between:1,31'],
            'concert.time' => ['present', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'concert.timezone' => ['present', 'nullable', 'timezone'],
            'concert.interested' => ['required', 'boolean'],
            'concert.ticketsBought' => ['required', 'boolean'],
            'concert.notes' => ['present', 'nullable', 'string', 'max:20000'],
            'concert.catalog' => ['required', 'array:eventId,artistIds,tours'],
            'concert.catalog.eventId' => ['sometimes', 'uuid', 'exists:events,external_id'],
            'concert.catalog.artistIds' => ['present', 'array'],
            'concert.catalog.artistIds.*' => ['nullable', 'uuid', 'exists:artists,external_id'],
            'concert.catalog.tours' => ['present', 'array', 'max:100'],
            'concert.catalog.tours.*' => ['array:name,catalogId'],
            'concert.catalog.tours.*.name' => ['required', 'string', 'max:255'],
            'concert.catalog.tours.*.catalogId' => ['present', 'nullable', 'uuid', 'exists:tours,external_id'],
            'concert.setlist' => ['present', 'array', 'max:1000'],
            'concert.setlist.*' => ['array:id,type,title,artist,tape,songCatalogId,artistCatalogId'],
            'concert.setlist.*.id' => ['required', 'uuid', 'distinct'],
            'concert.setlist.*.type' => ['required', Rule::in(['song', 'note'])],
            'concert.setlist.*.title' => ['required', 'string', 'max:2000'],
            'concert.setlist.*.artist' => ['present', 'nullable', 'string', 'max:2000'],
            'concert.setlist.*.tape' => ['required', 'boolean'],
            'concert.setlist.*.songCatalogId' => ['sometimes', 'uuid', 'exists:songs,external_id'],
            'concert.setlist.*.artistCatalogId' => ['sometimes', 'uuid', 'exists:artists,external_id'],
            'venue' => ['sometimes', 'array:id,name,city,neighborhood,lat,lng,catalogId,timezone,region,countryCode,address,aliases,image'],
            'venue.id' => ['required_with:venue', 'uuid'],
            'venue.name' => ['required_with:venue', 'string', 'max:255'],
            'venue.city' => ['present_with:venue', 'nullable', 'string', 'max:255'],
            'venue.neighborhood' => ['sometimes', 'nullable', 'string', 'max:255'],
            'venue.lat' => ['present_with:venue', 'nullable', 'numeric', 'between:-90,90'],
            'venue.lng' => ['present_with:venue', 'nullable', 'numeric', 'between:-180,180'],
            'venue.catalogId' => ['sometimes', 'uuid', 'exists:venues,external_id'],
            'venue.region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'venue.countryCode' => ['sometimes', 'nullable', 'regex:/^[A-Z]{2}$/D'],
            'venue.address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'venue.aliases' => ['sometimes', 'array', 'max:100'],
            'venue.aliases.*' => ['required', 'string', 'max:255', 'distinct'],
            'venue.image' => ['sometimes', 'nullable', 'array:url,thumbnailUrl,sourceUrl,credit,license,licenseUrl'],
            'venue.timezone' => ['sometimes', 'nullable', 'timezone'],
        ];
    }

    /**
     * The validated concert. The after-validation checks build it from input instead,
     * because `validated()` cannot be called while validation is still running.
     */
    public function concert(): ConcertDraftData
    {
        return ConcertDraftData::from($this->validated('concert'));
    }

    public function venue(): ?VenueDraftData
    {
        return $this->has('venue') ? VenueDraftData::from($this->validated('venue')) : null;
    }

    public function pendingCatalog(): ?PendingCatalogData
    {
        return $this->has('pendingCatalog') ? PendingCatalogData::from($this->validated('pendingCatalog')) : null;
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $concert = ConcertDraftData::from($this->input('concert'));
            $pending = $this->has('pendingCatalog') ? PendingCatalogData::from($this->input('pendingCatalog')) : null;
            $venue = $this->has('venue') ? VenueDraftSummaryData::from($this->input('venue')) : null;
            PendingCatalogRules::check($concert, $pending, $validator);
            if ($pending?->venueSelection() !== null && $venue?->isSaved()) {
                $validator->errors()->add('pendingCatalog.venue', 'Choose one Venue identity.');
            }
            if ($this->input('venue.image') !== null) {
                try {
                    CatalogProviderData::image(UntrustedPayload::parse(VenueImageInputData::class, $this->input('venue.image'), fn (): CatalogProviderUnavailable => new CatalogProviderUnavailable));
                } catch (CatalogProviderUnavailable) {
                    $validator->errors()->add('venue.image', 'Choose valid attributed image metadata.');
                }
            }
            $date = $concert->date;
            if (($date->month !== null && $date->year === null) || ($date->day !== null && $date->month === null)
                || ($date->year && $date->month && $date->day && ! checkdate($date->month, $date->day, $date->year))) {
                $validator->errors()->add('concert.date', 'Enter a valid date at its known precision.');
            }
            if (count($concert->artists) !== count($concert->catalog->artistIds)) {
                $validator->errors()->add('concert.artists', 'Artist references must match the lineup.');
            }
            if ($venue?->hasOneCoordinate()) {
                $validator->errors()->add('venue.lat', 'Supply both coordinates or neither.');
            }
            foreach ($concert->setlist as $index => $entry) {
                if ($entry->type === 'note' && ($entry->tape || is_string($entry->songCatalogId) || is_string($entry->artistCatalogId))) {
                    $validator->errors()->add("concert.setlist.$index", 'Notes cannot have song identities or tape status.');
                }
            }
        }];
    }
}
