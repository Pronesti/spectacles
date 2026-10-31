<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CatalogProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if ($this->is('api/v1/providers/venues/confirm')) {
            return ['selections' => ['required', 'array', 'size:2'], 'selections.*' => ['required', 'string', 'max:32768', 'distinct']];
        }
        if ($this->is('api/v1/musicbrainz/places')) {
            return ['q' => ['required', 'string', 'min:2', 'max:200']];
        }
        if ($this->is('api/v1/providers/setlistfm/venues/*')) {
            return ['venueUrl' => ['sometimes', 'string', 'max:2048']];
        }
        if ($this->route('mbid') !== null || $this->route('uuid') !== null) {
            return [];
        }
        if ($this->route('id') !== null) {
            return ['locator' => ['required', 'string', 'max:32768']];
        }
        $tour = $this->is('api/v1/providers/setlistfm/tours');
        $rules = ['q' => ['sometimes', 'nullable', 'string', 'max:200'], 'artistUuid' => ['sometimes', 'uuid', 'prohibits:artistMbid'], 'artistMbid' => ['sometimes', 'uuid', 'prohibits:artistUuid'], 'cursor' => ['sometimes', 'string', 'max:8192']];
        if (! $tour) {
            $rules += ['period' => ['required', Rule::in(['past', 'upcoming'])], 'venueUrl' => ['sometimes', 'string', 'max:2048'], 'venueId' => ['required_with:venueUrl', 'regex:/^[a-zA-Z0-9_-]{1,128}$/D'], 'tourId' => ['required_with:tourName', 'regex:/^[a-zA-Z0-9_-]{1,128}$/D'], 'tourName' => ['required_with:tourId', 'string', 'max:255'], 'date' => ['sometimes', 'date_format:Y-m-d'], 'year' => ['sometimes', 'integer', 'between:1,9999'], 'countryCode' => ['sometimes', 'regex:/^[A-Z]{2}$/D']];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), array_keys($this->rules())) !== [] || $this->isMethod('GET') && $this->getContent() !== '') {
                $validator->errors()->add('query', 'Only the supported provider filters are accepted.');
            }
            if ($this->is('api/v1/providers/setlistfm/tours') && ! $this->hasAny(['artistUuid', 'artistMbid'])) {
                $validator->errors()->add('artistMbid', 'Select one identified Artist.');
            }
        }];
    }
}
