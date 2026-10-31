<?php

namespace App\Http\Requests;

use App\Http\CatalogProviderData;
use App\Http\CatalogProviderUnavailable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveConcertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

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

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            PendingCatalogRules::check($this->input('concert'), $this->input('pendingCatalog', []), $validator);
            if ($this->has('pendingCatalog.venue') && $this->has('venue.catalogId')) {
                $validator->errors()->add('pendingCatalog.venue', 'Choose one Venue identity.');
            }
            if ($this->input('venue.image') !== null) {
                try {
                    CatalogProviderData::image($this->input('venue.image'));
                } catch (CatalogProviderUnavailable) {
                    $validator->errors()->add('venue.image', 'Choose valid attributed image metadata.');
                }
            }
            $date = $this->input('concert.date', []);
            $year = $date['year'] ?? null;
            $month = $date['month'] ?? null;
            $day = $date['day'] ?? null;
            if (($month !== null && $year === null) || ($day !== null && $month === null)
                || ($year && $month && $day && ! checkdate((int) $month, (int) $day, (int) $year))) {
                $validator->errors()->add('concert.date', 'Enter a valid date at its known precision.');
            }
            if (count($this->input('concert.artists', [])) !== count($this->input('concert.catalog.artistIds', []))) {
                $validator->errors()->add('concert.artists', 'Artist references must match the lineup.');
            }
            if (($this->input('venue.lat') === null) !== ($this->input('venue.lng') === null)) {
                $validator->errors()->add('venue.lat', 'Supply both coordinates or neither.');
            }
            foreach ($this->input('concert.setlist', []) as $index => $entry) {
                if (($entry['type'] ?? '') === 'note' && (($entry['tape'] ?? false) || isset($entry['songCatalogId']) || isset($entry['artistCatalogId']))) {
                    $validator->errors()->add("concert.setlist.$index", 'Notes cannot have song identities or tape status.');
                }
            }
        }];
    }
}
