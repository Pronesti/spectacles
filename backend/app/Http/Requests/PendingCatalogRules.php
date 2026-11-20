<?php

namespace App\Http\Requests;

use App\Http\CatalogProviderSelection;
use App\Http\Data\ConcertDraftData;
use App\Http\Data\PendingCatalogData;
use App\Http\MusicBrainzSelection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class PendingCatalogRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $present): array
    {
        $rules = ['pendingCatalog' => ['sometimes', 'array:artists,songs,lineup,entries,tours,tourChoices,venue,event']];
        if (! $present) {
            return $rules;
        }
        foreach (['artists' => 1100, 'songs' => 1000, 'lineup' => 100, 'entries' => 1000] as $map => $max) {
            $rules['pendingCatalog.'.$map] = ['present', 'array', 'max:'.$max];
        }
        foreach (['artists' => ['name', 'type', 'disambiguation', 'selection'], 'songs' => ['title', 'disambiguation', 'selection'], 'tours' => ['name', 'selection']] as $map => $fields) {
            $rules['pendingCatalog.'.$map.'.*'] = ['array:'.implode(',', $fields)];
            foreach ($fields as $field) {
                $rules['pendingCatalog.'.$map.'.*.'.$field] = in_array($field, ['name', 'title'], true) ? ['required', 'string', 'max:255'] : ['present', 'nullable', 'string', 'max:'.($field === 'selection' ? ($map === 'tours' ? 32768 : 4096) : 255)];
            }
        }
        foreach (['venue', 'event'] as $singleton) {
            $rules['pendingCatalog.'.$singleton] = ['sometimes', 'filled', 'array:selection'];
            $rules['pendingCatalog.'.$singleton.'.selection'] = ['required_with:pendingCatalog.'.$singleton, 'string', 'max:32768'];
        }
        $rules['pendingCatalog.tours'] = ['sometimes', 'array', 'max:100'];
        $rules['pendingCatalog.tourChoices'] = ['sometimes', 'array', 'max:100'];
        $rules['pendingCatalog.tourChoices.*'] = ['required', 'uuid'];
        $rules['pendingCatalog.lineup.*'] = ['required', 'uuid'];
        $rules['pendingCatalog.entries.*'] = ['array:song,artist'];
        foreach (['song', 'artist'] as $field) {
            $rules['pendingCatalog.entries.*.'.$field] = ['sometimes', 'required', 'uuid'];
        }

        return $rules;
    }

    public static function check(ConcertDraftData $concert, ?PendingCatalogData $pending, Validator $validator): void
    {
        if ($pending === null) {
            return;
        }
        $error = function (string $path) use ($validator): void {
            $validator->errors()->add('pendingCatalog.'.$path, 'Choose a valid, exclusively referenced catalog draft.');
        };
        foreach (['venue' => [$pending->venueSelection(), 'venue'], 'event' => [$pending->eventSelection(), 'performance']] as $singleton => [$binding, $kind]) {
            if ($binding === null) {
                continue;
            }
            if ($concert->catalog->hasEvent()) {
                $error($singleton);
            }
            try {
                app(CatalogProviderSelection::class)->read($binding->selection, $kind, 'pendingCatalog.'.$singleton.'.selection');
            } catch (ValidationException $failure) {
                foreach ($failure->validator->errors()->messages() as $path => $messages) {
                    $validator->errors()->add($path, $messages[0]);
                }
            }
        }
        if (is_array($pending->tours) !== is_array($pending->tourChoices)) {
            $error('tours');
        }
        foreach (PendingCatalogData::DRAFT_KINDS as $map => $kind) {
            foreach ($pending->drafts($map) as $key => $draft) {
                if (! Str::isUuid((string) $key) || $pending->claimsKeyBefore($map, $key)) {
                    $error($map);
                }
                if ($draft->selection !== null) {
                    try {
                        if ($kind === 'series') {
                            app(CatalogProviderSelection::class)->tour($draft->selection, 'pendingCatalog.'.$map.'.'.$key.'.selection');
                        } else {
                            app(MusicBrainzSelection::class)->read($draft->selection, $kind, 'pendingCatalog.'.$map.'.'.$key.'.selection');
                        }
                    } catch (ValidationException $failure) {
                        foreach ($failure->validator->errors()->messages() as $path => $messages) {
                            $validator->errors()->add($path, $messages[0]);
                        }
                    }
                }
            }
        }
        foreach ($pending->lineup as $position => $key) {
            $draft = $pending->artists[$key] ?? null;
            if (! preg_match('/^(0|[1-9][0-9]*)$/', (string) $position) || ! array_key_exists($position, $concert->artists) || $draft === null || ($concert->catalog->artistIds[$position] ?? null) !== null || $concert->artists[$position] !== $draft->name || $concert->catalog->hasEvent()) {
                $error('lineup');
            }
        }
        foreach ($pending->pendingTourChoices() as $position => $key) {
            $draft = $pending->pendingTours()[$key] ?? null;
            $choice = $concert->catalog->tours[$position] ?? null;
            if (! preg_match('/^(0|[1-9][0-9]*)$/', (string) $position) || $choice === null || $draft === null || $choice->catalogId !== null || $choice->name !== $draft->name || $concert->catalog->hasEvent()) {
                $error('tourChoices');
            }
        }
        $rows = $concert->setlistById();
        foreach ($pending->entries as $id => $refs) {
            $row = $rows[$id] ?? null;
            if (! Str::isUuid((string) $id) || $row === null || $row->type !== 'song' || (! is_string($refs->song) && ! is_string($refs->artist))) {
                $error('entries');
            }
            if (is_string($refs->song)) {
                $draft = $pending->songs[$refs->song] ?? null;
                if ($draft === null || is_string($row?->songCatalogId) || $row?->title !== $draft->title) {
                    $error('entries');
                }
            }
            if (is_string($refs->artist)) {
                $draft = $pending->artists[$refs->artist] ?? null;
                if ($draft === null || is_string($row?->artistCatalogId) || $row?->artist !== $draft->name) {
                    $error('entries');
                }
            }
        }
        foreach (array_keys(PendingCatalogData::DRAFT_KINDS) as $map) {
            foreach ($pending->drafts($map) as $key => $draft) {
                if (! $pending->refersTo($map, $key)) {
                    $error($map);
                }
            }
        }
    }
}
