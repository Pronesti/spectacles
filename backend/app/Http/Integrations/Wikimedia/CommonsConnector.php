<?php

declare(strict_types=1);

namespace App\Http\Integrations\Wikimedia;

use App\Http\Integrations\ProviderConnector;
use Saloon\Traits\Plugins\AcceptsJson;

class CommonsConnector extends ProviderConnector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://commons.wikimedia.org';
    }
}
