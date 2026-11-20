<?php

namespace App\Http\Integrations\Wikimedia;

use App\Http\Integrations\ProviderConnector;
use Saloon\Traits\Plugins\AcceptsJson;

class WikidataConnector extends ProviderConnector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://www.wikidata.org';
    }
}
