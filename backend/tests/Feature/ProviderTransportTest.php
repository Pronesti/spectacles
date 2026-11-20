<?php

namespace Tests\Feature;

use App\Http\Integrations\MusicBrainz\MusicBrainzConnector;
use App\Http\Integrations\MusicBrainz\Requests\GetMusicBrainzResource;
use App\Http\Integrations\ProviderConnector;
use App\Http\Integrations\SetlistFm\Requests\GetSetlistFmApiResource;
use App\Http\Integrations\SetlistFm\SetlistFmApiConnector;
use App\Http\Integrations\Wikimedia\CommonsConnector;
use App\Http\Integrations\Wikimedia\Requests\GetCommonsImageInfo;
use App\Http\Integrations\Wikimedia\Requests\GetWikidataEntity;
use App\Http\Integrations\Wikimedia\WikidataConnector;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Tests\TestCase;

class ProviderTransportTest extends TestCase
{
    /**
     * @return array<string, array{0: ProviderConnector, 1: Request, 2: string}>
     */
    public static function connectors(): array
    {
        return [
            'setlist.fm API' => [new SetlistFmApiConnector('Agent/1.0', 'secret'), new GetSetlistFmApiResource('venue/6bd6ca6e'), 'https://api.setlist.fm/rest/1.0/venue/6bd6ca6e'],
            'MusicBrainz' => [new MusicBrainzConnector('Agent/1.0'), new GetMusicBrainzResource('place/abc', ['fmt' => 'json']), 'https://musicbrainz.org/ws/2/place/abc?fmt=json'],
            'Wikidata' => [new WikidataConnector('Agent/1.0'), new GetWikidataEntity('Q1'), 'https://www.wikidata.org/wiki/Special:EntityData/Q1.json'],
            'Commons' => [new CommonsConnector('Agent/1.0'), new GetCommonsImageInfo('A.jpg'), 'https://commons.wikimedia.org/w/api.php?action=query&prop=imageinfo&iiprop=url%7Cextmetadata&iiurlwidth=800&titles=File%3AA.jpg&format=json'],
        ];
    }

    #[DataProvider('connectors')]
    public function test_provider_requests_use_short_timeouts_no_redirects_streaming_and_the_user_agent(ProviderConnector $connector, Request $request, string $url): void
    {
        $sent = null;
        $connector->withMockClient(new MockClient(['*' => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent = $pending;

            return MockResponse::make();
        }]));
        $connector->send($request);

        $this->assertSame($url, (string) $sent->getUri());
        $this->assertSame('Agent/1.0', $sent->headers()->get('User-Agent'));
        $this->assertEquals([RequestOptions::CONNECT_TIMEOUT => 2, RequestOptions::TIMEOUT => 5, RequestOptions::ALLOW_REDIRECTS => false, RequestOptions::STREAM => true], $sent->config()->all());
    }

    public function test_streamed_bodies_are_accepted_up_to_the_cap_and_rejected_beyond_it(): void
    {
        $connector = new MusicBrainzConnector('Agent/1.0');
        $connector->withMockClient(new MockClient([
            MockResponse::make(str_repeat('a', ProviderConnector::MAX_BODY_BYTES)),
            MockResponse::make(str_repeat('a', ProviderConnector::MAX_BODY_BYTES + 1)),
        ]));

        $this->assertSame(ProviderConnector::MAX_BODY_BYTES, strlen(ProviderConnector::boundedBody($connector->send(new GetMusicBrainzResource('place')))));
        $this->assertNull(ProviderConnector::boundedBody($connector->send(new GetMusicBrainzResource('place'))));
    }
}
