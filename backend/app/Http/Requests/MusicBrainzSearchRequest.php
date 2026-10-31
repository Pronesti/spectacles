<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MusicBrainzSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->is('api/v1/musicbrainz/tours') && is_string($this->query('q'))) {
            $this->merge(['q' => trim($this->query('q'))]);
        }
    }

    public function rules(): array
    {
        $rules = ['q' => ['required', 'string', 'min:2', 'max:200']];
        if ($this->is('api/v1/musicbrainz/tours')) {
            $rules['q'] = ['required', 'string', 'min:3', 'max:200'];
            foreach (['artist_uuids', 'artist_mbids'] as $key) {
                $rules[$key] = ['sometimes', 'array', 'max:100'];
                $rules[$key.'.*'] = ['required', 'uuid'];
            }
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = $this->is('api/v1/musicbrainz/tours') ? ['q', 'artist_uuids', 'artist_mbids'] : ['q'];
            if (count((array) $this->input('artist_uuids', [])) + count((array) $this->input('artist_mbids', [])) > 100) {
                $validator->errors()->add('artist_uuids', 'Select at most 100 Artists.');
            }
            if (array_diff(array_keys($this->query()), $allowed) !== [] || $this->getContent() !== '') {
                $validator->errors()->add('q', 'Only the search query is supported.');
            }
        }];
    }
}
