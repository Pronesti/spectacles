<?php

use Illuminate\Support\Facades\Http;

$f = require __DIR__.'/catalog-provider-inputs.php';
$place = ['id' => '50573105-4880-4a66-b668-0356374918f0', 'name' => 'Allstate Arena', 'address' => 'Fixture street 1', 'coordinates' => ['latitude' => '42.005', 'longitude' => '-87.887'], 'aliases' => [['name' => 'Arena Ñ']], 'relations' => [['type' => 'setlistfm', 'target-type' => 'url', 'url' => ['resource' => $f['venue']['url']]], ['type' => 'wikidata', 'target-type' => 'url', 'url' => ['resource' => 'https://www.wikidata.org/wiki/Q62005881']]]];
$file = 'Movistar_Arena_junio_de_2025_-_BugWarp_04.jpg';
$image = ['url' => 'https://upload.wikimedia.org/wikipedia/commons/e/e2/'.$file, 'thumburl' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/e2/'.$file.'/960px-'.$file, 'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:'.$file, 'extmetadata' => ['Artist' => ['value' => '<a>BugWarp</a>'], 'LicenseShortName' => ['value' => 'CC BY-SA 4.0'], 'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0/']]];

return [
    'https://musicbrainz.org/ws/2/artist/*' => Http::response(['id' => $f['mbid'], 'relations' => [['type' => 'setlistfm', 'target-type' => 'url', 'url' => ['resource' => $f['artist']['url']]]]]),
    'https://www.setlist.fm/setlists/*' => function ($request) {
        $html = file_get_contents(__DIR__.'/setlistfm/artist-listing.html');
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
        foreach (['tour', 'venue', 'year', 'country'] as $key) {
            if (isset($query[$key])) {
                $html = str_replace('name="'.$key.'"', 'name="ignored-'.$key.'"', $html);
                $html .= '<form><select name="'.$key.'"><option value="'.htmlspecialchars($query[$key], ENT_QUOTES).'" selected>Selected fixture filter</option></select></form>';
            }
        }

        return Http::response($html);
    },
    'https://www.setlist.fm/venue/*' => Http::response(file_get_contents(__DIR__.'/setlistfm/real-venue-listing.html')),
    'https://www.setlist.fm/search*' => function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
        $name = ($query['upcoming'] ?? '') === 'true' ? 'upcoming-search' : 'past-search';

        return Http::response(file_get_contents(__DIR__.'/setlistfm/'.$name.'.html'), 200, ['Content-Type' => 'text/html']);
    },
    'https://www.setlist.fm/setlist/*' => Http::response(file_get_contents(__DIR__.'/setlistfm/past-performance.html'), 200, ['Content-Type' => 'text/html']),
    'https://www.setlist.fm/show/*' => Http::response(file_get_contents(__DIR__.'/setlistfm/upcoming-performance.html'), 200, ['Content-Type' => 'text/html']),
    'https://musicbrainz.org/ws/2/place/*' => Http::response($place),
    'https://musicbrainz.org/ws/2/place?*' => Http::response(['places' => [$place]]),
    'https://musicbrainz.org/ws/2/artist?*' => Http::response(['artists' => [['id' => $f['mbid'], 'name' => 'Maná', 'type' => 'Group', 'disambiguation' => '', 'country' => 'MX', 'area' => ['name' => 'Mexico']]]]),
    'https://musicbrainz.org/ws/2/work?*' => Http::response(['works' => []]),
    'https://www.wikidata.org/wiki/Special:EntityData/*' => Http::response(['entities' => ['Q62005881' => ['claims' => ['P18' => [['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => str_replace('_', ' ', $file)]]]]]]]]),
    'https://commons.wikimedia.org/w/api.php*' => Http::response(['query' => ['pages' => ['1' => ['imageinfo' => [$image]]]]]),
];
