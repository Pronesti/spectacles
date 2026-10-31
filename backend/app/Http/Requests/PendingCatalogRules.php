<?php

namespace App\Http\Requests;

use App\Http\CatalogProviderSelection;
use App\Http\MusicBrainzSelection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class PendingCatalogRules
{
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
            $rules['pendingCatalog.'.$singleton] = ['sometimes', 'array:selection'];
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

    public static function check(array $concert, array $pending, Validator $validator): void
    {
        if ($pending === []) {
            return;
        }
        $error = function (string $path) use ($validator): void {
            $validator->errors()->add('pendingCatalog.'.$path, 'Choose a valid, exclusively referenced catalog draft.');
        };
        foreach (['venue' => 'venue', 'event' => 'performance'] as $singleton => $kind) {
            if (! isset($pending[$singleton])) {
                continue;
            }
            if (isset($concert['catalog']['eventId'])) {
                $error($singleton);
            }
            try {
                app(CatalogProviderSelection::class)->read($pending[$singleton]['selection'], $kind, 'pendingCatalog.'.$singleton.'.selection');
            } catch (ValidationException $failure) {
                foreach ($failure->errors() as $path => $messages) {
                    $validator->errors()->add($path, $messages[0]);
                }
            }
        }
        $used = ['artists' => [], 'songs' => [], 'tours' => []];
        if (array_key_exists('tours', $pending) !== array_key_exists('tourChoices', $pending)) {
            $error('tours');
        }
        $keys = [];
        foreach (['artists' => 'artist', 'songs' => 'work', 'tours' => 'series'] as $map => $kind) {
            foreach ($pending[$map] ?? [] as $key => $input) {
                if (! Str::isUuid((string) $key) || isset($keys[$key])) {
                    $error($map);
                }
                $keys[$key] = true;
                if ($input['selection'] !== null) {
                    try {
                        if ($kind === 'series') {
                            app(CatalogProviderSelection::class)->tour($input['selection'], 'pendingCatalog.'.$map.'.'.$key.'.selection');
                        } else {
                            app(MusicBrainzSelection::class)->read($input['selection'], $kind, 'pendingCatalog.'.$map.'.'.$key.'.selection');
                        }
                    } catch (ValidationException $failure) {
                        foreach ($failure->errors() as $path => $messages) {
                            $validator->errors()->add($path, $messages[0]);
                        }
                    }
                }
            }
        }
        foreach ($pending['lineup'] as $position => $key) {
            $input = $pending['artists'][$key] ?? null;
            if (! preg_match('/^(0|[1-9][0-9]*)$/', (string) $position) || ! array_key_exists($position, $concert['artists']) || $input === null || ($concert['catalog']['artistIds'][$position] ?? null) !== null || ($concert['artists'][$position] ?? null) !== ($input['name'] ?? null) || isset($concert['catalog']['eventId'])) {
                $error('lineup');
            }
            $used['artists'][$key] = true;
        }
        foreach ($pending['tourChoices'] ?? [] as $position => $key) {
            $input = $pending['tours'][$key] ?? null;
            $choice = $concert['catalog']['tours'][$position] ?? null;
            if (! preg_match('/^(0|[1-9][0-9]*)$/', (string) $position) || $choice === null || $input === null || $choice['catalogId'] !== null || $choice['name'] !== $input['name'] || isset($concert['catalog']['eventId'])) {
                $error('tourChoices');
            }
            $used['tours'][$key] = true;
        }
        $rows = array_column($concert['setlist'], null, 'id');
        foreach ($pending['entries'] as $id => $refs) {
            $row = $rows[$id] ?? null;
            if (! Str::isUuid((string) $id) || $row === null || $row['type'] !== 'song' || $refs === []) {
                $error('entries');
            }
            foreach ($refs as $kind => $key) {
                $map = $kind === 'song' ? 'songs' : 'artists';
                $label = $kind === 'song' ? 'title' : 'name';
                $input = $pending[$map][$key] ?? null;
                if ($input === null || isset($row[$kind === 'song' ? 'songCatalogId' : 'artistCatalogId']) || ($row[$kind === 'song' ? 'title' : 'artist'] ?? null) !== ($input[$label] ?? null)) {
                    $error('entries');
                }
                $used[$map][$key] = true;
            }
        }
        foreach ($used as $map => $refs) {
            foreach ($pending[$map] ?? [] as $key => $input) {
                if (! isset($refs[$key])) {
                    $error($map);
                }
            }
        }
    }
}
