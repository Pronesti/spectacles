<?php

namespace App\Http\Integrations\SetlistFm\Requests;

use App\Http\Data\SetlistFmSearchQueryData;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetSetlistFmApiResource extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $path, protected ?SetlistFmSearchQueryData $search = null) {}

    public function resolveEndpoint(): string
    {
        return '/'.$this->path;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->search?->parameters() ?? [];
    }
}
