<?php

declare(strict_types=1);

namespace App\Http\Integrations\Wikimedia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetWikidataEntity extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $entityId) {}

    public function resolveEndpoint(): string
    {
        return '/wiki/Special:EntityData/'.$this->entityId.'.json';
    }
}
