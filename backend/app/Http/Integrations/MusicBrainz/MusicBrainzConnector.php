<?php

declare(strict_types=1);

namespace App\Http\Integrations\MusicBrainz;

use App\Http\Integrations\ProviderConnector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;

class MusicBrainzConnector extends ProviderConnector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://musicbrainz.org/ws/2';
    }

    /**
     * MusicBrainz answers its rate limiters with 503 and a Retry-After header, so that is a
     * retryable rejection like 429. A 503 without Retry-After stays an outage.
     */
    public function isRateLimited(Response $response): bool
    {
        return $response->status() === 429
            || ($response->status() === 503 && $response->getPsrResponse()->hasHeader('Retry-After'));
    }
}
