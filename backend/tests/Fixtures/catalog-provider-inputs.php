<?php

$mbid = '55441123-89a2-49ab-ae3f-df7a3e86835a';
$tour = ['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'tour',
    'externalId' => '2bde703a'], 'values' => ['name' => 'Drama y luz'],
    'url' => 'https://www.setlist.fm/search?artist=4bd7afb6&tour=2bde703a',
    'selection' => '', 'localId' => null];
$venue = ['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'venue',
    'externalId' => '73d6ca11'], 'values' => ['name' => 'Allstate Arena',
        'city' => 'Rosemont', 'region' => 'IL', 'countryCode' => 'US',
        'address' => null, 'aliases' => [], 'lat' => null, 'lng' => null,
        'timezone' => null, 'image' => null], 'linkedRefs' => [],
    'url' => 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html',
    'selection' => '', 'localId' => null];
$artist = ['ref' => ['provider' => 'musicbrainz', 'resourceType' => 'artist',
    'externalId' => $mbid], 'values' => ['name' => 'Maná', 'type' => 'group',
        'disambiguation' => null], 'url' => 'https://www.setlist.fm/setlists/mana-4bd7afb6.html',
    'selection' => '', 'localId' => null];
$performance = ['ref' => ['provider' => 'setlist_fm', 'resourceType' => 'setlist',
    'externalId' => 'b4cc196'], 'artistName' => 'Maná', 'venueName' => 'Allstate Arena',
    'city' => 'Rosemont', 'date' => ['year' => 2026, 'month' => 10, 'day' => 30],
    'url' => 'https://www.setlist.fm/show/2026-10-30/allstate-arena-rosemont-il-2b893c8a.html?setlist=b4cc196',
    'locator' => '', 'localEventId' => null, 'artist' => $artist, 'tour' => $tour,
    'venue' => $venue, 'time' => null, 'timezone' => null, 'selection' => '',
    'localEvent' => null];

return compact('mbid', 'artist', 'tour', 'venue', 'performance');
