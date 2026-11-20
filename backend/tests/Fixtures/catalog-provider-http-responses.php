<?php

use Tests\Support\ProviderHttp;

$f = require __DIR__.'/catalog-provider-inputs.php';
$place = ['id' => '50573105-4880-4a66-b668-0356374918f0', 'name' => 'Allstate Arena', 'address' => 'Fixture street 1', 'coordinates' => ['latitude' => '42.005', 'longitude' => '-87.887'], 'aliases' => [['name' => 'Arena Ñ']], 'relations' => [['type' => 'setlistfm', 'target-type' => 'url', 'url' => ['resource' => $f['venue']['url']]], ['type' => 'wikidata', 'target-type' => 'url', 'url' => ['resource' => 'https://www.wikidata.org/wiki/Q62005881']]]];
$file = 'Movistar_Arena_junio_de_2025_-_BugWarp_04.jpg';
$image = ['url' => 'https://upload.wikimedia.org/wikipedia/commons/e/e2/'.$file, 'thumburl' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/e2/'.$file.'/960px-'.$file, 'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:'.$file, 'extmetadata' => ['Artist' => ['value' => '<a>BugWarp</a>'], 'LicenseShortName' => ['value' => 'CC BY-SA 4.0'], 'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0/']]];

return [
    'https://musicbrainz.org/ws/2/place/*' => ProviderHttp::response($place),
    'https://musicbrainz.org/ws/2/place?*' => ProviderHttp::response(['places' => [$place]]),
    'https://musicbrainz.org/ws/2/artist?*' => ProviderHttp::response(['artists' => [['id' => $f['mbid'], 'name' => 'Maná', 'type' => 'Group', 'disambiguation' => '', 'country' => 'MX', 'area' => ['name' => 'Mexico']]]]),
    'https://musicbrainz.org/ws/2/work?*' => ProviderHttp::response(['works' => []]),
    'https://www.wikidata.org/wiki/Special:EntityData/*' => ProviderHttp::response(['entities' => ['Q62005881' => ['claims' => ['P18' => [['rank' => 'normal', 'mainsnak' => ['datavalue' => ['value' => str_replace('_', ' ', $file)]]]]]]]]),
    'https://commons.wikimedia.org/w/api.php*' => ProviderHttp::response(['query' => ['pages' => ['1' => ['imageinfo' => [$image]]]]]),
];
