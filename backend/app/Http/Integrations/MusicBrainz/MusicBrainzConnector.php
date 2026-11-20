<?php

declare(strict_types=1);

namespace App\Http\Integrations\MusicBrainz;

use App\Http\Integrations\ProviderConnector;
use Saloon\Traits\Plugins\AcceptsJson;

class MusicBrainzConnector extends ProviderConnector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://musicbrainz.org/ws/2';
    }
}
