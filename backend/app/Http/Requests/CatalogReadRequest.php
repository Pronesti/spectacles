<?php

namespace App\Http\Requests;

use App\Http\Data\CatalogFilterData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class CatalogReadRequest extends FormRequest
{
    private const array DEFAULT_INCLUDES = [
        'events' => ['venue', 'artistLinks.artist'],
        'tours' => ['artistLinks.artist'],
    ];

    private const array ALLOWED_INCLUDES = [
        'events' => ['venue', 'artistLinks', 'artistLinks.artist', 'tourLinks', 'tourLinks.tour', 'tourLinks.tour.artistLinks', 'tourLinks.tour.artistLinks.artist'],
        'tours' => ['artistLinks', 'artistLinks.artist'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The catalog named by the `catalog.{catalog}.*` route serving this request.
     *
     * @return 'events'|'artists'|'venues'|'songs'|'tours'
     */
    public function catalog(): string
    {
        $catalog = explode('.', (string) $this->route()?->getName())[1] ?? null;

        return match ($catalog) {
            'events', 'artists', 'venues', 'songs', 'tours' => $catalog,
            default => throw new \LogicException('CatalogReadRequest only serves the catalog.{catalog}.* routes.'),
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->getContent() !== '') {
            throw ValidationException::withMessages(['body' => __('validation.prohibited', ['attribute' => 'body'])]);
        }
        $filters = $this->query('filter');
        if (is_array($filters) && isset($filters['country_code']) && is_string($filters['country_code'])) {
            $filters['country_code'] = strtoupper($filters['country_code']);
            $this->merge(['filter' => $filters]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['include' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }
            $allowed = self::ALLOWED_INCLUDES[$this->catalog()] ?? [];
            if (array_diff(explode(',', $value), $allowed) !== []) {
                $fail(__('validation.in', ['attribute' => $attribute]));
            }
        }]];
        if ($this->routeIs('catalog.events.show')) {
            return $rules;
        }
        $text = ['nullable', 'string', 'max:200'];
        $filters = match ($this->catalog()) {
            'events' => [
                'q' => $text,
                'venue_uuid' => ['sometimes', 'required', 'uuid'],
                'artist_uuid' => ['sometimes', 'required', 'uuid'],
                'artist_mbid' => ['sometimes', 'required', 'uuid'],
                'year' => ['sometimes', 'required', 'integer', 'between:1,9999'],
                'month' => ['sometimes', 'required', 'integer', 'between:1,12'],
                'day' => ['sometimes', 'required', 'integer', 'between:1,31'],
            ],
            'artists' => ['q' => $text, 'type' => ['sometimes', 'required', 'string', 'max:255']],
            'venues' => ['q' => $text, 'city' => $text, 'country_code' => ['sometimes', 'required', 'string', 'regex:/^[A-Z]{2}$/']],
            'songs' => ['q' => $text, 'disambiguation' => $text],
            'tours' => ['q' => $text, 'artist_uuid' => ['sometimes', 'required', 'uuid'], 'artist_uuids' => ['sometimes', 'array', 'max:100'], 'artist_mbids' => ['sometimes', 'array', 'max:100']],
        };
        if (in_array($this->catalog(), ['events', 'artists'], true)) {
            foreach (['provider', 'resource_type', 'provider_resource_id'] as $key) {
                $others = array_diff(['provider', 'resource_type', 'provider_resource_id'], [$key]);
                $filters[$key] = ['required_with:'.implode(',', array_map(fn (string $other): string => 'filter.'.$other, $others)), 'string', 'max:255'];
            }
        }
        $rules['filter'] = ['sometimes', 'array:'.implode(',', array_keys($filters))];
        foreach ($filters as $key => $validation) {
            $rules['filter.'.$key] = $validation;
        }
        if ($this->catalog() === 'tours') {
            $rules['filter.artist_uuids.*'] = ['required', 'uuid'];
            $rules['filter.artist_mbids.*'] = ['required', 'uuid'];
        }
        $rules['page'] = ['sometimes', 'required', 'integer', 'between:1,100000'];
        $rules['per_page'] = ['sometimes', 'required', 'integer', 'between:1,100'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = $this->routeIs('catalog.events.show') ? ['include'] : ['filter', 'include', 'page', 'per_page'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $key]));
            }
            if ($this->catalog() === 'tours' && count((array) $this->input('filter.artist_uuids', [])) + count((array) $this->input('filter.artist_mbids', [])) > 100) {
                $validator->errors()->add('filter.artist_uuids', 'Select at most 100 Artists.');
            }
            if ($validator->errors()->isNotEmpty() || $this->catalog() !== 'events') {
                return;
            }
            $filter = CatalogFilterData::from($this->input('filter', []));
            if ($filter->month !== null && $filter->year === null) {
                $validator->errors()->add('filter.year', __('validation.required', ['attribute' => 'filter.year']));
            }
            if ($filter->day !== null && ($filter->year === null || $filter->month === null)) {
                $validator->errors()->add('filter.month', __('validation.required', ['attribute' => 'filter.month']));
            }
            if ($filter->year !== null && $filter->month !== null && $filter->day !== null && ! checkdate($filter->month, $filter->day, $filter->year)) {
                $validator->errors()->add('filter.day', __('validation.date', ['attribute' => 'filter.day']));
            }
        });
    }

    /**
     * The validated catalog filter. The after-validation checks build it from input instead,
     * because `validated()` cannot be called while validation is still running.
     */
    public function filter(): CatalogFilterData
    {
        return CatalogFilterData::from($this->validated('filter', []));
    }

    /** @return list<string> */
    public function includePaths(): array
    {
        $requested = $this->filled('include') ? explode(',', $this->string('include')->toString()) : [];

        return array_values(array_unique([...(self::DEFAULT_INCLUDES[$this->catalog()] ?? []), ...$requested]));
    }

    protected function passedValidation(): void
    {
        $this->merge(['include' => implode(',', $this->includePaths())]);
    }
}
