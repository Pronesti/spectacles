<?php

$musicBrainzContact = trim((string) env('MUSICBRAINZ_CONTACT', ''));
$musicBrainzUserAgent = trim((string) env('MUSICBRAINZ_USER_AGENT', ''));

if ($musicBrainzUserAgent === '' && $musicBrainzContact !== '') {
    $musicBrainzUserAgent = 'Spectacles/1.0 ('.$musicBrainzContact.')';
}

return [
    'setlistfm' => [
        'enabled' => env('SETLISTFM_ENABLED', false),
        'user_agent' => env('SETLISTFM_USER_AGENT', '') ?: $musicBrainzUserAgent,
        'html_interval_ms' => (int) env('SETLISTFM_HTML_INTERVAL_MS', 2000),
        'html_daily_limit' => (int) env('SETLISTFM_HTML_DAILY_LIMIT', 0),
        'cache_seconds' => (int) env('SETLISTFM_CACHE_SECONDS', 0),
    ],
    'venue_images' => [
        'enabled' => env('VENUE_IMAGES_ENABLED', false),
        'wikidata_interval_ms' => (int) env('VENUE_IMAGES_WIKIDATA_INTERVAL_MS', 1000),
        'wikidata_daily_limit' => (int) env('VENUE_IMAGES_WIKIDATA_DAILY_LIMIT', 0),
        'commons_interval_ms' => (int) env('VENUE_IMAGES_COMMONS_INTERVAL_MS', 1000),
        'commons_daily_limit' => (int) env('VENUE_IMAGES_COMMONS_DAILY_LIMIT', 0),
        'cache_seconds' => (int) env('VENUE_IMAGES_CACHE_SECONDS', 0),
    ],
    'musicbrainz' => ['enabled' => env('MUSICBRAINZ_ENABLED', false), 'user_agent' => $musicBrainzUserAgent],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
