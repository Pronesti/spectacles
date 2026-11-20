<?php

declare(strict_types=1);

namespace App\Http\Integrations\SetlistFm;

use App\Http\Integrations\ProviderConnector;
use Saloon\Traits\Plugins\AcceptsJson;

class SetlistFmApiConnector extends ProviderConnector
{
    use AcceptsJson;

    public function __construct(string $userAgent, protected string $apiKey)
    {
        parent::__construct($userAgent);
    }

    public function resolveBaseUrl(): string
    {
        return 'https://api.setlist.fm/rest/1.0';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [...parent::defaultHeaders(), 'x-api-key' => $this->apiKey, 'Accept-Language' => 'en'];
    }
}
